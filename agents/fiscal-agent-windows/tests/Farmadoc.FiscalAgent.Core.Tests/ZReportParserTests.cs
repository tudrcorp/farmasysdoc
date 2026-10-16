using Farmadoc.FiscalAgent.Printing;
using Xunit;

namespace Farmadoc.FiscalAgent.Tests
{
    public class ZReportParserTests
    {
        [Fact]
        public void ParsesIgtfLayoutWithExactAmounts()
        {
            var z = ZReportParser.Parse(Samples.ZRecord(461, 16414, 3697244, 5035780, 805725));

            Assert.Equal(461, z.ZNumber);
            Assert.Equal("2026-10-15T20:26:00", z.ZDatetime);
            Assert.Equal(16414, z.LastInvoiceNumber);
            Assert.Equal(26, z.LastCreditNoteNumber);
            Assert.Equal(36972.44m, z.ExemptSales);
            Assert.Equal(50357.80m, z.GeneralBaseSales);
            Assert.Equal(8057.25m, z.GeneralTaxSales);
            Assert.Equal(39, z.FieldCount);
        }

        [Fact]
        public void LongerAmountFieldsAreAccepted()
        {
            var record = Samples.ZRecord(10, 20, 0, 0, 0).Replace("\n000000000000\n000000000000\n000000000000\n", "\n0000000000001234567\n0000000000005000000\n0000000000000800000\n");

            var z = ZReportParser.Parse(record);

            Assert.Equal(12345.67m, z.ExemptSales);
            Assert.Equal(50000m, z.GeneralBaseSales);
            Assert.Equal(8000m, z.GeneralTaxSales);
        }

        [Fact]
        public void NonNumericAmountThrowsInsteadOfReturningZero()
        {
            var record = Samples.ZRecord(10, 20, 100, 200, 32).Replace("000000000100", "12,34");

            var ex = Assert.Throws<ZReportParseException>(() => ZReportParser.Parse(record));

            Assert.Contains("Campo 9", ex.Message);
        }

        [Fact]
        public void UnknownShortLayoutThrows()
        {
            Assert.Throws<ZReportParseException>(() => ZReportParser.Parse("0461\n261015\n2026\n00016414\n"));
        }

        [Fact]
        public void SplitsUploadedFileIntoRecords()
        {
            var uploaded = Samples.ZRecord(1, 10, 0, 0, 0) + "\r\n" + Samples.ZRecord(2, 20, 0, 0, 0) + "\r\n";

            var records = ZReportParser.SplitRecords(uploaded);

            Assert.Equal(2, records.Count);
            Assert.Equal(2, ZReportParser.Parse(records[1]).ZNumber);
        }
    }
}
