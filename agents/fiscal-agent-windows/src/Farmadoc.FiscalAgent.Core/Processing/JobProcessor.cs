using System;
using System.Globalization;
using System.Linq;
using System.Threading;
using System.Threading.Tasks;
using Farmadoc.FiscalAgent.Logging;
using Farmadoc.FiscalAgent.Models;
using Farmadoc.FiscalAgent.Printing;
using Farmadoc.FiscalAgent.Runtime;
using Farmadoc.FiscalAgent.Server;

namespace Farmadoc.FiscalAgent.Processing
{
    /// <summary>
    /// Imprime un documento fiscal de forma idempotente.
    ///
    /// Reglas (un documento fiscal impreso no se puede deshacer):
    /// 1. Todo el plan de comandos se valida ANTES de abrir el documento.
    /// 2. Antes de imprimir se guarda en disco y en el servidor el último número fiscal de la máquina.
    /// 3. Ante cualquier duda (corte, excepción, reinicio) se compara el contador actual con el guardado:
    ///    +1 = salió; igual = no salió (se puede reintentar); otro valor = «uncertain» (revisión humana).
    /// 4. Nunca se reimprime a ciegas.
    /// </summary>
    public sealed class JobProcessor
    {
        private readonly IFiscalPrinter _printer;
        private readonly IFiscalServer _server;
        private readonly LocalJournal _journal;
        private readonly AgentSettings _settings;
        private readonly PrinterCache _cache;
        private readonly IAgentLog _log;

        public JobProcessor(IFiscalPrinter printer, IFiscalServer server, LocalJournal journal, AgentSettings settings, PrinterCache cache, IAgentLog log)
        {
            _printer = printer;
            _server = server;
            _journal = journal;
            _settings = settings;
            _cache = cache;
            _log = log;
        }

        private string CancelCommand => _settings.CommandFormat.CancelDocumentCommand;

        /// <summary>Tiempo máximo que se espera a que la máquina termine de imprimir y cierre el documento tras el pago.</summary>
        public TimeSpan CloseWaitTimeout { get; set; } = TimeSpan.FromSeconds(20);

        public TimeSpan CloseWaitInterval { get; set; } = TimeSpan.FromMilliseconds(500);

        /// <summary>
        /// Procesa el trabajo y deja el resultado en la bitácora local (el runner lo entrega al servidor).
        /// Lanza excepción solo cuando no es posible saber si el documento salió: el trabajo se reintenta
        /// más tarde como «recovery» y se vuelve a verificar con el contador.
        /// </summary>
        public async Task<JobResult> ProcessAsync(AgentJob job, CancellationToken cancellationToken)
        {
            var entry = _journal.Get(job.Uuid);

            if (entry?.Result != null)
            {
                _log.Info("Documento " + job.Uuid + " ya procesado localmente (" + entry.Result.Outcome + "); se reenvía el resultado.");
                return entry.Result;
            }

            if (job.Simulation)
            {
                var simulated = Simulate(job);
                _journal.SaveResult(job.Uuid, job.Type, simulated);
                return simulated;
            }

            var counterBefore = entry?.CounterBefore ?? ParseCounter(job.PrinterCounterBefore);
            JobResult result;

            try
            {
                using (var session = _printer.Open())
                {
                    result = await ProcessWithSessionAsync(job, counterBefore, session, cancellationToken).ConfigureAwait(false);
                }
            }
            catch (PrinterUnavailableException ex) when (counterBefore == null)
            {
                result = JobResult.Failed("printer_unavailable", ex.Message);
            }
            catch (HkaCommandException ex)
            {
                result = JobResult.Failed("invalid_document", ex.Message);
            }

            _journal.SaveResult(job.Uuid, job.Type, result);
            _log.Info("Documento " + job.Uuid + " (" + job.Type + ") → " + result.Outcome
                + (result.FiscalNumber != null ? " Nº " + result.FiscalNumber : string.Empty)
                + (result.ErrorMessage != null ? " · " + result.ErrorMessage : string.Empty));

            return result;
        }

        /// <summary>
        /// Modo Simulación: arma los comandos con la configuración de esta máquina, sin abrir el puerto
        /// (lo está usando el sistema actual de la caja) y sin imprimir.
        /// </summary>
        private JobResult Simulate(AgentJob job)
        {
            var taxes = _cache.TaxesOrDefault(out var taxesSource);
            JobResult result;

            try
            {
                var plan = _settings.CreateBuilder().Build(job.ReadPayload(), taxes);
                result = new JobResult { Outcome = JobOutcomes.Simulated };
                result.Raw["commands"] = plan.All.ToList();
            }
            catch (HkaCommandException ex)
            {
                result = new JobResult { Outcome = JobOutcomes.Simulated, ErrorCode = "invalid_document", ErrorMessage = ex.Message };
            }

            result.Raw["taxes_source"] = taxesSource;
            result.Raw["payment_slots"] = _settings.PaymentSlots;
            _log.Info("Simulación " + job.Uuid + (result.ErrorMessage != null ? " con error: " + result.ErrorMessage : " OK"));

            return result;
        }

        private async Task<JobResult> ProcessWithSessionAsync(AgentJob job, int? counterBefore, IPrinterSession session, CancellationToken cancellationToken)
        {
            var snapshot = session.ReadSnapshot();
            _cache.Save(snapshot, null);

            if (job.Type == FiscalDocumentTypes.StatusRead)
            {
                return ReadStatus(snapshot, session);
            }

            if (!RegistryMatches(snapshot.RegisteredMachineNumber))
            {
                var message = "La máquina conectada en " + _settings.Local.ComPort + " (" + snapshot.RegisteredMachineNumber
                    + ") no es la configurada (" + _settings.ExpectedRegistry + ").";

                if (counterBefore != null)
                {
                    throw new InvalidOperationException(message);
                }

                return JobResult.Failed("wrong_printer", message);
            }

            switch (job.Type)
            {
                case FiscalDocumentTypes.XReport:
                    session.PrintXReport();
                    return Printed(null, null, session.ReadSnapshot(), null);

                case FiscalDocumentTypes.NonFiscalTicket:
                    return PrintNonFiscalTicket(job, snapshot, session);

                case FiscalDocumentTypes.ZReport:
                    return await PrintZReportAsync(job, counterBefore, snapshot, session, cancellationToken).ConfigureAwait(false);

                case FiscalDocumentTypes.Invoice:
                case FiscalDocumentTypes.CreditNote:
                    return await PrintDocumentAsync(job, counterBefore, snapshot, session, cancellationToken).ConfigureAwait(false);

                default:
                    return JobResult.Failed("unsupported_type", "Tipo de documento no soportado por el agente: " + job.Type + ".");
            }
        }

        private async Task<JobResult> PrintDocumentAsync(AgentJob job, int? counterBefore, PrinterSnapshot snapshot, IPrinterSession session, CancellationToken cancellationToken)
        {
            if (counterBefore != null)
            {
                var recovered = Recover(job, counterBefore.Value, ref snapshot, session);
                if (recovered != null)
                {
                    return recovered;
                }
            }
            else if (snapshot.InFiscalTransaction)
            {
                _log.Warn("Había un documento fiscal abierto que no es de este agente; se anula antes de imprimir.");
                session.Send(CancelCommand);
                snapshot = session.ReadSnapshot();

                if (snapshot.InFiscalTransaction)
                {
                    return JobResult.Failed("printer_busy", "La máquina fiscal tiene un documento abierto que no se pudo anular.");
                }
            }

            if (snapshot.InNonFiscalTransaction)
            {
                return JobResult.Failed("printer_busy", "La máquina fiscal tiene un documento no fiscal abierto (¿otro programa?).");
            }

            var taxes = session.ReadTaxes();
            _cache.Save(snapshot, taxes);
            var plan = _settings.CreateBuilder().Build(job.ReadPayload(), taxes);
            var before = Counter(job.Type, snapshot);

            _journal.SaveStarted(job.Uuid, job.Type, before);
            await _server.MarkStartedAsync(job.Uuid, before, cancellationToken).ConfigureAwait(false);

            decimal? amountPayable = null;

            try
            {
                foreach (var command in plan.Body)
                {
                    if (!session.Send(command))
                    {
                        return Abort(job.Type, before, session, "command_rejected", "La máquina rechazó el comando «" + Describe(command) + "»");
                    }
                }

                amountPayable = session.ReadAmountPayable();

                foreach (var command in plan.Payments)
                {
                    if (!session.Send(command))
                    {
                        return Abort(job.Type, before, session, "payment_rejected", "La máquina rechazó el pago «" + command + "»");
                    }
                }
            }
            catch (Exception ex) when (!(ex is OperationCanceledException))
            {
                _log.Error("Error durante la impresión de " + job.Uuid, ex);
                return VerifyAfterError(job.Type, before, session, amountPayable, taxes, ex);
            }

            var after = await WaitForDocumentCloseAsync(job.Type, before, session, cancellationToken).ConfigureAwait(false);
            var current = Counter(job.Type, after);

            if (current == before + 1)
            {
                return Printed(current, amountPayable, after, taxes);
            }

            if (after.InFiscalTransaction)
            {
                return Abort(job.Type, before, session, "document_not_closed", "Los pagos no cerraron el documento");
            }

            return JobResult.Uncertain("counter_mismatch", "Contador antes " + before + ", después " + current + ".");
        }

        /// <summary>Laboratorio nivel 1: solo lectura; reporta todo lo que ve la máquina, incluso si no es la esperada.</summary>
        private JobResult ReadStatus(PrinterSnapshot snapshot, IPrinterSession session)
        {
            var taxes = session.ReadTaxes();
            _cache.Save(snapshot, taxes);

            var result = new JobResult
            {
                Outcome = JobOutcomes.Printed,
                PrinterSerial = snapshot.RegisteredMachineNumber,
                PrinterDatetime = snapshot.PrinterDateTime?.ToString("yyyy-MM-ddTHH:mm:ss", CultureInfo.InvariantCulture),
                PrinterComputesIgtf = taxes.ComputesIgtf,
            };

            foreach (var pair in snapshot.ToStatusDictionary())
            {
                result.Raw[pair.Key] = pair.Value;
            }

            result.Raw["rif"] = snapshot.Rif;
            result.Raw["tax_rates"] = new[] { taxes.Tax1, taxes.Tax2, taxes.Tax3 };
            result.Raw["igtf_rate"] = taxes.IgtfRate;
            result.Raw["system_flags"] = taxes.SystemFlags;
            result.Raw["registry_matches"] = RegistryMatches(snapshot.RegisteredMachineNumber);
            result.Raw["expected_registry"] = _settings.ExpectedRegistry;

            if (snapshot.PrinterDateTime.HasValue)
            {
                result.Raw["clock_drift_seconds"] = (int)(snapshot.PrinterDateTime.Value - DateTime.Now).TotalSeconds;
            }

            if (!RegistryMatches(snapshot.RegisteredMachineNumber))
            {
                result.ErrorCode = "wrong_printer";
                result.ErrorMessage = "La máquina conectada (" + snapshot.RegisteredMachineNumber + ") no es la configurada (" + _settings.ExpectedRegistry + ").";
            }

            return result;
        }

        /// <summary>Laboratorio nivel 2: documento no fiscal; no toca la memoria fiscal.</summary>
        private JobResult PrintNonFiscalTicket(AgentJob job, PrinterSnapshot snapshot, IPrinterSession session)
        {
            if (snapshot.InFiscalTransaction || snapshot.InNonFiscalTransaction)
            {
                return JobResult.Failed("printer_busy", "La máquina tiene un documento abierto (¿otro programa la está usando?).");
            }

            var plan = _settings.CreateBuilder().BuildNonFiscal(job.ReadPayload());
            var before = snapshot.LastNonFiscalDocNumber;

            foreach (var command in plan.Body)
            {
                if (!session.Send(command))
                {
                    var error = TryRead(session);
                    session.Send(_settings.CommandFormat.NonFiscalCloseCommand);

                    return JobResult.Failed("command_rejected", "La máquina rechazó la línea «" + Describe(command) + "»" + ErrorSuffix(error)
                        + ". Revise non_fiscal_line_prefix en el formato de comandos.");
                }
            }

            if (!session.Send(plan.Payments[0]))
            {
                return JobResult.Failed("command_rejected", "La máquina rechazó el cierre del documento no fiscal («" + plan.Payments[0] + "»)" + ErrorSuffix(TryRead(session)) + ".");
            }

            var after = session.ReadSnapshot();
            var result = Printed(null, null, after, null);
            result.Raw["non_fiscal_number"] = after.LastNonFiscalDocNumber;
            result.Raw["commands"] = plan.All.ToList();

            if (after.LastNonFiscalDocNumber != before + 1)
            {
                result.Outcome = JobOutcomes.Uncertain;
                result.ErrorCode = "counter_mismatch";
                result.ErrorMessage = "Contador de documentos no fiscales " + before + " → " + after.LastNonFiscalDocNumber + "; verifique si salió el ticket.";
            }

            return result;
        }

        /// <summary>
        /// El documento ya se había empezado a imprimir (reinicio, corte, caída de red). Devuelve el resultado si
        /// se puede determinar; null si el documento no salió y se puede imprimir de nuevo.
        /// </summary>
        private JobResult Recover(AgentJob job, int before, ref PrinterSnapshot snapshot, IPrinterSession session)
        {
            _log.Warn("Recuperando " + job.Uuid + ": contador guardado " + before + ".");

            if (snapshot.InFiscalTransaction)
            {
                session.Send(CancelCommand);
                snapshot = session.ReadSnapshot();
            }

            var current = Counter(job.Type, snapshot);

            if (current == before)
            {
                return null;
            }

            if (current == before + 1)
            {
                var result = Printed(current, null, snapshot, null);
                result.Raw["recovered"] = true;
                return result;
            }

            return JobResult.Uncertain("counter_jumped",
                "El contador pasó de " + before + " a " + current + " (¿otro programa imprimió en la máquina?). Verifique en la memoria fiscal.");
        }

        private async Task<JobResult> PrintZReportAsync(AgentJob job, int? counterBefore, PrinterSnapshot snapshot, IPrinterSession session, CancellationToken cancellationToken)
        {
            if (counterBefore != null && snapshot.DailyClosureCounter == counterBefore.Value + 1)
            {
                return ZPrinted(snapshot);
            }

            if (counterBefore != null && snapshot.DailyClosureCounter != counterBefore.Value)
            {
                return JobResult.Uncertain("counter_jumped", "El contador de Z pasó de " + counterBefore + " a " + snapshot.DailyClosureCounter + ".");
            }

            var before = snapshot.DailyClosureCounter;
            _journal.SaveStarted(job.Uuid, job.Type, before);
            await _server.MarkStartedAsync(job.Uuid, before, cancellationToken).ConfigureAwait(false);

            try
            {
                session.PrintZReport();
            }
            catch (Exception ex)
            {
                _log.Error("Error al imprimir el reporte Z", ex);
            }

            var after = session.ReadSnapshot();

            if (after.DailyClosureCounter == before + 1)
            {
                return ZPrinted(after);
            }

            return after.DailyClosureCounter == before
                ? JobResult.Failed("z_not_printed", "La máquina no emitió el reporte Z" + ErrorSuffix(after) + ".")
                : JobResult.Uncertain("counter_mismatch", "Contador de Z antes " + before + ", después " + after.DailyClosureCounter + ".");
        }

        /// <summary>
        /// Tras el último pago la máquina sigue «en transacción» mientras imprime el pie del documento;
        /// el contador fiscal solo sube al cerrarse. Se consulta hasta que cierre o se agote el tiempo.
        /// </summary>
        private async Task<PrinterSnapshot> WaitForDocumentCloseAsync(string type, int before, IPrinterSession session, CancellationToken cancellationToken)
        {
            var deadline = DateTime.UtcNow + CloseWaitTimeout;

            while (true)
            {
                var snapshot = session.ReadSnapshot();

                if (!snapshot.InFiscalTransaction || Counter(type, snapshot) == before + 1 || DateTime.UtcNow >= deadline)
                {
                    return snapshot;
                }

                await Task.Delay(CloseWaitInterval, cancellationToken).ConfigureAwait(false);
            }
        }

        private JobResult Abort(string type, int before, IPrinterSession session, string code, string message)
        {
            var errorSnapshot = TryRead(session);

            if (errorSnapshot != null && Counter(type, errorSnapshot) == before + 1)
            {
                return JobResult.Uncertain(code, message + ErrorSuffix(errorSnapshot) + ". La máquina ya emitió el documento Nº "
                    + Counter(type, errorSnapshot) + "; no se anuló. Verifíquelo y resuélvalo a mano.");
            }
            session.Send(CancelCommand);
            var after = session.ReadSnapshot();
            var detail = message + ErrorSuffix(errorSnapshot) + ".";

            if (Counter(type, after) == before && !after.InFiscalTransaction)
            {
                return JobResult.Failed(code, detail + " El documento se anuló sin emitirse.");
            }

            return JobResult.Uncertain(code, detail + " No se pudo confirmar la anulación; verifique la máquina fiscal.");
        }

        private JobResult VerifyAfterError(string type, int before, IPrinterSession session, decimal? amountPayable, PrinterTaxes taxes, Exception error)
        {
            PrinterSnapshot snapshot;

            try
            {
                snapshot = session.ReadSnapshot();
            }
            catch (Exception)
            {
                throw new InvalidOperationException("Se perdió la comunicación con la máquina fiscal a mitad del documento; se verificará al reconectar.", error);
            }

            var current = Counter(type, snapshot);

            if (current == before + 1 && !snapshot.InFiscalTransaction)
            {
                return Printed(current, amountPayable, snapshot, taxes);
            }

            if (current == before)
            {
                return Abort(type, before, session, "printer_error", "Error de comunicación: " + error.Message);
            }

            return JobResult.Uncertain("printer_error", "Error de comunicación (" + error.Message + ") y contador inesperado (" + before + " → " + current + ").");
        }

        private static JobResult Printed(int? number, decimal? total, PrinterSnapshot snapshot, PrinterTaxes taxes)
        {
            var result = new JobResult
            {
                Outcome = JobOutcomes.Printed,
                FiscalNumber = number?.ToString("D8", CultureInfo.InvariantCulture),
                PrinterSerial = snapshot.RegisteredMachineNumber,
                PrinterDatetime = snapshot.PrinterDateTime?.ToString("yyyy-MM-ddTHH:mm:ss", CultureInfo.InvariantCulture),
                PrinterTotalVes = total.HasValue ? decimal.Round(total.Value, 2) : (decimal?)null,
                PrinterComputesIgtf = taxes?.ComputesIgtf,
            };
            result.Raw["status"] = snapshot.StatusDescription;

            return result;
        }

        private static JobResult ZPrinted(PrinterSnapshot snapshot)
        {
            var result = Printed(null, null, snapshot, null);
            result.ZNumber = snapshot.DailyClosureCounter.ToString(CultureInfo.InvariantCulture);

            return result;
        }

        private static int Counter(string type, PrinterSnapshot snapshot)
        {
            return type == FiscalDocumentTypes.CreditNote ? snapshot.LastCreditNoteNumber : snapshot.LastInvoiceNumber;
        }

        private bool RegistryMatches(string printerRegistry)
        {
            var expected = Normalize(_settings.ExpectedRegistry);
            var actual = Normalize(printerRegistry);

            return actual.Length > 0 && expected.Length > 0
                && (expected == actual || expected.StartsWith(actual, StringComparison.Ordinal) || actual.StartsWith(expected, StringComparison.Ordinal));
        }

        private static string Normalize(string value)
        {
            return new string((value ?? string.Empty).ToUpperInvariant().Where(char.IsLetterOrDigit).ToArray());
        }

        private static int? ParseCounter(string value)
        {
            return int.TryParse(value, NumberStyles.Integer, CultureInfo.InvariantCulture, out var n) ? n : (int?)null;
        }

        private static PrinterSnapshot TryRead(IPrinterSession session)
        {
            try
            {
                return session.ReadSnapshot();
            }
            catch (Exception)
            {
                return null;
            }
        }

        private static string ErrorSuffix(PrinterSnapshot snapshot)
        {
            return snapshot != null && snapshot.HasError ? " (" + snapshot.ErrorDescription + ")" : string.Empty;
        }

        private static string Describe(string command)
        {
            return command.Length > 50 ? command.Substring(0, 50) + "…" : command;
        }
    }
}
