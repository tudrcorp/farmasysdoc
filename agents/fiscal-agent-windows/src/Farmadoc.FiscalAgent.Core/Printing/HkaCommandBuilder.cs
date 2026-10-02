using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using Farmadoc.FiscalAgent.Models;

namespace Farmadoc.FiscalAgent.Printing
{
    /// <summary>
    /// Longitudes de campo del protocolo HKA Venezuela. Los valores por defecto corresponden al formato
    /// estándar (flag 21 = 00): precio 8+2, cantidad 5+3. Ajustables por agent.json sin recompilar.
    /// </summary>
    public sealed class HkaCommandFormat
    {
        public int PriceIntegerDigits { get; set; } = 8;

        public int PriceDecimals { get; set; } = 2;

        public int QuantityIntegerDigits { get; set; } = 5;

        public int QuantityDecimals { get; set; } = 3;

        public int DiscountIntegerDigits { get; set; } = 7;

        public int DiscountDecimals { get; set; } = 2;

        public int PaymentIntegerDigits { get; set; } = 10;

        public int PaymentDecimals { get; set; } = 2;

        public int MaxDescriptionLength { get; set; } = 40;

        public int MaxCustomerDocumentLength { get; set; } = 14;

        public int MaxCustomerNameLength { get; set; } = 40;

        public int MaxExtraLineLength { get; set; } = 40;

        public int CreditNoteInvoiceNumberDigits { get; set; } = 11;

        public string CreditNoteDateFormat { get; set; } = "dd-MM-yyyy";

        /// <summary>Comando que anula el documento fiscal en curso.</summary>
        public string CancelDocumentCommand { get; set; } = "7";

        /// <summary>Prefijo de cada línea de un documento no fiscal (a confirmar con el manual HKA del firmware).</summary>
        public string NonFiscalLinePrefix { get; set; } = "800";

        /// <summary>Comando que cierra el documento no fiscal.</summary>
        public string NonFiscalCloseCommand { get; set; } = "810";

        public int MaxNonFiscalLineLength { get; set; } = 40;
    }

    /// <summary>Secuencia de comandos de un documento, separada en cuerpo (cliente + ítems) y pagos (cierre).</summary>
    public sealed class HkaCommandPlan
    {
        public List<string> Body { get; } = new List<string>();

        public List<string> Payments { get; } = new List<string>();

        public IEnumerable<string> All => Body.Concat(Payments);
    }

    public sealed class HkaCommandException : Exception
    {
        public HkaCommandException(string message) : base(message)
        {
        }
    }

    /// <summary>
    /// Traduce el payload de Farmadoc a comandos del protocolo HKA (Venezuela).
    /// Se construye el plan completo ANTES de abrir el documento: cualquier error de formato
    /// falla sin haber enviado nada a la máquina fiscal.
    /// </summary>
    public sealed class HkaCommandBuilder
    {
        private static readonly string[] InvoiceTaxPrefixes = { " ", "!", "\"", "#" };

        private static readonly string[] CreditNoteTaxPrefixes = { "d0", "d1", "d2", "d3" };

        private readonly HkaCommandFormat _format;
        private readonly IReadOnlyDictionary<string, string> _paymentSlots;
        private readonly bool _sendCustomerAddress;

        public HkaCommandBuilder(HkaCommandFormat format, IReadOnlyDictionary<string, string> paymentSlots, bool sendCustomerAddress = true)
        {
            _format = format ?? new HkaCommandFormat();
            _paymentSlots = paymentSlots ?? new Dictionary<string, string>();
            _sendCustomerAddress = sendCustomerAddress;
        }

        public HkaCommandPlan Build(FiscalPayload payload, PrinterTaxes taxes)
        {
            if (payload == null)
            {
                throw new HkaCommandException("El documento no trae payload.");
            }

            if (payload.Version != 1)
            {
                throw new HkaCommandException("Versión de payload no soportada: " + payload.Version + ".");
            }

            var isCreditNote = payload.Type == FiscalDocumentTypes.CreditNote;

            if (payload.Type != FiscalDocumentTypes.Invoice && !isCreditNote)
            {
                throw new HkaCommandException("Tipo de documento no imprimible como factura: " + payload.Type + ".");
            }

            if (payload.Items == null || payload.Items.Count == 0)
            {
                throw new HkaCommandException("El documento no tiene ítems.");
            }

            var plan = new HkaCommandPlan();

            if (isCreditNote)
            {
                AddCreditNoteReference(plan, payload.OriginalInvoice);
            }

            AddCustomer(plan, payload.Customer, payload.SaleNumber, isCreditNote);

            foreach (var item in payload.Items)
            {
                AddItem(plan, item, taxes, isCreditNote);
            }

            AddPayments(plan, payload.Payments);

            return plan;
        }

        /// <summary>Ticket no fiscal: líneas de texto + cierre. No afecta la memoria fiscal.</summary>
        public HkaCommandPlan BuildNonFiscal(FiscalPayload payload)
        {
            var lines = (payload?.Lines ?? new List<string>())
                .Select(line => PrinterText.Clean(line, _format.MaxNonFiscalLineLength))
                .Where(line => line.Length > 0)
                .ToList();

            if (lines.Count == 0)
            {
                throw new HkaCommandException("El ticket no fiscal no tiene texto.");
            }

            var plan = new HkaCommandPlan();
            plan.Body.AddRange(lines.Select(line => _format.NonFiscalLinePrefix + line));
            plan.Payments.Add(_format.NonFiscalCloseCommand);

            return plan;
        }

        private void AddCreditNoteReference(HkaCommandPlan plan, OriginalInvoice original)
        {
            if (original == null || string.IsNullOrWhiteSpace(original.FiscalNumber))
            {
                throw new HkaCommandException("La nota de crédito no trae la factura fiscal original.");
            }

            var digits = new string(original.FiscalNumber.Where(char.IsDigit).ToArray());
            if (digits.Length == 0 || digits.Length > _format.CreditNoteInvoiceNumberDigits)
            {
                throw new HkaCommandException("Número de factura original inválido: " + original.FiscalNumber + ".");
            }

            if (!DateTime.TryParseExact(original.Date, "yyyy-MM-dd", CultureInfo.InvariantCulture, DateTimeStyles.None, out var date))
            {
                throw new HkaCommandException("Fecha de la factura original inválida: " + original.Date + ".");
            }

            var serial = PrinterText.Clean(original.PrinterSerial, 20).Replace(" ", string.Empty);
            if (serial.Length == 0)
            {
                throw new HkaCommandException("La nota de crédito no trae el registro de la máquina que emitió la factura.");
            }

            plan.Body.Add("iF*" + digits.PadLeft(_format.CreditNoteInvoiceNumberDigits, '0'));
            plan.Body.Add("iD*" + date.ToString(_format.CreditNoteDateFormat, CultureInfo.InvariantCulture));
            plan.Body.Add("iI*" + serial);
        }

        private void AddCustomer(HkaCommandPlan plan, FiscalCustomer customer, string saleNumber, bool isCreditNote)
        {
            var document = PrinterText.Clean(customer?.Document, _format.MaxCustomerDocumentLength).Replace(" ", string.Empty);
            var name = PrinterText.Clean(customer?.Name, _format.MaxCustomerNameLength);

            if (isCreditNote && (document.Length == 0 || name.Length == 0))
            {
                throw new HkaCommandException("La nota de crédito requiere RIF/C.I. y nombre del cliente.");
            }

            if (document.Length > 0)
            {
                plan.Body.Add("iR*" + document);
            }

            if (name.Length > 0 && (document.Length > 0 || isCreditNote))
            {
                plan.Body.Add("iS*" + name);
            }

            var extraLine = 0;

            if (_sendCustomerAddress && document.Length > 0)
            {
                var address = PrinterText.Clean(customer?.Address, _format.MaxExtraLineLength - 5);
                if (address.Length > 0)
                {
                    plan.Body.Add(ExtraLine(extraLine++, "DIR: " + address));
                }

                var phone = PrinterText.Clean(customer?.Phone, _format.MaxExtraLineLength - 5);
                if (phone.Length > 0)
                {
                    plan.Body.Add(ExtraLine(extraLine++, "TLF: " + phone));
                }
            }

            var reference = PrinterText.Clean(saleNumber, _format.MaxExtraLineLength - 5);
            if (reference.Length > 0)
            {
                plan.Body.Add(ExtraLine(extraLine, "REF: " + reference));
            }
        }

        private string ExtraLine(int index, string text)
        {
            return "i" + index.ToString("00", CultureInfo.InvariantCulture) + PrinterText.Clean(text, _format.MaxExtraLineLength);
        }

        private void AddItem(HkaCommandPlan plan, FiscalItem item, PrinterTaxes taxes, bool isCreditNote)
        {
            var description = PrinterText.Clean(item.Description, _format.MaxDescriptionLength);
            if (description.Length == 0)
            {
                description = "PRODUCTO";
            }

            if (item.Quantity <= 0)
            {
                throw new HkaCommandException("Cantidad inválida en «" + description + "».");
            }

            var taxIndex = ResolveTaxIndex(item, taxes, description);
            var prefix = isCreditNote ? CreditNoteTaxPrefixes[taxIndex] : InvoiceTaxPrefixes[taxIndex];

            plan.Body.Add(prefix
                + PrinterText.FixedNumber(item.UnitPriceVes, _format.PriceIntegerDigits, _format.PriceDecimals, "Precio de «" + description + "»")
                + PrinterText.FixedNumber(item.Quantity, _format.QuantityIntegerDigits, _format.QuantityDecimals, "Cantidad de «" + description + "»")
                + description);

            if (item.DiscountVes > 0.004m)
            {
                plan.Body.Add("q-" + PrinterText.FixedNumber(item.DiscountVes, _format.DiscountIntegerDigits, _format.DiscountDecimals, "Descuento de «" + description + "»"));
            }
        }

        /// <summary>0 = exento; 1–3 = alícuota programada en la máquina con la misma tasa.</summary>
        private static int ResolveTaxIndex(FiscalItem item, PrinterTaxes taxes, string description)
        {
            if (string.Equals(item.TaxCode, "E", StringComparison.OrdinalIgnoreCase) || item.TaxRatePercent <= 0m)
            {
                return 0;
            }

            if (taxes == null)
            {
                throw new HkaCommandException("No se pudieron leer las alícuotas de la máquina fiscal.");
            }

            var rates = new[] { taxes.Tax1, taxes.Tax2, taxes.Tax3 };
            for (var i = 0; i < rates.Length; i++)
            {
                if (Math.Abs(rates[i] - item.TaxRatePercent) < 0.001m)
                {
                    return i + 1;
                }
            }

            throw new HkaCommandException("La alícuota " + item.TaxRatePercent.ToString(CultureInfo.InvariantCulture)
                + "% de «" + description + "» no está programada en la máquina fiscal ("
                + string.Join(" / ", rates.Select(r => r.ToString(CultureInfo.InvariantCulture) + "%")) + ").");
        }

        private void AddPayments(HkaCommandPlan plan, List<FiscalPayment> payments)
        {
            if (payments == null || payments.Count == 0)
            {
                throw new HkaCommandException("El documento no trae medios de pago.");
            }

            var bySlot = new List<KeyValuePair<string, decimal>>();
            foreach (var payment in payments.Where(p => p.AmountVes > 0m))
            {
                if (!_paymentSlots.TryGetValue(payment.Method ?? string.Empty, out var slot))
                {
                    throw new HkaCommandException("El medio de pago «" + payment.Method
                        + "» no tiene número asignado en payment_slots de agent.json.");
                }

                var index = bySlot.FindIndex(p => p.Key == slot);
                if (index >= 0)
                {
                    bySlot[index] = new KeyValuePair<string, decimal>(slot, bySlot[index].Value + payment.AmountVes);
                }
                else
                {
                    bySlot.Add(new KeyValuePair<string, decimal>(slot, payment.AmountVes));
                }
            }

            if (bySlot.Count == 0)
            {
                throw new HkaCommandException("El documento no trae montos de pago.");
            }

            for (var i = 0; i < bySlot.Count - 1; i++)
            {
                plan.Payments.Add("2" + bySlot[i].Key
                    + PrinterText.FixedNumber(bySlot[i].Value, _format.PaymentIntegerDigits, _format.PaymentDecimals, "Pago parcial"));
            }

            // El último pago cubre el saldo restante y cierra el documento (evita descuadres por redondeo).
            plan.Payments.Add("1" + bySlot[bySlot.Count - 1].Key);
        }
    }
}
