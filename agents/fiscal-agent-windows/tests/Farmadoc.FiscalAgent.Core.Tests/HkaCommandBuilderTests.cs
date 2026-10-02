using System.Collections.Generic;
using System.Linq;
using Farmadoc.FiscalAgent.Models;
using Farmadoc.FiscalAgent.Printing;
using Newtonsoft.Json.Linq;
using Xunit;

namespace Farmadoc.FiscalAgent.Tests
{
    public class HkaCommandBuilderTests
    {
        private static readonly PrinterTaxes Taxes = new PrinterTaxes { Tax1 = 16m, Tax2 = 8m, Tax3 = 31m };

        private static HkaCommandBuilder Builder() => new HkaCommandBuilder(new HkaCommandFormat(), Samples.Config().PaymentSlots);

        private static FiscalPayload Payload(JObject json) => new AgentJob { Payload = json }.ReadPayload();

        [Fact]
        public void BuildsInvoiceWithCustomerItemsDiscountsAndPayments()
        {
            var plan = Builder().Build(Payload(Samples.InvoicePayload()), Taxes);

            Assert.Equal(new List<string>
            {
                "iR*V12345678",
                "iS*JOSE PEÑA",
                "i00DIR: AV. LIBERTADOR, BARINAS",
                "i01TLF: 0414-1234567",
                "i02REF: VTA-000123",
                "!000000500000002000ACETAMINOFEN 500MG X 10",
                "q-000000500",
                " 000000205000001000ALCOHOL ISOPROPILICO",
            }, plan.Body);

            Assert.Equal(new List<string> { "220000000007300", "102" }, plan.Payments);
        }

        [Fact]
        public void ConsumidorFinalSkipsCustomerIdentification()
        {
            var json = Samples.InvoicePayload();
            json["customer"] = JObject.Parse(@"{ ""document"": null, ""name"": ""CONSUMIDOR FINAL"", ""address"": null, ""phone"": null }");

            var plan = Builder().Build(Payload(json), Taxes);

            Assert.DoesNotContain(plan.Body, c => c.StartsWith("iR*") || c.StartsWith("iS*") || c.StartsWith("i00DIR"));
            Assert.Contains("i00REF: VTA-000123", plan.Body);
        }

        [Fact]
        public void SinglePaymentClosesWithTotalPaymentCommand()
        {
            var json = Samples.InvoicePayload();
            json["payments"] = JArray.Parse(@"[{ ""method"": ""mobile_payment_ves"", ""amount_ves"": 130.7, ""is_foreign_currency"": false }]");

            var plan = Builder().Build(Payload(json), Taxes);

            Assert.Equal(new List<string> { "105" }, plan.Payments);
        }

        [Fact]
        public void MapsTaxRatesToProgrammedPrinterSlots()
        {
            var json = Samples.InvoicePayload();
            json["items"][0]["tax_rate_percent"] = 8;
            json["items"][1]["tax_code"] = "G";
            json["items"][1]["tax_rate_percent"] = 31;

            var plan = Builder().Build(Payload(json), Taxes);

            Assert.StartsWith("\"", plan.Body.Single(c => c.EndsWith("ACETAMINOFEN 500MG X 10")));
            Assert.StartsWith("#", plan.Body.Single(c => c.EndsWith("ALCOHOL ISOPROPILICO")));
        }

        [Fact]
        public void RejectsTaxRateNotProgrammedInPrinter()
        {
            var json = Samples.InvoicePayload();
            json["items"][0]["tax_rate_percent"] = 12;

            var error = Assert.Throws<HkaCommandException>(() => Builder().Build(Payload(json), Taxes));
            Assert.Contains("12%", error.Message);
        }

        [Fact]
        public void RejectsUnmappedPaymentMethod()
        {
            var json = Samples.InvoicePayload();
            json["payments"] = JArray.Parse(@"[{ ""method"": ""zelle"", ""amount_ves"": 130.7, ""is_foreign_currency"": true }]");

            var error = Assert.Throws<HkaCommandException>(() => Builder().Build(Payload(json), Taxes));
            Assert.Contains("zelle", error.Message);
        }

        [Fact]
        public void RejectsAmountsThatOverflowFieldLength()
        {
            var json = Samples.InvoicePayload();
            json["items"][0]["unit_price_ves"] = 123456789m;

            Assert.Throws<HkaCommandException>(() => Builder().Build(Payload(json), Taxes));
        }

        [Fact]
        public void BuildsCreditNoteWithOriginalInvoiceReference()
        {
            var json = Samples.InvoicePayload();
            json["type"] = FiscalDocumentTypes.CreditNote;
            json["original_invoice"] = JObject.Parse(@"{ ""fiscal_number"": ""00005720"", ""printer_serial"": ""ZZP0020235"", ""date"": ""2026-10-02"", ""time"": ""12:01"" }");

            var plan = Builder().Build(Payload(json), Taxes);

            Assert.Equal("iF*00000005720", plan.Body[0]);
            Assert.Equal("iD*02-10-2026", plan.Body[1]);
            Assert.Equal("iI*ZZP0020235", plan.Body[2]);
            Assert.Contains("d1000000500000002000ACETAMINOFEN 500MG X 10", plan.Body);
            Assert.Contains("d0000000205000001000ALCOHOL ISOPROPILICO", plan.Body);
        }

        [Fact]
        public void CreditNoteRequiresCustomerIdentification()
        {
            var json = Samples.InvoicePayload();
            json["type"] = FiscalDocumentTypes.CreditNote;
            json["customer"]["document"] = null;
            json["original_invoice"] = JObject.Parse(@"{ ""fiscal_number"": ""00005720"", ""printer_serial"": ""ZZP0020235"", ""date"": ""2026-10-02"" }");

            Assert.Throws<HkaCommandException>(() => Builder().Build(Payload(json), Taxes));
        }

        [Theory]
        [InlineData(12.5, 8, 2, "0000001250")]
        [InlineData(1, 5, 3, "00001000")]
        [InlineData(0.005, 8, 2, "0000000001")]
        [InlineData(2.345, 5, 3, "00002345")]
        public void FormatsFixedWidthNumbers(double value, int integers, int decimals, string expected)
        {
            Assert.Equal(expected, PrinterText.FixedNumber((decimal)value, integers, decimals, "campo"));
        }

        [Fact]
        public void CleansTextForPrinter()
        {
            Assert.Equal("JOSE PEÑA NUÑEZ", PrinterText.Clean("  José  Peña\tNúñez ", 40));
            Assert.Equal("ABC", PrinterText.Clean("abcdef", 3));
        }
    }
}
