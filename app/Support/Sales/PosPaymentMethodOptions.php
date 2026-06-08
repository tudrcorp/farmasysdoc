<?php

namespace App\Support\Sales;

use App\Models\Sale;
use Illuminate\Support\HtmlString;

final class PosPaymentMethodOptions
{
    public const CACHEA = 'cachea';

    public const CACHEA_LOGO = 'images/logos/cachea.jpg';

    /**
     * Métodos de cobro en caja que exigen referencia de pago (campo «Referencia de pago»).
     *
     * @return list<string>
     */
    public static function methodsRequiringPaymentReference(): array
    {
        return ['transfer_ves', 'zelle'];
    }

    public static function requiresPaymentReference(string $paymentMethod): bool
    {
        return in_array(strtolower(trim($paymentMethod)), self::methodsRequiringPaymentReference(), true);
    }

    /**
     * Opciones visibles del select «Cobro» en la caja (Cachea se activa con el toggle dedicado).
     *
     * @return array<string, string>
     */
    public static function posCobroOptions(): array
    {
        return [
            'efectivo_usd' => 'Efectivo USD',
            'efectivo_ves' => 'Efectivo VES',
            'punto_venta_ves' => 'Punto de Venta',
            'transfer_ves' => 'Transferencia VES',
            'zelle' => 'Zelle',
            'pago_movil' => 'Pago Movil',
            'mixed' => 'Pago Multiple',
            'credito_cliente' => 'Crédito · cuenta por cobrar',
        ];
    }

    public static function posCobroOptionLabel(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if ($value === self::CACHEA) {
            return 'Cachea';
        }

        return self::posCobroOptions()[$value] ?? $value;
    }

    public static function isCachea(?string $paymentMethod): bool
    {
        return strtolower(trim((string) $paymentMethod)) === self::CACHEA;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function resolveFromPosRegisterData(array $data): string
    {
        if (filter_var($data['pay_with_cachea'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return self::CACHEA;
        }

        if (filter_var($data['generate_accounts_receivable'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return 'credito_cliente';
        }

        return (string) ($data['payment_method'] ?? '');
    }

    public static function effectiveSalePaymentMethod(Sale $sale): ?string
    {
        if (filled($sale->payment_method)) {
            return (string) $sale->payment_method;
        }

        if ($sale->relationLoaded('conciliationCachea')) {
            return $sale->conciliationCachea !== null ? self::CACHEA : null;
        }

        return $sale->conciliationCachea()->exists() ? self::CACHEA : null;
    }

    public static function cacheaTableBadgeHtml(): string
    {
        $src = e(asset(self::CACHEA_LOGO));

        return '<span class="farmadoc-sales-cachea-payment">'
            .'<img src="'.$src.'" alt="Cachea" class="farmadoc-sales-cachea-payment__icon" loading="lazy" decoding="async" />'
            .'<span class="farmadoc-sales-cachea-payment__label">Cachea</span>'
            .'</span>';
    }

    public static function cacheaNavigationIconUrl(): string
    {
        return asset(self::CACHEA_LOGO);
    }

    public static function cacheaPageHeadingHtml(string $title): HtmlString
    {
        $src = e(asset(self::CACHEA_LOGO));

        return new HtmlString(
            '<span class="farmadoc-cachea-page-heading">'
            .'<img src="'.$src.'" alt="Cachea" class="farmadoc-cachea-page-heading__icon" loading="lazy" decoding="async" />'
            .'<span class="farmadoc-cachea-page-heading__title">'.e($title).'</span>'
            .'</span>'
        );
    }

    public static function cacheaToggleLabel(): HtmlString
    {
        $src = e(asset(self::CACHEA_LOGO));

        return new HtmlString(
            '<span class="farmadoc-pos-cachea-toggle-label">'
            .'<img src="'.$src.'" alt="Cachea" class="farmadoc-pos-cachea-toggle-label__icon" loading="lazy" decoding="async" />'
            .'<span class="farmadoc-pos-cachea-toggle-label__text">Pagar con Cachea</span>'
            .'</span>'
        );
    }
}
