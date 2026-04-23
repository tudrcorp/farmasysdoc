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
        return 'Descargas en CSV (UTF-8, separador punto y coma). Elija rango de fechas cuando aplique; los datos respetan el alcance de sucursal de su usuario.';
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
        $desde = now()->subDays(90)->toDateString();
        $hasta = now()->toDateString();

        return [
            'defaultDesde' => $desde,
            'defaultHasta' => $hasta,
            'sections' => self::reportSectionsMeta(),
        ];
    }

    /**
     * @return list<array{title: string, description: string, items: list<array{slug: string, title: string, hint: string, dates: bool, extra_fields?: list<array{name: string, label: string, type: string, options?: array<string, string>}>}>}>
     */
    public static function reportSectionsMeta(): array
    {
        return [
            [
                'title' => 'Ventas y caja',
                'description' => 'Movimientos de venta según la fecha registrada en cada venta.',
                'items' => [
                    ['slug' => 'ventas', 'title' => 'Ventas detalladas', 'hint' => 'Una fila por venta con totales y pagos.', 'dates' => true],
                    ['slug' => 'ventas-por-usuario', 'title' => 'Ventas por usuario', 'hint' => 'Agrupado por quien registró la venta.', 'dates' => true],
                    ['slug' => 'ventas-por-sucursal', 'title' => 'Ventas por sucursal', 'hint' => 'Totales por sucursal de la venta.', 'dates' => true],
                ],
            ],
            [
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
                'title' => 'Traslados de inventario',
                'description' => 'Movimientos entre sucursales y costos asociados.',
                'items' => [
                    ['slug' => 'traslados-por-usuario', 'title' => 'Traslados por usuario', 'hint' => 'Quién creó el traslado.', 'dates' => true],
                    ['slug' => 'traslados-por-sucursal', 'title' => 'Traslados por sucursal', 'hint' => 'Salidas y entradas por sucursal.', 'dates' => true],
                    ['slug' => 'traslados-costos', 'title' => 'Costos de traslados', 'hint' => 'Detalle con costo total de traslado por movimiento.', 'dates' => true],
                ],
            ],
            [
                'title' => 'Clientes y productos',
                'description' => 'Clientes, ranking de compra y rotación de productos.',
                'items' => [
                    ['slug' => 'clientes', 'title' => 'Clientes', 'hint' => 'Directorio de clientes.', 'dates' => false],
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
                'title' => 'Inventario y tasas',
                'description' => 'Existencias valorizadas y serie BCV oficial en caché.',
                'items' => [
                    [
                        'slug' => 'inventario',
                        'title' => 'Inventario valorizado',
                        'hint' => 'Costos y precios según columnas del inventario.',
                        'dates' => false,
                        'extra_fields' => [
                            [
                                'name' => 'moneda',
                                'label' => 'Columnas',
                                'type' => 'select',
                                'options' => ['ambas' => 'USD y Bs', 'usd' => 'Solo USD', 'ves' => 'Solo columnas Bs'],
                            ],
                        ],
                    ],
                    ['slug' => 'tasas-bcv', 'title' => 'Tasas BCV (oficial)', 'hint' => 'Filas de la API en caché entre fechas.', 'dates' => true],
                ],
            ],
            [
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
