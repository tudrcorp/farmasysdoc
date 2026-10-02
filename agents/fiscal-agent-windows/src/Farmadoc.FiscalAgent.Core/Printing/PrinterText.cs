using System.Globalization;
using System.Text;

namespace Farmadoc.FiscalAgent.Printing
{
    /// <summary>
    /// Normaliza texto para la máquina fiscal (ISO-8859-1): mayúsculas, sin acentos salvo la Ñ, sin caracteres de control.
    /// </summary>
    public static class PrinterText
    {
        public static string Clean(string value, int maxLength)
        {
            if (string.IsNullOrWhiteSpace(value) || maxLength <= 0)
            {
                return string.Empty;
            }

            var upper = value.Trim().ToUpperInvariant().Replace('Ñ', '\u0001');
            var decomposed = upper.Normalize(NormalizationForm.FormD);
            var builder = new StringBuilder(decomposed.Length);

            foreach (var ch in decomposed)
            {
                if (ch == '\u0001')
                {
                    builder.Append('Ñ');
                    continue;
                }

                if (CharUnicodeInfo.GetUnicodeCategory(ch) == UnicodeCategory.NonSpacingMark)
                {
                    continue;
                }

                if (ch >= 32 && ch <= 126)
                {
                    builder.Append(ch);
                }
                else if (char.IsWhiteSpace(ch))
                {
                    builder.Append(' ');
                }
            }

            var clean = builder.ToString().Normalize(NormalizationForm.FormC).Trim();

            while (clean.Contains("  "))
            {
                clean = clean.Replace("  ", " ");
            }

            return clean.Length > maxLength ? clean.Substring(0, maxLength).TrimEnd() : clean;
        }

        /// <summary>Monto sin separador decimal, con ceros a la izquierda (p. ej. 12,5 con 8+2 → "0000001250").</summary>
        public static string FixedNumber(decimal value, int integerDigits, int decimals, string fieldName)
        {
            if (value < 0)
            {
                throw new HkaCommandException(fieldName + " no puede ser negativo (" + value.ToString(CultureInfo.InvariantCulture) + ").");
            }

            var scaled = decimal.Round(value * Pow10(decimals), 0, System.MidpointRounding.AwayFromZero);
            var digits = scaled.ToString("0", CultureInfo.InvariantCulture);
            var width = integerDigits + decimals;

            if (digits.Length > width)
            {
                throw new HkaCommandException(fieldName + " (" + value.ToString(CultureInfo.InvariantCulture)
                    + ") excede el máximo de " + integerDigits + " enteros y " + decimals + " decimales de la máquina fiscal.");
            }

            return digits.PadLeft(width, '0');
        }

        private static decimal Pow10(int exponent)
        {
            var result = 1m;
            for (var i = 0; i < exponent; i++)
            {
                result *= 10m;
            }

            return result;
        }
    }
}
