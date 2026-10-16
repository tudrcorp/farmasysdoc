using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;

namespace Farmadoc.FiscalAgent.Printing
{
    /// <summary>
    /// Interpreta la trama cruda de un reporte Z de HKA (respuesta de U0Z / U3A).
    ///
    /// A diferencia del SDK (que ante un campo inesperado deja los montos en cero sin avisar), aquí cualquier
    /// campo que no tenga el formato esperado lanza <see cref="ZReportParseException"/>: un Z nunca se reporta con ceros falsos.
    ///
    /// Campos separados por salto de línea (LF). Formatos conocidos por número de campos (sin el vacío final):
    /// 20 y 21 (antiguos, sin notas de débito), 33 (con notas de débito y percibido), 39 (con IGTF) y ≥ 30 genérico.
    /// Montos: dígitos con los dos últimos como decimales (p. ej. 1234567 = 12.345,67).
    /// </summary>
    public static class ZReportParser
    {
        /// <summary>Separa el texto de UploadReportCmd (registros terminados en CRLF) en tramas individuales.</summary>
        public static IList<string> SplitRecords(string uploaded)
        {
            if (string.IsNullOrEmpty(uploaded))
            {
                return new List<string>();
            }

            return uploaded
                .Split(new[] { "\r\n" }, StringSplitOptions.None)
                .Where(r => r.Trim('\n', '\r', ' ').Length > 0)
                .ToList();
        }

        public static ZReportData Parse(string record)
        {
            if (string.IsNullOrWhiteSpace(record))
            {
                throw new ZReportParseException("Trama vacía.", record);
            }

            var fields = record.Split('\n').ToList();
            if (fields.Count > 0 && fields[fields.Count - 1].Trim('\r', ' ').Length == 0)
            {
                fields.RemoveAt(fields.Count - 1);
            }

            var f = new Reader(fields, record);
            var z = new ZReportData { RawRecord = record, FieldCount = fields.Count };

            switch (fields.Count)
            {
                case 20:
                    z.ZNumber = f.Int(0);
                    z.ZDatetime = f.DateTime(1, null);
                    z.LastInvoiceNumber = f.Int(2);
                    z.LastInvoiceDatetime = f.DateTime(3, 4);
                    ReadSalesAndCredits(f, z, 5, 12);
                    z.LastCreditNoteNumber = f.Int(19);
                    break;

                case 21:
                    z.ZNumber = f.Int(0);
                    z.ZDatetime = f.DateTime(1, 2);
                    z.LastInvoiceNumber = f.Int(3);
                    z.LastInvoiceDatetime = f.DateTime(4, 5);
                    ReadSalesAndCredits(f, z, 6, 13);
                    z.LastCreditNoteNumber = f.Int(20);
                    break;

                default:
                    if (fields.Count < 30)
                    {
                        throw new ZReportParseException("Formato de Z desconocido: " + fields.Count + " campos.", record);
                    }

                    z.ZNumber = f.Int(0);
                    z.ZDatetime = f.DateTime(1, 2);
                    z.LastInvoiceNumber = f.Int(3);
                    z.LastInvoiceDatetime = f.DateTime(4, 5);
                    z.LastDebitNoteNumber = f.Int(6);
                    z.LastCreditNoteNumber = f.Int(7);
                    z.LastNonFiscalNumber = f.Int(8);
                    z.ExemptSales = f.Money(9);
                    z.GeneralBaseSales = f.Money(10);
                    z.GeneralTaxSales = f.Money(11);
                    z.ReducedBaseSales = f.Money(12);
                    z.ReducedTaxSales = f.Money(13);
                    z.AdditionalBaseSales = f.Money(14);
                    z.AdditionalTaxSales = f.Money(15);
                    z.ExemptDebitNotes = f.Money(16);
                    z.GeneralBaseDebitNotes = f.Money(17);
                    z.GeneralTaxDebitNotes = f.Money(18);
                    z.ReducedBaseDebitNotes = f.Money(19);
                    z.ReducedTaxDebitNotes = f.Money(20);
                    z.AdditionalBaseDebitNotes = f.Money(21);
                    z.AdditionalTaxDebitNotes = f.Money(22);
                    z.ExemptCreditNotes = f.Money(23);
                    z.GeneralBaseCreditNotes = f.Money(24);
                    z.GeneralTaxCreditNotes = f.Money(25);
                    z.ReducedBaseCreditNotes = f.Money(26);
                    z.ReducedTaxCreditNotes = f.Money(27);
                    z.AdditionalBaseCreditNotes = f.Money(28);
                    z.AdditionalTaxCreditNotes = f.Money(29);

                    if (fields.Count >= 39)
                    {
                        z.IgtfBaseSales = f.Money(33);
                        z.IgtfTaxSales = f.Money(34);
                        z.IgtfBaseDebitNotes = f.Money(35);
                        z.IgtfTaxDebitNotes = f.Money(36);
                        z.IgtfBaseCreditNotes = f.Money(37);
                        z.IgtfTaxCreditNotes = f.Money(38);
                    }

                    break;
            }

            if (z.ZNumber <= 0)
            {
                throw new ZReportParseException("El número de Z no es válido (" + z.ZNumber + ").", record);
            }

            return z;
        }

        private static void ReadSalesAndCredits(Reader f, ZReportData z, int sales, int credits)
        {
            z.ExemptSales = f.Money(sales);
            z.GeneralBaseSales = f.Money(sales + 1);
            z.GeneralTaxSales = f.Money(sales + 2);
            z.ReducedBaseSales = f.Money(sales + 3);
            z.ReducedTaxSales = f.Money(sales + 4);
            z.AdditionalBaseSales = f.Money(sales + 5);
            z.AdditionalTaxSales = f.Money(sales + 6);
            z.ExemptCreditNotes = f.Money(credits);
            z.GeneralBaseCreditNotes = f.Money(credits + 1);
            z.GeneralTaxCreditNotes = f.Money(credits + 2);
            z.ReducedBaseCreditNotes = f.Money(credits + 3);
            z.ReducedTaxCreditNotes = f.Money(credits + 4);
            z.AdditionalBaseCreditNotes = f.Money(credits + 5);
            z.AdditionalTaxCreditNotes = f.Money(credits + 6);
        }

        private sealed class Reader
        {
            private readonly List<string> _fields;
            private readonly string _record;

            public Reader(List<string> fields, string record)
            {
                _fields = fields;
                _record = record;
            }

            private string Field(int index)
            {
                if (index >= _fields.Count)
                {
                    throw new ZReportParseException("Falta el campo " + index + " (la trama tiene " + _fields.Count + ").", _record);
                }

                return _fields[index].Trim('\r', ' ', '\u0003');
            }

            public int Int(int index)
            {
                var value = Field(index);

                if (!int.TryParse(value, NumberStyles.None, CultureInfo.InvariantCulture, out var number))
                {
                    throw new ZReportParseException("Campo " + index + " no es un número entero: «" + value + "».", _record);
                }

                return number;
            }

            public decimal Money(int index)
            {
                var value = Field(index);
                var negative = value.StartsWith("-", StringComparison.Ordinal);
                var digits = negative ? value.Substring(1) : value;

                if (digits.Length == 0 || !digits.All(char.IsDigit))
                {
                    throw new ZReportParseException("Campo " + index + " no es un monto: «" + value + "».", _record);
                }

                var cents = decimal.Parse(digits, NumberStyles.None, CultureInfo.InvariantCulture);

                return (negative ? -cents : cents) / 100m;
            }

            /// <summary>Fecha AAMMDD y hora HHMM (opcional); 000000 se reporta como vacío.</summary>
            public string DateTime(int dateIndex, int? timeIndex)
            {
                var date = Field(dateIndex);
                var time = timeIndex.HasValue ? Field(timeIndex.Value) : "0000";

                if (date.Length != 6 || !date.All(char.IsDigit) || time.Length < 4 || !time.Substring(0, 4).All(char.IsDigit))
                {
                    throw new ZReportParseException("Fecha/hora inválida en campos " + dateIndex + "/" + timeIndex + ": «" + date + "» «" + time + "».", _record);
                }

                if (date == "000000")
                {
                    return null;
                }

                var parsed = new System.DateTime(
                    2000 + int.Parse(date.Substring(0, 2), CultureInfo.InvariantCulture),
                    int.Parse(date.Substring(2, 2), CultureInfo.InvariantCulture),
                    int.Parse(date.Substring(4, 2), CultureInfo.InvariantCulture),
                    int.Parse(time.Substring(0, 2), CultureInfo.InvariantCulture),
                    int.Parse(time.Substring(2, 2), CultureInfo.InvariantCulture),
                    0);

                return parsed.ToString("yyyy-MM-ddTHH:mm:ss", CultureInfo.InvariantCulture);
            }
        }
    }

    public sealed class ZReportParseException : Exception
    {
        public ZReportParseException(string message, string record) : base(message)
        {
            Record = record;
        }

        public string Record { get; }
    }
}
