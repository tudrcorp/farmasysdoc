<?php

namespace App\Filament\Pages\Reports;

use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Centro de descargas CSV para administración y gerencia.
 */
final class SystemReportsHubPage extends Page
{
    /** URL del panel: `/farmaadmin/reportes-del-sistema`. */
    protected static ?string $slug = 'reportes-del-sistema';

    protected static ?string $navigationLabel = 'Reportes del sistema';

    protected static string|UnitEnum|null $navigationGroup = 'reports';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBarSquare;

    protected string $view = 'filament.pages.reports.system-reports-hub';

    public function getHeading(): string|Htmlable
    {
        return 'Reportes del sistema';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User) {
            return false;
        }
        if ($user->isAdministrator()) {
            return true;
        }

        return $user->hasGerenciaRole() && $user->canAccessFarmaadminMenuKey('system_reports');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $sections = self::reportSectionsMeta();
        $reportCount = 0;
        foreach ($sections as $section) {
            $reportCount += count($section['items']);
        }

        return [
            'defaultDesde' => now()->subDays(90)->toDateString(),
            'defaultHasta' => now()->toDateString(),
            'sections' => $sections,
            'reportCount' => $reportCount,
            'sectionCount' => count($sections),
            'datePresets' => self::datePresets(),
        ];
    }

    /**
     * @return list<array{id: string, label: string, desde: string, hasta: string}>
     */
    public static function datePresets(): array
    {
        $today = now();
        $monthStart = $today->copy()->startOfMonth();
        $prevMonthStart = $monthStart->copy()->subMonth();
        $prevMonthEnd = $monthStart->copy()->subDay();

        return [
            [
                'id' => '7d',
                'label' => '7 días',
                'desde' => $today->copy()->subDays(6)->toDateString(),
                'hasta' => $today->toDateString(),
            ],
            [
                'id' => '30d',
                'label' => '30 días',
                'desde' => $today->copy()->subDays(29)->toDateString(),
                'hasta' => $today->toDateString(),
            ],
            [
                'id' => '90d',
                'label' => '90 días',
                'desde' => $today->copy()->subDays(89)->toDateString(),
                'hasta' => $today->toDateString(),
            ],
            [
                'id' => 'month',
                'label' => 'Mes actual',
                'desde' => $monthStart->toDateString(),
                'hasta' => $today->toDateString(),
            ],
            [
                'id' => 'prev_month',
                'label' => 'Mes anterior',
                'desde' => $prevMonthStart->toDateString(),
                'hasta' => $prevMonthEnd->toDateString(),
            ],
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     icon: string,
     *     accent: string,
     *     items: list<array{
     *         slug: string,
     *         title: string,
     *         hint: string,
     *         dates: bool,
     *         extra_fields?: list<array{name: string, label: string, type: string, options?: array<string, string>}>
     *     }>
     * }>
     */
    public static function reportSectionsMeta(): array
    {
        return [
            [
                'key' => 'ventas',
                'icon' => 'chart-bar',
                'accent' => 'teal',
                'title' => 'Ventas y caja',
                'description' => 'Movimientos de venta según la fecha registrada en cada venta.',
                'items' => [
                    ['slug' => 'ventas', 'title' => 'Ventas detalladas', 'hint' => 'Una fila por venta con totales y pagos.', 'dates' => true],
                    [
                        'slug' => 'ventas-global-sucursal',
                        'title' => 'Ventas global y por sucursal',
                        'hint' => 'Resumen consolidado y desglose por sucursal (ventas completadas, cobros USD/Bs y ticket promedio).',
                        'dates' => true,
                    ],
                    ['slug' => 'ventas-por-usuario', 'title' => 'Ventas por usuario', 'hint' => 'Agrupado por quien registró la venta.', 'dates' => true],
                    ['slug' => 'ventas-por-sucursal', 'title' => 'Ventas por sucursal', 'hint' => 'Totales por sucursal de la venta.', 'dates' => true],
                ],
            ],
            [
                'key' => 'operaciones',
                'icon' => 'building-storefront',
                'accent' => 'violet',
                'title' => 'Operaciones y aliados',
                'description' => 'Pedidos, órdenes de servicio e ingresos por compañía aliada.',
                'items' => [
                    ['slug' => 'pedidos', 'title' => 'Pedidos', 'hint' => 'Pedidos creados en el rango.', 'dates' => true],
                    ['slug' => 'ordenes-servicio', 'title' => 'Órdenes de servicio', 'hint' => 'Por fecha de orden o de registro.', 'dates' => true],
                    ['slug' => 'companias-aliadas', 'title' => 'Directorio de compañías aliadas', 'hint' => 'Catálogo actual (sin filtro de fechas).', 'dates' => false],
                    ['slug' => 'ingresos-aliados', 'title' => 'Ingresos por compañía aliada', 'hint' => 'Suma de pedidos completados con aliado, en el rango.', 'dates' => true],
                ],
            ],
            [
                'key' => 'traslados',
                'icon' => 'truck',
                'accent' => 'amber',
                'title' => 'Traslados de inventario',
                'description' => 'Movimientos entre sucursales y costos asociados.',
                'items' => [
                    ['slug' => 'traslados-por-usuario', 'title' => 'Traslados por usuario', 'hint' => 'Quién creó el traslado.', 'dates' => true],
                    ['slug' => 'traslados-por-sucursal', 'title' => 'Traslados por sucursal', 'hint' => 'Salidas y entradas por sucursal.', 'dates' => true],
                    ['slug' => 'traslados-costos', 'title' => 'Costos de traslados', 'hint' => 'Detalle con costo total de traslado por movimiento.', 'dates' => true],
                ],
            ],
            [
                'key' => 'clientes',
                'icon' => 'users',
                'accent' => 'sky',
                'title' => 'Clientes y productos',
                'description' => 'Clientes, ranking de compra y rotación de productos.',
                'items' => [
                    ['slug' => 'clientes', 'title' => 'Clientes', 'hint' => 'Directorio de clientes.', 'dates' => false],
                    ['slug' => 'catalogo-productos', 'title' => 'Catálogo de productos', 'hint' => 'Filas de la tabla products (precios de lista, categoría y datos regulatorios).', 'dates' => false],
                    [
                        'slug' => 'top-clientes-sucursal',
                        'title' => 'Top clientes por sucursal',
                        'hint' => 'Ranking de compradores por total en USD en el rango.',
                        'dates' => true,
                        'extra_fields' => [
                            [
                                'name' => 'top',
                                'label' => 'Tamaño del top',
                                'type' => 'select',
                                'options' => ['5' => 'Top 5', '10' => 'Top 10', '20' => 'Top 20'],
                            ],
                        ],
                    ],
                    [
                        'slug' => 'productos-mas-vendidos',
                        'title' => 'Productos más vendidos',
                        'hint' => 'Cantidades y totales de líneas de venta.',
                        'dates' => true,
                        'extra_fields' => [
                            [
                                'name' => 'agrupar',
                                'label' => 'Agrupar por',
                                'type' => 'select',
                                'options' => ['sucursal' => 'Por sucursal', 'categoria' => 'Por categoría'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'inventario',
                'icon' => 'cube',
                'accent' => 'emerald',
                'title' => 'Inventario y tasas',
                'description' => 'Existencias valorizadas y serie BCV oficial en caché.',
                'items' => [
                    [
                        'slug' => 'inventario',
                        'title' => 'Valoración de inventario',
                        'hint' => 'Existencias con costos, precios y valor total (cantidad × unitario). Precios en USD; opción Bs usa tasa BCV del día.',
                        'dates' => false,
                        'extra_fields' => [
                            [
                                'name' => 'moneda',
                                'label' => 'Columnas',
                                'type' => 'select',
                                'options' => ['ambas' => 'USD y Bs (valor total)', 'usd' => 'Solo USD'],
                            ],
                            [
                                'name' => 'vista',
                                'label' => 'Vista',
                                'type' => 'select',
                                'options' => ['detalle' => 'Por producto', 'resumen_sucursal' => 'Resumen por sucursal'],
                            ],
                        ],
                    ],
                    [
                        'slug' => 'inventario-vencimientos',
                        'title' => 'Vencidos y por vencer',
                        'hint' => 'Lotes FEFO con stock: vencidos o próximos a vencer según umbrales del sistema.',
                        'dates' => false,
                        'extra_fields' => [
                            [
                                'name' => 'filtro',
                                'label' => 'Mostrar',
                                'type' => 'select',
                                'options' => [
                                    'vencidos_y_por_vencer' => 'Vencidos y por vencer',
                                    'vencidos' => 'Solo vencidos',
                                    'por_vencer' => 'Solo por vencer',
                                    'todos' => 'Todos con stock',
                                ],
                            ],
                        ],
                    ],
                    ['slug' => 'tasas-bcv', 'title' => 'Tasas BCV (oficial)', 'hint' => 'Filas de la API en caché entre fechas.', 'dates' => true],
                ],
            ],
            [
                'key' => 'compras',
                'icon' => 'banknotes',
                'accent' => 'rose',
                'title' => 'Compras y finanzas',
                'description' => 'Compras con filtro por estado de pago y CxP.',
                'items' => [
                    [
                        'slug' => 'compras',
                        'title' => 'Compras (consolidado)',
                        'hint' => 'Todas, solo CxP «por pagar», o contado / CxP pagadas.',
                        'dates' => false,
                        'extra_fields' => [
                            [
                                'name' => 'filtro',
                                'label' => 'Filtro',
                                'type' => 'select',
                                'options' => [
                                    'todas' => 'Todas las compras',
                                    'cxp_por_pagar' => 'Con CxP en «Por pagar»',
                                    'historico_pagado' => 'Pagadas (contado o CxP saldada)',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
