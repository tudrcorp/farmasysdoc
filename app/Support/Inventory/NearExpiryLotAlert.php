<?php

namespace App\Support\Inventory;

/**
 * Alerta POS: lote FEFO con stock en sucursal próximo a vencer.
 */
final readonly class NearExpiryLotAlert
{
    public function __construct(
        public int $productLotId,
        public string $expirationMonthYear,
        public float $quantityInLot,
        public int $daysUntilExpiry,
        public string $supplierInvoiceNumber,
        public string $severity,
    ) {}

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function modifierClass(): string
    {
        return $this->isCritical()
            ? 'farmadoc-pos-fefo-alert--critical'
            : 'farmadoc-pos-fefo-alert--warning';
    }

    public function notificationTitle(): string
    {
        return $this->isCritical()
            ? 'Despachar lote por vencer (urgente)'
            : 'Despachar lote por vencer (FEFO)';
    }

    public function notificationBody(string $productLabel): string
    {
        $qty = InventoryQuantityFormat::display($this->quantityInLot);

        return sprintf(
            '«%s»: despache primero el lote que vence %s (factura %s, %s u.). Quedan %d días.',
            $productLabel,
            $this->expirationMonthYear,
            $this->supplierInvoiceNumber !== '' ? $this->supplierInvoiceNumber : '—',
            $qty,
            max(0, $this->daysUntilExpiry),
        );
    }

    public function notificationBodyHtml(string $productLabel): string
    {
        $headline = $this->isCritical()
            ? 'URGENTE — Despachar primero este lote (FEFO)'
            : 'Despachar lote por vencer (FEFO)';

        return sprintf(
            '<div class="farmadoc-pos-fefo-alert %s" role="alert">'
            .'<strong class="farmadoc-pos-fefo-alert__title">%s</strong>'
            .'<span class="farmadoc-pos-fefo-alert__detail">%s</span>'
            .'</div>',
            e($this->modifierClass()),
            e($headline),
            e($this->notificationBody($productLabel)),
        );
    }

    public function bannerHtml(): string
    {
        $headline = $this->isCritical()
            ? 'URGENTE — Despachar primero (FEFO)'
            : 'Despachar lote por vencer (FEFO)';

        $qty = InventoryQuantityFormat::display($this->quantityInLot);
        $invoice = $this->supplierInvoiceNumber !== '' ? $this->supplierInvoiceNumber : '—';
        $days = max(0, $this->daysUntilExpiry);

        return sprintf(
            '<div class="farmadoc-pos-fefo-alert %s" role="alert">'
            .'<strong class="farmadoc-pos-fefo-alert__title">%s</strong>'
            .'<span class="farmadoc-pos-fefo-alert__detail">Vence %s · %s u. · %d días · Fact. %s</span>'
            .'</div>',
            e($this->modifierClass()),
            e($headline),
            e($this->expirationMonthYear),
            e($qty),
            $days,
            e($invoice),
        );
    }

    public function badgeHtml(): string
    {
        $label = $this->isCritical() ? 'FEFO URGENTE' : 'FEFO';

        return sprintf(
            '<span class="farmadoc-pos-fefo-badge %s">%s %s</span>',
            e($this->modifierClass()),
            e($label),
            e($this->expirationMonthYear),
        );
    }
}
