using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using Farmadoc.FiscalAgent.Printing;
using TfhkaNet.IF.VE;

namespace Farmadoc.FiscalAgent.Service
{
    /// <summary>
    /// Máquina fiscal HKA (Venezuela) a través del SDK oficial TfhkaNet.dll (32 bits).
    /// </summary>
    public sealed class HkaFiscalPrinter : IFiscalPrinter
    {
        private readonly string _port;
        private readonly int? _baudRate;
        private readonly bool _verboseSdkLog;

        public HkaFiscalPrinter(string port, int? baudRate, bool verboseSdkLog)
        {
            _port = port;
            _baudRate = baudRate;
            _verboseSdkLog = verboseSdkLog;
        }

        public IPrinterSession Open()
        {
            var fiscal = new Tfhka();

            if (_verboseSdkLog)
            {
                fiscal.ActiveLog(true);
            }

            bool opened;
            try
            {
                opened = _baudRate.HasValue ? fiscal.OpenFpCtrl(_port, _baudRate.Value) : fiscal.OpenFpCtrl(_port);
            }
            catch (Exception ex)
            {
                throw new PrinterUnavailableException("No se pudo abrir " + _port + ": " + ex.Message, ex);
            }

            if (!opened)
            {
                throw new PrinterUnavailableException("No se pudo abrir " + _port + " (¿otro programa, como Valery, lo está usando?).");
            }

            bool responds;
            try
            {
                responds = fiscal.CheckFPrinter();
            }
            catch (Exception ex)
            {
                SafeClose(fiscal);
                throw new PrinterUnavailableException("La máquina fiscal no responde en " + _port + ": " + ex.Message, ex);
            }

            if (!responds)
            {
                SafeClose(fiscal);
                throw new PrinterUnavailableException("La máquina fiscal no responde en " + _port + " (¿apagada o sin papel?).");
            }

            return new Session(fiscal);
        }

        private static void SafeClose(Tfhka fiscal)
        {
            try
            {
                fiscal.CloseFpCtrl();
            }
            catch (Exception)
            {
            }
        }

        private sealed class Session : IPrinterSession
        {
            private readonly Tfhka _fiscal;

            public Session(Tfhka fiscal)
            {
                _fiscal = fiscal;
            }

            public PrinterSnapshot ReadSnapshot()
            {
                _fiscal.ReadFpStatus();
                var status = _fiscal.GetPrinterStatus();
                var s1 = _fiscal.GetS1PrinterData();

                return new PrinterSnapshot
                {
                    StatusCode = status.PrinterStatusCode,
                    StatusDescription = status.PrinterStatusDescription,
                    ErrorCode = status.PrinterErrorCode,
                    ErrorDescription = status.PrinterErrorDescription,
                    LastInvoiceNumber = s1.LastInvoiceNumber,
                    LastCreditNoteNumber = s1.LastCreditNoteNumber,
                    LastNonFiscalDocNumber = s1.LastNonFiscalDocNumber,
                    DailyClosureCounter = s1.DailyClosureCounter,
                    RegisteredMachineNumber = s1.RegisteredMachineNumber,
                    Rif = s1.RIF,
                    PrinterDateTime = s1.CurrentPrinterDateTime,
                };
            }

            public PrinterTaxes ReadTaxes()
            {
                var s3 = _fiscal.GetS3PrinterData();

                return new PrinterTaxes
                {
                    Tax1 = ToMoney(s3.Tax1),
                    Tax2 = ToMoney(s3.Tax2),
                    Tax3 = ToMoney(s3.Tax3),
                    IgtfRate = ToMoney(s3.TaxIGTF),
                    SystemFlags = s3.AllSystemFlags ?? new int[0],
                };
            }

            public bool Send(string command)
            {
                return _fiscal.SendCmd(command);
            }

            public decimal? ReadAmountPayable()
            {
                var s2 = _fiscal.GetS2PrinterData();
                return s2 == null ? (decimal?)null : ToMoney(s2.AmountPayable);
            }

            public void PrintXReport()
            {
                _fiscal.PrintXReport();
            }

            public void PrintZReport()
            {
                _fiscal.PrintZReport();
            }

            public ZReportData ReadLastZReport()
            {
                return Map(_fiscal.GetZReport());
            }

            public IList<ZReportData> ReadZReports(int fromNumber, int toNumber)
            {
                var reports = _fiscal.GetZReport(fromNumber, toNumber);

                return reports == null
                    ? new List<ZReportData>()
                    : reports.Select(Map).Where(r => r != null).ToList();
            }

            public void Dispose()
            {
                SafeClose(_fiscal);
            }

            /// <summary>Una trama que el SDK no pudo interpretar deja todo en cero: se descarta (número de Z 0).</summary>
            private static ZReportData Map(ReportData data)
            {
                if (data == null || data.NumberOfLastZReport <= 0)
                {
                    return null;
                }

                return new ZReportData
                {
                    ZNumber = data.NumberOfLastZReport,
                    ZDatetime = FormatDate(data.ZReportDate),
                    LastInvoiceNumber = data.NumberOfLastInvoice,
                    LastInvoiceDatetime = FormatDate(data.LastInvoiceDate),
                    LastCreditNoteNumber = data.NumberOfLastCreditNote,
                    LastDebitNoteNumber = data.NumberOfLastDebitNote,
                    LastNonFiscalNumber = data.NumberOfLastNonFiscal,
                    ExemptSales = ToMoney(data.FreeSalesTax),
                    GeneralBaseSales = ToMoney(data.GeneralRate1Sale),
                    GeneralTaxSales = ToMoney(data.GeneralRate1Tax),
                    ReducedBaseSales = ToMoney(data.ReducedRate2Sale),
                    ReducedTaxSales = ToMoney(data.ReducedRate2Tax),
                    AdditionalBaseSales = ToMoney(data.AdditionalRate3Sale),
                    AdditionalTaxSales = ToMoney(data.AdditionalRate3Tax),
                    ExemptCreditNotes = ToMoney(data.FreeTaxDevolution),
                    GeneralBaseCreditNotes = ToMoney(data.GeneralRateDevolution),
                    GeneralTaxCreditNotes = ToMoney(data.GeneralRateTaxDevolution),
                    ReducedBaseCreditNotes = ToMoney(data.ReducedRateDevolution),
                    ReducedTaxCreditNotes = ToMoney(data.ReducedRateTaxDevolution),
                    AdditionalBaseCreditNotes = ToMoney(data.AdditionalRateDevolution),
                    AdditionalTaxCreditNotes = ToMoney(data.AdditionalRateTaxDevolution),
                    ExemptDebitNotes = ToMoney(data.FreeTaxDebit),
                    GeneralBaseDebitNotes = ToMoney(data.GeneralRateDebit),
                    GeneralTaxDebitNotes = ToMoney(data.GeneralRateTaxDebit),
                    ReducedBaseDebitNotes = ToMoney(data.ReducedRateDebit),
                    ReducedTaxDebitNotes = ToMoney(data.ReducedRateTaxDebit),
                    AdditionalBaseDebitNotes = ToMoney(data.AdditionalRateDebit),
                    AdditionalTaxDebitNotes = ToMoney(data.AdditionalRateTaxDebit),
                    IgtfBaseSales = ToMoney(data.IgtfRateSales),
                    IgtfTaxSales = ToMoney(data.IgtfRateTaxSales),
                    IgtfBaseCreditNotes = ToMoney(data.IgtfRateDevolution),
                    IgtfTaxCreditNotes = ToMoney(data.IgtfRateTaxDevolution),
                    IgtfBaseDebitNotes = ToMoney(data.IgtfRateDebit),
                    IgtfTaxDebitNotes = ToMoney(data.IgtfRateTaxDebit),
                };
            }

            private static string FormatDate(DateTime value)
            {
                return value.Year <= 1 ? null : value.ToString("yyyy-MM-ddTHH:mm:ss", CultureInfo.InvariantCulture);
            }

            private static decimal ToMoney(double value)
            {
                return decimal.Round((decimal)value, 2);
            }
        }
    }
}
