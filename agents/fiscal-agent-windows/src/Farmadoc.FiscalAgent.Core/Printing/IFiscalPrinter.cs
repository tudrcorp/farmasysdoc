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

        /// <summary>
        /// Tramas crudas de un comando de lectura de reportes (U0Z = último Z, U3A = rango de la memoria fiscal).
        /// Se interpretan con <see cref="ZReportParser"/>, nunca con el parser del SDK.
        /// </summary>
        IList<string> ReadReportRecords(string command);
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

    /// <summary>
    /// Totales de un reporte Z tal como los guarda la memoria fiscal (Bs, 2 decimales).
    /// Ventas = facturas; Devoluciones = notas de crédito; Débitos = notas de débito.
    /// </summary>
    public sealed class ZReportData
    {
        /// <summary>Trama tal como la envió la máquina (evidencia para auditoría).</summary>
        public string RawRecord { get; set; }

        public int FieldCount { get; set; }

        public int ZNumber { get; set; }

        /// <summary>Fecha (y hora, si la máquina la envía) del reporte Z, formato yyyy-MM-ddTHH:mm:ss.</summary>
        public string ZDatetime { get; set; }

        public int LastInvoiceNumber { get; set; }

        public string LastInvoiceDatetime { get; set; }

        public int LastCreditNoteNumber { get; set; }

        public int LastDebitNoteNumber { get; set; }

        public int LastNonFiscalNumber { get; set; }

        public decimal ExemptSales { get; set; }

        public decimal GeneralBaseSales { get; set; }

        public decimal GeneralTaxSales { get; set; }

        public decimal ReducedBaseSales { get; set; }

        public decimal ReducedTaxSales { get; set; }

        public decimal AdditionalBaseSales { get; set; }

        public decimal AdditionalTaxSales { get; set; }

        public decimal ExemptCreditNotes { get; set; }

        public decimal GeneralBaseCreditNotes { get; set; }

        public decimal GeneralTaxCreditNotes { get; set; }

        public decimal ReducedBaseCreditNotes { get; set; }

        public decimal ReducedTaxCreditNotes { get; set; }

        public decimal AdditionalBaseCreditNotes { get; set; }

        public decimal AdditionalTaxCreditNotes { get; set; }

        public decimal ExemptDebitNotes { get; set; }

        public decimal GeneralBaseDebitNotes { get; set; }

        public decimal GeneralTaxDebitNotes { get; set; }

        public decimal ReducedBaseDebitNotes { get; set; }

        public decimal ReducedTaxDebitNotes { get; set; }

        public decimal AdditionalBaseDebitNotes { get; set; }

        public decimal AdditionalTaxDebitNotes { get; set; }

        public decimal IgtfBaseSales { get; set; }

        public decimal IgtfTaxSales { get; set; }

        public decimal IgtfBaseCreditNotes { get; set; }

        public decimal IgtfTaxCreditNotes { get; set; }

        public decimal IgtfBaseDebitNotes { get; set; }

        public decimal IgtfTaxDebitNotes { get; set; }
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
