using System.Collections.Generic;
using Newtonsoft.Json.Linq;

namespace Farmadoc.FiscalAgent.Models
{
    public static class FiscalDocumentTypes
    {
        public const string Invoice = "factura";
        public const string CreditNote = "nota_credito";
        public const string XReport = "reporte_x";
        public const string ZReport = "reporte_z";
        public const string StatusRead = "lectura_estado";
        public const string NonFiscalTicket = "ticket_no_fiscal";
    }

    public static class JobOutcomes
    {
        public const string Printed = "printed";
        public const string Failed = "failed";
        public const string Uncertain = "uncertain";
        public const string Simulated = "simulated";
    }

    /// <summary>Respuesta de POST /jobs/claim.</summary>
    public sealed class ClaimResponse
    {
        public AgentJob Job { get; set; }

        public RemoteAgentConfig Config { get; set; }
    }

    /// <summary>Respuesta de POST /heartbeat.</summary>
    public sealed class HeartbeatResponse
    {
        public string ServerTime { get; set; }

        public int PendingJobs { get; set; }

        public RemoteAgentConfig Config { get; set; }
    }

    public static class PrinterModes
    {
        public const string Disabled = "desactivada";
        public const string Simulation = "simulacion";
        public const string Active = "activa";
    }

    /// <summary>Configuración de esta máquina fiscal administrada desde Farmaadmin.</summary>
    public sealed class RemoteAgentConfig
    {
        public string Mode { get; set; }

        public string FiscalRegistry { get; set; }

        public Dictionary<string, string> PaymentSlots { get; set; } = new Dictionary<string, string>();

        public Printing.HkaCommandFormat CommandFormat { get; set; }
    }

    public sealed class AgentJob
    {
        public string Uuid { get; set; }

        public string Type { get; set; }

        /// <summary>Documento de prueba: se arman los comandos sin imprimir.</summary>
        public bool Simulation { get; set; }

        /// <summary>Prueba del laboratorio fiscal pedida por un administrador (sin venta asociada).</summary>
        public bool IsTest { get; set; }

        public bool Recovery { get; set; }

        public int Attempts { get; set; }

        public string PrinterCounterBefore { get; set; }

        public JObject Payload { get; set; }

        public FiscalPayload ReadPayload()
        {
            return Payload?.ToObject<FiscalPayload>(Newtonsoft.Json.JsonSerializer.Create(AgentJson.Settings));
        }
    }

    /// <summary>Contrato v1 generado por FiscalDocumentPayloadBuilder en Farmadoc.</summary>
    public sealed class FiscalPayload
    {
        public int Version { get; set; }

        public string Type { get; set; }

        public string SaleNumber { get; set; }

        public decimal ExchangeRateVesPerUsd { get; set; }

        public FiscalCustomer Customer { get; set; }

        public OriginalInvoice OriginalInvoice { get; set; }

        public List<FiscalItem> Items { get; set; } = new List<FiscalItem>();

        public List<FiscalPayment> Payments { get; set; } = new List<FiscalPayment>();

        public ExpectedTotals Expected { get; set; }

        /// <summary>Líneas del ticket no fiscal (laboratorio).</summary>
        public List<string> Lines { get; set; } = new List<string>();
    }

    public sealed class FiscalCustomer
    {
        public string Document { get; set; }

        public string Name { get; set; }

        public string Address { get; set; }

        public string Phone { get; set; }
    }

    public sealed class OriginalInvoice
    {
        public string FiscalNumber { get; set; }

        public string PrinterSerial { get; set; }

        /// <summary>Formato Y-m-d.</summary>
        public string Date { get; set; }

        public string Time { get; set; }
    }

    public sealed class FiscalItem
    {
        public string Code { get; set; }

        public string Description { get; set; }

        public decimal Quantity { get; set; }

        public decimal UnitPriceVes { get; set; }

        public decimal DiscountVes { get; set; }

        public string TaxCode { get; set; }

        public decimal TaxRatePercent { get; set; }
    }

    public sealed class FiscalPayment
    {
        public string Method { get; set; }

        public decimal AmountVes { get; set; }

        public bool IsForeignCurrency { get; set; }
    }

    public sealed class ExpectedTotals
    {
        public decimal SubtotalVes { get; set; }

        public decimal DiscountVes { get; set; }

        public decimal TaxVes { get; set; }

        public decimal IgtfVes { get; set; }

        public decimal TotalVes { get; set; }
    }

    /// <summary>Cuerpo de POST /jobs/{uuid}/result.</summary>
    public sealed class JobResult
    {
        public string Outcome { get; set; }

        public string FiscalNumber { get; set; }

        public string PrinterSerial { get; set; }

        public string ZNumber { get; set; }

        public string PrinterDatetime { get; set; }

        public decimal? PrinterTotalVes { get; set; }

        public bool? PrinterComputesIgtf { get; set; }

        public string ErrorCode { get; set; }

        public string ErrorMessage { get; set; }

        public Dictionary<string, object> Raw { get; set; } = new Dictionary<string, object>();

        public static JobResult Failed(string code, string message)
        {
            return new JobResult { Outcome = JobOutcomes.Failed, ErrorCode = code, ErrorMessage = message };
        }

        public static JobResult Uncertain(string code, string message)
        {
            return new JobResult { Outcome = JobOutcomes.Uncertain, ErrorCode = code, ErrorMessage = message };
        }
    }
}
