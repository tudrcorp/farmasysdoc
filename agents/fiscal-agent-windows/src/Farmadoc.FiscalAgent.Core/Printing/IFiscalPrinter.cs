using System;
using System.Collections.Generic;

namespace Farmadoc.FiscalAgent.Printing
{
    /// <summary>
    /// Acceso exclusivo a la máquina fiscal. Cada sesión abre y cierra el puerto (robusto ante reconexiones USB).
    /// </summary>
    public interface IFiscalPrinter
    {
        /// <summary>Abre el puerto; lanza <see cref="PrinterUnavailableException"/> si está ocupado o desconectado.</summary>
        IPrinterSession Open();
    }

    public interface IPrinterSession : IDisposable
    {
        PrinterSnapshot ReadSnapshot();

        PrinterTaxes ReadTaxes();

        /// <summary>Envía un comando del protocolo HKA. Devuelve false si la máquina lo rechazó.</summary>
        bool Send(string command);

        /// <summary>Monto a pagar del documento abierto (estado S2), con impuestos.</summary>
        decimal? ReadAmountPayable();

        void PrintXReport();

        void PrintZReport();
    }

    public sealed class PrinterSnapshot
    {
        public int StatusCode { get; set; }

        public string StatusDescription { get; set; }

        public int ErrorCode { get; set; }

        public string ErrorDescription { get; set; }

        public int LastInvoiceNumber { get; set; }

        public int LastCreditNoteNumber { get; set; }

        public int LastNonFiscalDocNumber { get; set; }

        public int DailyClosureCounter { get; set; }

        public string RegisteredMachineNumber { get; set; }

        public string Rif { get; set; }

        public DateTime? PrinterDateTime { get; set; }

        public bool HasError => ErrorCode != 0;

        /// <summary>Hay un documento fiscal abierto (p. ej. tras un corte a mitad de factura).</summary>
        public bool InFiscalTransaction =>
            StatusDescription != null
            && StatusDescription.IndexOf("in Fiscal Transaction", StringComparison.OrdinalIgnoreCase) >= 0;

        public bool InNonFiscalTransaction =>
            StatusDescription != null
            && StatusDescription.IndexOf("in Non Fiscal Transaction", StringComparison.OrdinalIgnoreCase) >= 0;

        public Dictionary<string, object> ToStatusDictionary()
        {
            return new Dictionary<string, object>
            {
                ["status_code"] = StatusCode,
                ["status"] = StatusDescription,
                ["error_code"] = ErrorCode,
                ["error"] = ErrorDescription,
                ["last_invoice_number"] = LastInvoiceNumber,
                ["last_credit_note_number"] = LastCreditNoteNumber,
                ["last_non_fiscal_doc_number"] = LastNonFiscalDocNumber,
                ["daily_closure_counter"] = DailyClosureCounter,
                ["registered_machine_number"] = RegisteredMachineNumber,
                ["printer_datetime"] = PrinterDateTime?.ToString("yyyy-MM-ddTHH:mm:ss"),
            };
        }
    }

    public sealed class PrinterTaxes
    {
        public decimal Tax1 { get; set; }

        public decimal Tax2 { get; set; }

        public decimal Tax3 { get; set; }

        public decimal IgtfRate { get; set; }

        public int[] SystemFlags { get; set; } = new int[0];

        public bool ComputesIgtf => IgtfRate > 0m;
    }

    public sealed class PrinterUnavailableException : Exception
    {
        public PrinterUnavailableException(string message, Exception inner = null) : base(message, inner)
        {
        }
    }
}
