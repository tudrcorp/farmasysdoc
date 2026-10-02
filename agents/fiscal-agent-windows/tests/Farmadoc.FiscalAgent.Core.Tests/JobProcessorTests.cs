using System;
using System.Linq;
using System.Net.Http;
using System.Threading;
using System.Threading.Tasks;
using Farmadoc.FiscalAgent.Models;
using Farmadoc.FiscalAgent.Printing;
using Farmadoc.FiscalAgent.Processing;
using Farmadoc.FiscalAgent.Runtime;
using Xunit;

namespace Farmadoc.FiscalAgent.Tests
{
    public class JobProcessorTests
    {
        private readonly FakePrinter _printer = new FakePrinter();
        private readonly FakeServer _server = new FakeServer();
        private readonly LocalJournal _journal = new LocalJournal(Samples.TempDirectory());

        private readonly AgentSettings _settings = new AgentSettings(Samples.Config(), null);
        private readonly PrinterCache _cache = new PrinterCache(null);

        private JobProcessor Processor()
        {
            return new JobProcessor(_printer, _server, _journal, _settings, _cache, new NullLog());
        }

        private static AgentJob SimulationJob()
        {
            var job = Samples.InvoiceJob();
            job.Simulation = true;
            return job;
        }

        [Fact]
        public async Task SimulationBuildsCommandsWithoutTouchingThePort()
        {
            _printer.Available = false;

            var result = await Processor().ProcessAsync(SimulationJob(), CancellationToken.None);

            Assert.Equal(JobOutcomes.Simulated, result.Outcome);
            Assert.Null(result.ErrorCode);
            Assert.Contains("102", (System.Collections.Generic.IEnumerable<string>)result.Raw["commands"]);
            Assert.Empty(_printer.Sent);
            Assert.Empty(_server.Started);
            Assert.Equal(5719, _printer.LastInvoiceNumber);
        }

        [Fact]
        public async Task SimulationReportsConfigurationErrors()
        {
            _settings.Update(new RemoteAgentConfig
            {
                Mode = PrinterModes.Simulation,
                FiscalRegistry = "ZZP0020235-I",
                PaymentSlots = new System.Collections.Generic.Dictionary<string, string> { ["cash_ves"] = "09" },
            });

            var result = await Processor().ProcessAsync(SimulationJob(), CancellationToken.None);

            Assert.Equal(JobOutcomes.Simulated, result.Outcome);
            Assert.Equal("invalid_document", result.ErrorCode);
            Assert.Contains("cash_usd", result.ErrorMessage);
        }

        [Fact]
        public async Task RemotePaymentSlotsOverrideLocalConfiguration()
        {
            _settings.Update(new RemoteAgentConfig
            {
                Mode = PrinterModes.Active,
                FiscalRegistry = "ZZP0020235-I",
                PaymentSlots = new System.Collections.Generic.Dictionary<string, string> { ["cash_usd"] = "20", ["card_ves"] = "09" },
            });

            await Processor().ProcessAsync(Samples.InvoiceJob(), CancellationToken.None);

            Assert.Contains("109", _printer.Sent);
        }

        [Fact]
        public async Task RemoteRegistryIsEnforced()
        {
            _settings.Update(new RemoteAgentConfig { Mode = PrinterModes.Active, FiscalRegistry = "ZZP0099999" });

            var result = await Processor().ProcessAsync(Samples.InvoiceJob(), CancellationToken.None);

            Assert.Equal("wrong_printer", result.ErrorCode);
            Assert.Empty(_printer.Sent);
        }

        [Fact]
        public async Task PrintingRefreshesPrinterCacheForHeartbeat()
        {
            await Processor().ProcessAsync(Samples.InvoiceJob(), CancellationToken.None);

            var status = _cache.ToStatusDictionary();
            Assert.Equal("ZZP0020235", status["registered_machine_number"]);
            Assert.Equal(new[] { 16m, 8m, 31m }, (decimal[])status["tax_rates"]);
        }

        [Fact]
        public async Task PrintsInvoiceAndReportsFiscalNumber()
        {
            var job = Samples.InvoiceJob();

            var result = await Processor().ProcessAsync(job, CancellationToken.None);

            Assert.Equal(JobOutcomes.Printed, result.Outcome);
            Assert.Equal("00005720", result.FiscalNumber);
            Assert.Equal("ZZP0020235", result.PrinterSerial);
            Assert.Equal(116m, result.PrinterTotalVes);
            Assert.False(result.PrinterComputesIgtf);
            Assert.Equal((job.Uuid, 5719), _server.Started.Single());
            Assert.Equal(result.FiscalNumber, _journal.Get(job.Uuid).Result.FiscalNumber);
        }

        [Fact]
        public async Task SameJobIsNeverPrintedTwice()
        {
            var job = Samples.InvoiceJob();
            var processor = Processor();

            await processor.ProcessAsync(job, CancellationToken.None);
            var sentAfterFirst = _printer.Sent.Count;
            var second = await processor.ProcessAsync(job, CancellationToken.None);

            Assert.Equal("00005720", second.FiscalNumber);
            Assert.Equal(sentAfterFirst, _printer.Sent.Count);
            Assert.Equal(5720, _printer.LastInvoiceNumber);
        }

        [Fact]
        public async Task RecoveryAfterPrintedDocumentDoesNotReprint()
        {
            var job = Samples.InvoiceJob(recovery: true, counterBefore: "5719");
            _printer.LastInvoiceNumber = 5720;

            var result = await Processor().ProcessAsync(job, CancellationToken.None);

            Assert.Equal(JobOutcomes.Printed, result.Outcome);
            Assert.Equal("00005720", result.FiscalNumber);
            Assert.Empty(_printer.Sent);
        }

        [Fact]
        public async Task RecoveryWithOpenDocumentCancelsAndReprints()
        {
            var job = Samples.InvoiceJob(recovery: true, counterBefore: "5719");
            _printer.InTransaction = true;

            var result = await Processor().ProcessAsync(job, CancellationToken.None);

            Assert.Equal("7", _printer.Sent.First());
            Assert.Equal(JobOutcomes.Printed, result.Outcome);
            Assert.Equal("00005720", result.FiscalNumber);
        }

        [Fact]
        public async Task RecoveryWhenAnotherProgramPrintedIsUncertain()
        {
            var job = Samples.InvoiceJob(recovery: true, counterBefore: "5719");
            _printer.LastInvoiceNumber = 5723;

            var result = await Processor().ProcessAsync(job, CancellationToken.None);

            Assert.Equal(JobOutcomes.Uncertain, result.Outcome);
            Assert.Equal("counter_jumped", result.ErrorCode);
            Assert.Empty(_printer.Sent);
        }

        [Fact]
        public async Task RejectedItemCancelsDocumentAndFailsSafely()
        {
            _printer.RejectPrefix = "!";

            var result = await Processor().ProcessAsync(Samples.InvoiceJob(), CancellationToken.None);

            Assert.Equal(JobOutcomes.Failed, result.Outcome);
            Assert.Equal("command_rejected", result.ErrorCode);
            Assert.Equal("7", _printer.Sent.Last());
            Assert.Equal(5719, _printer.LastInvoiceNumber);
        }

        [Fact]
        public async Task CommunicationErrorAfterCloseIsDetectedAsPrinted()
        {
            _printer.ThrowAfterClose = true;

            var result = await Processor().ProcessAsync(Samples.InvoiceJob(), CancellationToken.None);

            Assert.Equal(JobOutcomes.Printed, result.Outcome);
            Assert.Equal("00005720", result.FiscalNumber);
        }

        [Fact]
        public async Task ServerUnreachableBeforePrintingPrintsNothingAndRetriesLater()
        {
            var job = Samples.InvoiceJob();
            _server.FailStarted = true;

            await Assert.ThrowsAsync<HttpRequestException>(() => Processor().ProcessAsync(job, CancellationToken.None));
            Assert.Empty(_printer.Sent);
            Assert.Equal(5719, _journal.Get(job.Uuid).CounterBefore);
            Assert.Null(_journal.Get(job.Uuid).Result);

            _server.FailStarted = false;
            var retry = await Processor().ProcessAsync(Samples.InvoiceJob(job.Uuid, recovery: true), CancellationToken.None);

            Assert.Equal("00005720", retry.FiscalNumber);
            Assert.Equal(5720, _printer.LastInvoiceNumber);
        }

        [Fact]
        public async Task WrongPrinterIsRefused()
        {
            _printer.Registry = "ZZP0099999";

            var result = await Processor().ProcessAsync(Samples.InvoiceJob(), CancellationToken.None);

            Assert.Equal("wrong_printer", result.ErrorCode);
            Assert.Empty(_printer.Sent);
        }

        [Fact]
        public async Task BusyPortFailsWithoutPrinting()
        {
            _printer.Available = false;

            var result = await Processor().ProcessAsync(Samples.InvoiceJob(), CancellationToken.None);

            Assert.Equal(JobOutcomes.Failed, result.Outcome);
            Assert.Equal("printer_unavailable", result.ErrorCode);
        }

        [Fact]
        public async Task BusyPortDuringRecoveryKeepsJobPending()
        {
            _printer.Available = false;

            await Assert.ThrowsAsync<PrinterUnavailableException>(
                () => Processor().ProcessAsync(Samples.InvoiceJob(recovery: true, counterBefore: "5719"), CancellationToken.None));
        }

        [Fact]
        public async Task InvalidPayloadFailsBeforeTouchingPrinter()
        {
            var job = Samples.InvoiceJob();
            job.Payload["payments"][0]["method"] = "bitcoin";

            var result = await Processor().ProcessAsync(job, CancellationToken.None);

            Assert.Equal("invalid_document", result.ErrorCode);
            Assert.Empty(_printer.Sent);
            Assert.Empty(_server.Started);
        }

        [Fact]
        public async Task PrintsZReportAndReportsCounter()
        {
            var job = new AgentJob { Uuid = Guid.NewGuid().ToString(), Type = FiscalDocumentTypes.ZReport };

            var result = await Processor().ProcessAsync(job, CancellationToken.None);

            Assert.Equal(JobOutcomes.Printed, result.Outcome);
            Assert.Equal("283", result.ZNumber);
            Assert.Null(result.FiscalNumber);
        }

        [Fact]
        public async Task ZReportRecoveryDoesNotPrintTwice()
        {
            _printer.DailyClosureCounter = 283;
            var job = new AgentJob { Uuid = Guid.NewGuid().ToString(), Type = FiscalDocumentTypes.ZReport, Recovery = true, PrinterCounterBefore = "282" };

            var result = await Processor().ProcessAsync(job, CancellationToken.None);

            Assert.Equal("283", result.ZNumber);
            Assert.DoesNotContain("I0Z", _printer.Sent);
        }

        [Fact]
        public async Task PrintsCreditNoteUsingCreditNoteCounter()
        {
            var job = Samples.InvoiceJob();
            job.Type = FiscalDocumentTypes.CreditNote;
            job.Payload["type"] = FiscalDocumentTypes.CreditNote;
            job.Payload["original_invoice"] = Newtonsoft.Json.Linq.JObject.Parse(@"{ ""fiscal_number"": ""00005720"", ""printer_serial"": ""ZZP0020235"", ""date"": ""2026-10-02"" }");

            var result = await Processor().ProcessAsync(job, CancellationToken.None);

            Assert.Equal("00000027", result.FiscalNumber);
            Assert.Equal(5719, _printer.LastInvoiceNumber);
        }

        [Fact]
        public async Task StatusReadReportsPrinterDataWithoutPrinting()
        {
            var job = new AgentJob { Uuid = Guid.NewGuid().ToString(), Type = FiscalDocumentTypes.StatusRead, IsTest = true };

            var result = await Processor().ProcessAsync(job, CancellationToken.None);

            Assert.Equal(JobOutcomes.Printed, result.Outcome);
            Assert.Null(result.FiscalNumber);
            Assert.Equal(5719, result.Raw["last_invoice_number"]);
            Assert.Equal(true, result.Raw["registry_matches"]);
            Assert.Empty(_printer.Sent);
        }

        [Fact]
        public async Task StatusReadOnWrongPrinterStillReportsWhatItSees()
        {
            _printer.Registry = "ZZP0099999";
            var job = new AgentJob { Uuid = Guid.NewGuid().ToString(), Type = FiscalDocumentTypes.StatusRead, IsTest = true };

            var result = await Processor().ProcessAsync(job, CancellationToken.None);

            Assert.Equal("wrong_printer", result.ErrorCode);
            Assert.Equal("ZZP0099999", result.PrinterSerial);
            Assert.Equal(false, result.Raw["registry_matches"]);
        }

        [Fact]
        public async Task NonFiscalTicketPrintsLinesAndClosesWithoutTouchingFiscalCounters()
        {
            var job = new AgentJob
            {
                Uuid = Guid.NewGuid().ToString(),
                Type = FiscalDocumentTypes.NonFiscalTicket,
                IsTest = true,
                Payload = Newtonsoft.Json.Linq.JObject.Parse(@"{ ""version"": 1, ""type"": ""ticket_no_fiscal"", ""lines"": [""*** PRUEBA - NO FISCAL ***"", ""Caja 1""] }"),
            };

            var result = await Processor().ProcessAsync(job, CancellationToken.None);

            Assert.Equal(JobOutcomes.Printed, result.Outcome);
            Assert.Equal(new[] { "800*** PRUEBA - NO FISCAL ***", "800CAJA 1", "810" }, _printer.Sent);
            Assert.Equal(321, result.Raw["non_fiscal_number"]);
            Assert.Equal(5719, _printer.LastInvoiceNumber);
            Assert.Empty(_server.Started);
        }

        [Fact]
        public async Task RejectedNonFiscalLineClosesDocumentAndFails()
        {
            _printer.RejectPrefix = "800";
            var job = new AgentJob
            {
                Uuid = Guid.NewGuid().ToString(),
                Type = FiscalDocumentTypes.NonFiscalTicket,
                IsTest = true,
                Payload = Newtonsoft.Json.Linq.JObject.Parse(@"{ ""version"": 1, ""type"": ""ticket_no_fiscal"", ""lines"": [""HOLA""] }"),
            };

            var result = await Processor().ProcessAsync(job, CancellationToken.None);

            Assert.Equal(JobOutcomes.Failed, result.Outcome);
            Assert.Equal("command_rejected", result.ErrorCode);
            Assert.Equal("810", _printer.Sent.Last());
        }
    }
}
