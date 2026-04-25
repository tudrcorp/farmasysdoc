<?php

namespace App\Support\Filament;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

final class SaleIosBreakdownHtml
{
    public static function build(Sale $sale): HtmlString
    {
        $sale->loadMissing(['items', 'branch', 'client']);

        $status = $sale->status;
        $statusLabel = $status instanceof SaleStatus ? $status->label() : (string) ($sale->getRawOriginal('status') ?? '—');
        $statusClass = self::statusPillClass($status);

        $branch = filled($sale->branch?->name)
            ? e($sale->branch->name).(filled($sale->branch->code) ? ' · '.e((string) $sale->branch->code) : '')
            : '—';

        $client = filled($sale->client?->name)
            ? e($sale->client->name)
            : 'Venta de mostrador (sin cliente)';

        $soldAt = self::formatDateTime($sale->sold_at);
        $createdAt = self::formatDateTime($sale->created_at);

        $rate = $sale->bcv_ves_per_usd;
        $hasRate = is_numeric($rate) && (float) $rate > 0;
        $vesRef = $hasRate
            ? self::fmtBs((float) $sale->total * (float) $rate)
            : null;

        $items = $sale->items->sortBy('id')->values();
        $lineCount = $items->count();

        $html = '<div class="farmadoc-sale-sheet">';

        $html .= '<header class="farmadoc-sale-sheet__summary" aria-labelledby="farmadoc-sale-sheet-doc-title">'
            .'<div class="farmadoc-sale-sheet__summary-top">'
            .'<div class="farmadoc-sale-sheet__summary-id">'
            .'<span id="farmadoc-sale-sheet-doc-title" class="farmadoc-sale-sheet__eyebrow">Número de venta</span>'
            .'<p class="farmadoc-sale-sheet__doc-number">'.e($sale->sale_number).'</p>'
            .'</div>'
            .'<span class="'.$statusClass.'">'.e($statusLabel).'</span>'
            .'</div>'
            .'<div class="farmadoc-sale-sheet__hero-amount" role="group" aria-label="Total del documento">'
            .'<span class="farmadoc-sale-sheet__hero-amount-label">Total del ticket</span>'
            .'<span class="farmadoc-sale-sheet__hero-amount-value">'.e(self::moneyUsd((string) $sale->total)).'</span>'
            .'</div>';

        if ($vesRef !== null) {
            $html .= '<p class="farmadoc-sale-sheet__hero-ves">Aproximado en bolívares: <strong>'.$vesRef.'</strong>'
                .' <span class="farmadoc-sale-sheet__hint">(solo referencia, según tasa BCV guardada)</span></p>';
        } else {
            $html .= '<p class="farmadoc-sale-sheet__hero-ves farmadoc-sale-sheet__hero-ves--muted">Sin tasa BCV en el documento: no se muestra equivalente en Bs.</p>';
        }

        if ($hasRate) {
            $html .= '<p class="farmadoc-sale-sheet__rate-pill" role="status">'
                .'<span class="farmadoc-sale-sheet__rate-pill-label">Tasa en documento</span> '
                .'<span class="farmadoc-sale-sheet__rate-pill-value">'.e(number_format((float) $rate, 6, ',', '.')).' Bs./USD</span>'
                .'</p>';
        }

        $html .= '</header>';

        $html .= self::section(
            'Lugar, cliente y fechas',
            'Contexto de la operación tal como quedó registrada.',
            self::dlRows([
                ['Sucursal', $branch],
                ['Cliente', $client],
                ['Momento de la venta', e($soldAt)],
                ['Alta en sistema', e($createdAt)],
            ]),
        );

        $html .= self::section(
            'Importes del documento (USD)',
            'Son los totales del ticket: líneas, impuestos y descuentos globales, en dólares.',
            self::dlRows([
                ['Subtotal (suma de líneas antes de impuestos globales)', e(self::moneyUsd((string) $sale->subtotal))],
                ['Descuentos del documento', e(self::moneyUsd((string) $sale->discount_total))],
                ['Impuestos (IVA u otros)', e(self::moneyUsd((string) $sale->tax_total))],
                ['IGTF', e(self::moneyUsd((string) $sale->igtf_total))],
                ['Total del documento', '<strong class="farmadoc-sale-sheet__strong-num">'.e(self::moneyUsd((string) $sale->total)).'</strong>'],
            ]),
        );

        $html .= self::section(
            'Cobro registrado',
            'Montos y datos tal como se cerró el pago; pueden incluir VES aunque el ticket totalice en USD.',
            self::dlRows([
                ['Medio de pago', e(self::paymentMethodLabel($sale->payment_method))],
                ['Estado del cobro', e(self::paymentStatusLabel($sale->payment_status))],
                ['Monto cobrado en USD', e(self::moneyUsd((string) $sale->payment_usd))],
                ['Monto cobrado en VES', e(self::fmtBs((float) $sale->payment_ves))],
                ['Referencia / nº operación', filled($sale->reference) ? e((string) $sale->reference) : '—'],
            ]),
        );

        $auditPairs = [
            ['Registrado por', filled($sale->created_by) ? e((string) $sale->created_by) : '—'],
            ['Última modificación por', filled($sale->updated_by) ? e((string) $sale->updated_by) : '—'],
        ];
        if (filled($sale->notes)) {
            $auditPairs[] = ['Notas internas', nl2br(e((string) $sale->notes))];
        }

        $html .= self::section(
            'Observaciones y trazabilidad',
            null,
            self::dlRows($auditPairs),
        );

        $html .= '<div class="farmadoc-sale-sheet__lines-head">'
            .'<h2 class="farmadoc-sale-sheet__lines-title">Productos vendidos</h2>'
            .'<p class="farmadoc-sale-sheet__lines-caption">'.e((string) $lineCount).' línea(s) · precios y totales de línea en <strong>USD</strong> salvo que se indique.</p>'
            .'</div>';

        $html .= '<div class="farmadoc-sale-sheet__lines">';

        foreach ($items as $index => $item) {
            /** @var SaleItem $item */
            $html .= self::lineCard($item, $index + 1, $lineCount);
        }

        if ($lineCount === 0) {
            $html .= '<p class="farmadoc-sale-sheet__empty">Esta venta no tiene líneas de producto guardadas.</p>';
        }

        $html .= '</div>';

        $html .= '<footer class="farmadoc-sale-sheet__footer">'
            .'Los valores provienen del registro al momento de la venta. El equivalente en Bs. sirve solo como referencia histórica usando la tasa BCV almacenada en el documento.'
            .'</footer>'
            .'</div>';

        return new HtmlString($html);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $rows
     */
    private static function dlRows(array $rows): string
    {
        $out = '<dl class="farmadoc-sale-sheet__dl">';
        foreach ($rows as [$label, $value]) {
            $out .= '<div class="farmadoc-sale-sheet__dl-row">'
                .'<dt>'.e($label).'</dt>'
                .'<dd>'.$value.'</dd>'
                .'</div>';
        }
        $out .= '</dl>';

        return $out;
    }

    private static function section(string $title, ?string $subtitle, string $body): string
    {
        $sub = filled($subtitle)
            ? '<p class="farmadoc-sale-sheet__section-sub">'.e($subtitle).'</p>'
            : '';

        return '<section class="farmadoc-sale-sheet__section" aria-label="'.e($title).'">'
            .'<div class="farmadoc-sale-sheet__section-head">'
            .'<h2 class="farmadoc-sale-sheet__section-title">'.e($title).'</h2>'
            .$sub
            .'</div>'
            .'<div class="farmadoc-sale-sheet__section-card">'.$body.'</div>'
            .'</section>';
    }

    private static function lineCard(SaleItem $item, int $lineNo, int $totalLines): string
    {
        $name = filled($item->product_name_snapshot) ? e((string) $item->product_name_snapshot) : 'Producto';
        $sku = filled($item->sku_snapshot) ? e((string) $item->sku_snapshot) : '—';

        $ids = [];
        if ($item->product_id) {
            $ids[] = 'ID producto '.e((string) $item->product_id);
        }
        if ($item->inventory_id) {
            $ids[] = 'ID inventario '.e((string) $item->inventory_id);
        }
        $idsRow = $ids !== [] ? '<p class="farmadoc-sale-line__ids">'.implode(' · ', $ids).'</p>' : '';

        $qty = (float) $item->quantity;
        $unit = self::moneyUsd((string) $item->unit_price);
        $formula = e(number_format($qty, 3, ',', '.')).' × '.e($unit);

        $body = '<div class="farmadoc-sale-line__top">'
            .'<span class="farmadoc-sale-line__badge" aria-hidden="true">Línea '.$lineNo.' / '.$totalLines.'</span>'
            .'<h3 class="farmadoc-sale-line__name">'.$name.'</h3>'
            .'<p class="farmadoc-sale-line__sku">Código (SKU): <span>'.$sku.'</span></p>'
            .$idsRow
            .'<p class="farmadoc-sale-line__formula" title="Cantidad por precio unitario">'
            .$formula
            .'</p>'
            .'</div>';

        $body .= self::dlRows([
            ['Base de la línea (antes de IVA en la línea)', e(self::moneyUsd((string) $item->line_subtotal))],
            ['Impuesto en la línea', e(self::moneyUsd((string) $item->tax_amount))],
            ['Descuento en la línea', e(self::moneyUsd((string) $item->discount_amount))],
            ['Total de esta línea', '<strong class="farmadoc-sale-sheet__strong-num">'.e(self::moneyUsd((string) $item->line_total)).'</strong>'],
        ]);

        $body .= '<p class="farmadoc-sale-line__divider">Coste y margen <span class="farmadoc-sale-sheet__hint">(gestión)</span></p>';

        $body .= self::dlRows([
            ['Coste unitario', e(self::moneyUsd((string) $item->unit_cost))],
            ['Coste total de la línea', e(self::moneyUsd((string) $item->line_cost_total))],
            ['Margen bruto de la línea', e(self::moneyUsd((string) $item->gross_profit))],
        ]);

        return '<article class="farmadoc-sale-line"><div class="farmadoc-sale-line__card">'.$body.'</div></article>';
    }

    private static function formatDateTime(mixed $value): string
    {
        if (! $value instanceof Carbon) {
            return '—';
        }

        return $value->timezone(config('app.timezone'))->format('d/m/Y H:i');
    }

    private static function statusPillClass(mixed $status): string
    {
        $base = 'farmadoc-sale-sheet__pill';

        if (! $status instanceof SaleStatus) {
            return $base.' farmadoc-sale-sheet__pill--muted';
        }

        return match ($status) {
            SaleStatus::Completed => $base.' farmadoc-sale-sheet__pill--ok',
            SaleStatus::Draft => $base.' farmadoc-sale-sheet__pill--muted',
            SaleStatus::Cancelled => $base.' farmadoc-sale-sheet__pill--danger',
            SaleStatus::Refunded => $base.' farmadoc-sale-sheet__pill--warn',
        };
    }

    private static function moneyUsd(string $value): string
    {
        if (! is_numeric($value)) {
            return '—';
        }

        return '$'.number_format((float) $value, 2, ',', '.');
    }

    private static function fmtBs(float $value): string
    {
        return 'Bs. '.number_format($value, 2, ',', '.');
    }

    private static function paymentMethodLabel(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        $key = strtolower(trim($value));

        return match ($key) {
            'efectivo_usd' => 'Efectivo USD',
            'efectivo_ves' => 'Efectivo VES',
            'punto_venta_ves' => 'Punto de venta',
            'transfer_ves' => 'Transferencia VES',
            'zelle' => 'Zelle',
            'pago_movil' => 'Pago móvil',
            'mixed' => 'Pago mixto',
            'transfer_usd' => 'Transferencias USD',
            'traslado_sucursal' => 'Traslado entre sucursales (costo)',
            'cash', 'efectivo' => 'Efectivo',
            'card', 'tarjeta', 'debit', 'credit' => 'Tarjeta',
            'transfer', 'transferencia', 'nequi', 'daviplata' => 'Transferencia / digital',
            default => $value,
        };
    }

    private static function paymentStatusLabel(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        $key = strtolower(trim($value));

        return match ($key) {
            'paid', 'pagado', 'cobrado' => 'Pagado',
            'pending', 'pendiente' => 'Pendiente',
            'partial', 'parcial' => 'Parcial',
            'refunded', 'reembolsado' => 'Reembolsado',
            default => $value,
        };
    }
}
