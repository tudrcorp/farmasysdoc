<?php

namespace App\Support\Filament;

class FarmaadminMenuAccessCatalog
{
    /**
     * Catálogo central de ítems visibles del menú Farmaadmin.
     *
     * @return array<string, array{label: string, route_name_fragment: string, group: string}>
     */
    public static function items(): array
    {
        return [
            'dashboard' => ['label' => 'Inicio / Dashboard', 'route_name_fragment' => 'pages.dashboard', 'group' => 'General'],
            'orders' => ['label' => 'Ordenes', 'route_name_fragment' => 'resources.orders.', 'group' => 'Operaciones'],
            'order_services' => ['label' => 'Ordenes de servicio', 'route_name_fragment' => 'resources.order-services.', 'group' => 'Operaciones'],
            'sales' => ['label' => 'Ventas', 'route_name_fragment' => 'resources.sales.', 'group' => 'Operaciones'],
            'purchases' => ['label' => 'Compras', 'route_name_fragment' => 'resources.purchases.', 'group' => 'Operaciones'],
            'accounts_payable' => ['label' => 'Cuentas por pagar', 'route_name_fragment' => 'resources.accounts-payables.', 'group' => 'Operaciones'],
            'purchase_histories' => ['label' => 'Histórico de compras', 'route_name_fragment' => 'resources.purchase-histories.', 'group' => 'Operaciones'],
            'deliveries' => ['label' => 'Entregas', 'route_name_fragment' => 'resources.deliveries.', 'group' => 'Operaciones'],
            'clients' => ['label' => 'Clientes', 'route_name_fragment' => 'resources.clients.', 'group' => 'Operaciones'],
            'partner_companies' => ['label' => 'Compañias aliadas', 'route_name_fragment' => 'resources.partner-companies.', 'group' => 'Aliados Comerciales'],
            'products' => ['label' => 'Productos', 'route_name_fragment' => 'resources.products.', 'group' => 'Inventario'],
            'product_categories' => ['label' => 'Categorias de productos', 'route_name_fragment' => 'resources.product-categories.', 'group' => 'Inventario'],
            'inventories' => ['label' => 'Inventario', 'route_name_fragment' => 'resources.inventories.', 'group' => 'Inventario'],
            'inventory_movements' => ['label' => 'Movimientos de inventario', 'route_name_fragment' => 'resources.inventory-movements.', 'group' => 'Inventario'],
            'inventory_adjustments' => ['label' => 'Ajustes de inventario', 'route_name_fragment' => 'resources.inventory-adjustments.', 'group' => 'Inventario'],
            'product_transfers' => ['label' => 'Traslados de productos', 'route_name_fragment' => 'resources.product-transfers.', 'group' => 'Inventario'],
            'suppliers' => ['label' => 'Proveedores', 'route_name_fragment' => 'resources.suppliers.', 'group' => 'Inventario'],
            'branches' => ['label' => 'Sucursales', 'route_name_fragment' => 'resources.branches.', 'group' => 'Configuración'],
            'roles' => ['label' => 'Roles', 'route_name_fragment' => 'resources.roles.', 'group' => 'Configuración'],
            'users' => ['label' => 'Usuarios', 'route_name_fragment' => 'resources.users.', 'group' => 'Configuración'],
            'audit_logs' => ['label' => 'Auditoría y trazas', 'route_name_fragment' => 'resources.audit-logs.', 'group' => 'Configuración'],
            'api_clients' => ['label' => 'Clientes API', 'route_name_fragment' => 'resources.api-clients.', 'group' => 'Configuración'],
            'financial_settings' => ['label' => 'Administración financiera', 'route_name_fragment' => 'pages.manage-financial-settings', 'group' => 'Configuración'],
            'bdv_playground' => ['label' => 'BDV — APIs (pruebas)', 'route_name_fragment' => 'pages.bdv-conciliation-playground', 'group' => 'Configuración'],
            'marketing_hub' => ['label' => 'Panel de marketing', 'route_name_fragment' => 'pages.marketing-hub-page', 'group' => 'Marketing'],
            'marketing_campaigns' => ['label' => 'Campañas de marketing', 'route_name_fragment' => 'resources.marketing.campaigns.marketing-campaigns.', 'group' => 'Marketing'],
            'marketing_broadcasts' => ['label' => 'Broadcasts de marketing', 'route_name_fragment' => 'resources.marketing.broadcasts.marketing-broadcasts.', 'group' => 'Marketing'],
            'marketing_contents' => ['label' => 'Contenidos de marketing', 'route_name_fragment' => 'resources.marketing.contents.marketing-contents.', 'group' => 'Marketing'],
            'marketing_coupons' => ['label' => 'Cupones de marketing', 'route_name_fragment' => 'resources.marketing.coupons.marketing-coupons.', 'group' => 'Marketing'],
            'marketing_segments' => ['label' => 'Segmentos de marketing', 'route_name_fragment' => 'resources.marketing.segments.marketing-segments.', 'group' => 'Marketing'],
            'marketing_utm_links' => ['label' => 'UTM links de marketing', 'route_name_fragment' => 'resources.marketing.utm-links.marketing-utm-links.', 'group' => 'Marketing'],
            'system_reports' => ['label' => 'Reportes del sistema', 'route_name_fragment' => 'pages.reportes-del-sistema', 'group' => 'Reportes'],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        $grouped = [];
        foreach (self::items() as $key => $item) {
            $grouped[$item['group']][$key] = $item['label'];
        }

        return $grouped;
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return array_keys(self::groupedOptions());
    }

    /**
     * @return array<string, string>
     */
    public static function optionsForGroup(string $group): array
    {
        return self::groupedOptions()[$group] ?? [];
    }

    /**
     * @return array<string, string>
     */
    public static function flatOptions(): array
    {
        $flat = [];

        foreach (self::items() as $key => $item) {
            $flat[$key] = $item['group'].' · '.$item['label'];
        }

        return $flat;
    }

    /**
     * @return list<string>
     */
    public static function allKeys(): array
    {
        return array_keys(self::items());
    }

    public static function resolveMenuKeyByRouteName(?string $routeName): ?string
    {
        if (! is_string($routeName) || $routeName === '') {
            return null;
        }

        if (str_contains($routeName, 'pages.farmaadmin-dashboard') || str_contains($routeName, 'pages.dashboard')) {
            return 'dashboard';
        }

        foreach (self::items() as $key => $item) {
            $fragment = (string) $item['route_name_fragment'];
            $baseFragment = rtrim($fragment, '.');

            if (str_contains($routeName, $fragment) || ($baseFragment !== '' && str_contains($routeName, $baseFragment))) {
                return $key;
            }
        }

        return null;
    }
}
