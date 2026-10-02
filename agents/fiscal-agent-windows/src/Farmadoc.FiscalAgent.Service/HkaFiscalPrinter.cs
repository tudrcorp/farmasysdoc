using System;
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

            public void Dispose()
            {
                SafeClose(_fiscal);
            }

            private static decimal ToMoney(double value)
            {
                return decimal.Round((decimal)value, 2);
            }
        }
    }
}
