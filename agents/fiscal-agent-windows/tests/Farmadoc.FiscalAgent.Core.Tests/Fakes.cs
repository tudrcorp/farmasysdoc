using System;
using System.Collections.Generic;
using System.IO;
using System.Threading;
using System.Threading.Tasks;
using Farmadoc.FiscalAgent.Logging;
using Farmadoc.FiscalAgent.Models;
using Farmadoc.FiscalAgent.Printing;
using Farmadoc.FiscalAgent.Server;
using Newtonsoft.Json.Linq;

namespace Farmadoc.FiscalAgent.Tests
{
    /// <summary>Máquina fiscal en memoria que imita el ciclo abrir → ítems → pago → cierre del protocolo HKA.</summary>
    public sealed class FakePrinter : IFiscalPrinter
    {
        public int LastInvoiceNumber { get; set; } = 5719;

        public int LastCreditNoteNumber { get; set; } = 26;

        public int DailyClosureCounter { get; set; } = 282;

        public int LastNonFiscalDocNumber { get; set; } = 320;

        public bool NonFiscalOpen { get; set; }

        public string Registry { get; set; } = "ZZP0020235";

        public bool InTransaction { get; set; }

        public bool Available { get; set; } = true;

        public bool CreditNoteOpen { get; set; }

        public List<string> Sent { get; } = new List<string>();

        /// <summary>Rechaza (devuelve false) el primer comando que empiece con este prefijo.</summary>
        public string RejectPrefix { get; set; }

        /// <summary>Lanza excepción DESPUÉS de cerrar el documento (simula corte de comunicación tras imprimir).</summary>
        public bool ThrowAfterClose { get; set; }

        public decimal AmountPayable { get; set; } = 116m;

        public decimal IgtfRate { get; set; }

        public IPrinterSession Open()
        {
            if (!Available)
            {
                throw new PrinterUnavailableException("COM5 ocupado");
            }

            return new Session(this);
        }

        private sealed class Session : IPrinterSession
        {
            private readonly FakePrinter _p;

            public Session(FakePrinter printer)
            {
                _p = printer;
            }

            public PrinterSnapshot ReadSnapshot() => new PrinterSnapshot
            {
                StatusCode = _p.InTransaction ? 5 : 4,
                StatusDescription = _p.InTransaction
                    ? "In Fiscal Mode, in Fiscal Transaction"
                    : _p.NonFiscalOpen ? "In Fiscal Mode, in Non Fiscal Transaction" : "In Fiscal Mode and Waiting",
                ErrorDescription = "No Error",
                LastInvoiceNumber = _p.LastInvoiceNumber,
                LastCreditNoteNumber = _p.LastCreditNoteNumber,
                LastNonFiscalDocNumber = _p.LastNonFiscalDocNumber,
                DailyClosureCounter = _p.DailyClosureCounter,
                RegisteredMachineNumber = _p.Registry,
                PrinterDateTime = new DateTime(2026, 10, 2, 12, 0, 0),
            };

            public PrinterTaxes ReadTaxes() => new PrinterTaxes { Tax1 = 16m, Tax2 = 8m, Tax3 = 31m, IgtfRate = _p.IgtfRate };

            public bool Send(string command)
            {
                _p.Sent.Add(command);

                if (_p.RejectPrefix != null && command.StartsWith(_p.RejectPrefix, StringComparison.Ordinal))
                {
                    _p.RejectPrefix = null;
                    return false;
                }

                if (command.StartsWith("800", StringComparison.Ordinal))
                {
                    _p.NonFiscalOpen = true;
                    return true;
                }

                if (command == "810")
                {
                    if (_p.NonFiscalOpen)
                    {
                        _p.NonFiscalOpen = false;
                        _p.LastNonFiscalDocNumber++;
                    }

                    return true;
                }

                if (command == "7")
                {
                    _p.InTransaction = false;
                    _p.CreditNoteOpen = false;
                    return true;
                }

                if (command.StartsWith("iF*", StringComparison.Ordinal))
                {
                    _p.CreditNoteOpen = true;
                }

                if (command.Length > 1 && (command[0] == ' ' || command[0] == '!' || command[0] == '"' || command[0] == '#' || command.StartsWith("d", StringComparison.Ordinal)))
                {
                    _p.InTransaction = true;
                }

                if (command.Length == 3 && command[0] == '1' && _p.InTransaction)
                {
                    _p.InTransaction = false;
                    if (_p.CreditNoteOpen)
                    {
                        _p.LastCreditNoteNumber++;
                        _p.CreditNoteOpen = false;
                    }
                    else
                    {
                        _p.LastInvoiceNumber++;
                    }

                    if (_p.ThrowAfterClose)
                    {
                        _p.ThrowAfterClose = false;
                        throw new TimeoutException("Sin respuesta tras el cierre");
                    }
                }

                return true;
            }

            public decimal? ReadAmountPayable() => _p.AmountPayable;

            public void PrintXReport() => _p.Sent.Add("I0X");

            public void PrintZReport()
            {
                _p.Sent.Add("I0Z");
                _p.DailyClosureCounter++;
            }

            public void Dispose()
            {
            }
        }
    }

    public sealed class FakeServer : IFiscalServer
    {
        public List<(string Uuid, int Counter)> Started { get; } = new List<(string, int)>();

        public bool FailStarted { get; set; }

        public Task<ClaimResponse> ClaimAsync(int waitSeconds, CancellationToken cancellationToken) => Task.FromResult<ClaimResponse>(null);

        public Task MarkStartedAsync(string uuid, int counterBefore, CancellationToken cancellationToken)
        {
            if (FailStarted)
            {
                throw new System.Net.Http.HttpRequestException("sin internet");
            }

            Started.Add((uuid, counterBefore));
            return Task.CompletedTask;
        }

        public Task SendResultAsync(string uuid, JobResult result, CancellationToken cancellationToken) => Task.CompletedTask;

        public Task<HeartbeatResponse> SendHeartbeatAsync(HeartbeatData data, CancellationToken cancellationToken) => Task.FromResult(new HeartbeatResponse());
    }

    public sealed class NullLog : IAgentLog
    {
        public void Info(string message)
        {
        }

        public void Warn(string message)
        {
        }

        public void Error(string message, Exception exception = null)
        {
        }
    }

    public static class Samples
    {
        public static AgentConfig Config() => new AgentConfig
        {
            ServerUrl = "https://farmadoc.test",
            AgentToken = "fd_fp_test",
            ComPort = "COM5",
            ExpectedRegistry = "ZZP0020235-I",
            PaymentSlots = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase)
            {
                ["cash_ves"] = "01",
                ["card_ves"] = "02",
                ["cash_usd"] = "20",
                ["mobile_payment_ves"] = "05",
            },
        };

        public static string TempDirectory()
        {
            var dir = Path.Combine(Path.GetTempPath(), "fiscal-agent-tests", Guid.NewGuid().ToString("N"));
            Directory.CreateDirectory(dir);
            return dir;
        }

        public static JObject InvoicePayload() => JObject.Parse(@"{
            ""version"": 1,
            ""type"": ""factura"",
            ""sale_number"": ""VTA-000123"",
            ""exchange_rate_ves_per_usd"": 36.5,
            ""customer"": { ""document"": ""V12345678"", ""name"": ""JOSÉ PEÑA"", ""address"": ""Av. Libertador, Barinas"", ""phone"": ""0414-1234567"" },
            ""items"": [
                { ""code"": ""7591"", ""description"": ""Acetaminofén 500mg x 10"", ""quantity"": 2, ""unit_price_ves"": 50, ""discount_ves"": 5, ""tax_code"": ""G"", ""tax_rate_percent"": 16 },
                { ""code"": null, ""description"": ""Alcohol isopropílico"", ""quantity"": 1, ""unit_price_ves"": 20.5, ""discount_ves"": 0, ""tax_code"": ""E"", ""tax_rate_percent"": 0 }
            ],
            ""payments"": [
                { ""method"": ""cash_usd"", ""amount_ves"": 73, ""is_foreign_currency"": true },
                { ""method"": ""card_ves"", ""amount_ves"": 50, ""is_foreign_currency"": false }
            ],
            ""expected"": { ""subtotal_ves"": 120.5, ""discount_ves"": 5, ""tax_ves"": 15.2, ""igtf_ves"": 2.19, ""total_ves"": 132.89 }
        }");

        public static AgentJob InvoiceJob(string uuid = null, bool recovery = false, string counterBefore = null) => new AgentJob
        {
            Uuid = uuid ?? Guid.NewGuid().ToString(),
            Type = FiscalDocumentTypes.Invoice,
            Recovery = recovery,
            PrinterCounterBefore = counterBefore,
            Payload = InvoicePayload(),
        };
    }
}
