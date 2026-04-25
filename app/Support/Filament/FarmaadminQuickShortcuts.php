<?php

namespace App\Support\Filament;

use App\Filament\Resources\Inventories\InventoryResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\OrderServices\OrderServiceResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\ProductTransfers\ProductTransferResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Accesos directos del encabezado Farmaadmin (pill iOS), filtrados por permisos de menú / recursos.
 */
final class FarmaadminQuickShortcuts
{
    public const PANEL_ID = 'farmaadmin';

    /**
     * @return list<array{id: string, label: string, hint: string, href: string, force_full_page: bool}>
     */
    public static function visibleItems(?Authenticatable $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        $panel = self::PANEL_ID;
        $items = [];

        if (SaleResource::canViewAny()) {
            $base = SaleResource::getUrl(panel: $panel, isAbsolute: false);
            $items[] = [
                'id' => 'caja',
                'label' => 'Caja',
                'hint' => 'Cliente y carrito',
                'href' => $base.'?'.http_build_query(['abrir' => 'caja']),
                'force_full_page' => true,
            ];
            $items[] = [
                'id' => 'ventas',
                'label' => 'Ventas',
                'hint' => 'Listado y totales',
                'href' => $base,
                'force_full_page' => false,
            ];
        }

        if (InventoryResource::canViewAny()) {
            $items[] = [
                'id' => 'inventario',
                'label' => 'Inventario',
                'hint' => 'Existencias',
                'href' => InventoryResource::getUrl(panel: $panel, isAbsolute: false),
                'force_full_page' => false,
            ];
        }

        if (ProductResource::canViewAny()) {
            $items[] = [
                'id' => 'productos',
                'label' => 'Productos',
                'hint' => 'Catálogo',
                'href' => ProductResource::getUrl(panel: $panel, isAbsolute: false),
                'force_full_page' => false,
            ];
        }

        if (PurchaseResource::canCreate()) {
            $items[] = [
                'id' => 'compras',
                'label' => 'Compras',
                'hint' => 'Nueva compra',
                'href' => PurchaseResource::getUrl('create', panel: $panel, isAbsolute: false),
                'force_full_page' => false,
            ];
        }

        if (ProductTransferResource::canViewAny()) {
            $items[] = [
                'id' => 'traslados',
                'label' => 'Traslados',
                'hint' => 'Entre sucursales',
                'href' => ProductTransferResource::getUrl(panel: $panel, isAbsolute: false),
                'force_full_page' => false,
            ];
        }

        $orders = self::resolveOrdersShortcut($panel);
        if ($orders !== null) {
            $items[] = $orders;
        }

        return $items;
    }

    /**
     * @return array{id: string, label: string, hint: string, href: string, force_full_page: bool}|null
     */
    private static function resolveOrdersShortcut(string $panel): ?array
    {
        if (OrderResource::canViewAny()) {
            return [
                'id' => 'ordenes',
                'label' => 'Órdenes',
                'hint' => 'Pedidos',
                'href' => OrderResource::getUrl(panel: $panel, isAbsolute: false),
                'force_full_page' => false,
            ];
        }

        if (OrderServiceResource::canViewAny()) {
            return [
                'id' => 'ordenes-servicio',
                'label' => 'Órdenes',
                'hint' => 'Servicio aliados',
                'href' => OrderServiceResource::getUrl(panel: $panel, isAbsolute: false),
                'force_full_page' => false,
            ];
        }

        return null;
    }
}
