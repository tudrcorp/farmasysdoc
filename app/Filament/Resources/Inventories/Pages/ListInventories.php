<?php

namespace App\Filament\Resources\Inventories\Pages;

use App\Filament\Resources\Inventories\InventoryResource;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListInventories extends ListRecords
{
    private const MAX_PDF_DETAIL_ROWS = 500;

    protected static string $resource = InventoryResource::class;

    protected static ?string $title = 'Listado de Inventarios';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('inventory_report')
                ->label('Generar reporte')
                ->icon(Heroicon::DocumentArrowDown)
                ->color('gray')
                ->modalHeading('Generar reporte de inventario')
                ->modalDescription('Aplica filtros por fecha, categoría, precio, stock y columnas para descargar un CSV personalizado.')
                ->modalSubmitActionLabel('Descargar reporte CSV')
                ->schema([
                    Grid::make([
                        'default' => 1,
                        'lg' => 2,
                    ])
                        ->schema([
                            DatePicker::make('updated_from')
                                ->label('Actualizado desde')
                                ->native(false),
                            DatePicker::make('updated_until')
                                ->label('Actualizado hasta')
                                ->native(false),
                            Select::make('branch_ids')
                                ->label('Sucursales')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->options(fn (): array => BranchAuthScope::applyToBranchFormSelect(
                                    Branch::query()->where('is_active', true)->orderBy('name')
                                )->pluck('name', 'id')->toArray()),
                            Select::make('category_ids')
                                ->label('Categorías de inventario')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->options(fn (): array => ProductCategory::query()
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray()),
                            Select::make('price_field')
                                ->label('Campo de precio')
                                ->options([
                                    'cost_price' => 'Costo',
                                    'final_price_without_vat' => 'Precio final sin IVA',
                                    'final_price_with_vat' => 'Precio final con IVA',
                                ])
                                ->default('final_price_with_vat')
                                ->required(),
                            Select::make('stock_state')
                                ->label('Estado del stock')
                                ->options([
                                    'all' => 'Todos',
                                    'low' => 'Bajo mínimo / reorden',
                                    'out' => 'Agotado (cantidad <= 0)',
                                    'available' => 'Con stock disponible',
                                ])
                                ->default('all')
                                ->required(),
                            TextInput::make('min_price')
                                ->label('Precio mínimo')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.00000001),
                            TextInput::make('max_price')
                                ->label('Precio máximo')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.00000001),
                            TextInput::make('search')
                                ->label('Buscar (producto/código/ubicación)')
                                ->placeholder('Ej. acetaminofén, 759100, estante A1')
                                ->columnSpanFull(),
                            Select::make('sort_by')
                                ->label('Ordenar por')
                                ->options([
                                    'updated_at' => 'Fecha de actualización',
                                    'quantity' => 'Cantidad',
                                    'cost_price' => 'Costo',
                                    'final_price_with_vat' => 'Precio final con IVA',
                                ])
                                ->default('updated_at')
                                ->required(),
                            Select::make('sort_direction')
                                ->label('Dirección de orden')
                                ->options([
                                    'desc' => 'Descendente',
                                    'asc' => 'Ascendente',
                                ])
                                ->default('desc')
                                ->required(),
                            Select::make('output_format')
                                ->label('Formato de salida')
                                ->options([
                                    'csv' => 'CSV (Excel)',
                                    'pdf' => 'PDF (con resumen)',
                                ])
                                ->default('csv')
                                ->required()
                                ->helperText('PDF recomendado para reportes ejecutivos. Si el detalle es muy grande, se incluirán las primeras '.self::MAX_PDF_DETAIL_ROWS.' filas y podrás usar CSV para el total.')
                                ->columnSpanFull(),
                        ]),
                    CheckboxList::make('columns')
                        ->label('Columnas del reporte')
                        ->options(self::reportColumnLabels())
                        ->default(array_keys(self::reportColumnLabels()))
                        ->required()
                        ->bulkToggleable()
                        ->columns(2)
                        ->helperText('Selecciona solo las columnas que necesitas en este reporte.'),
                ])
                ->action(fn (array $data): Response|StreamedResponse => self::downloadInventoryReport($data))
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--gray',
                ]),
            CreateAction::make()
                ->label('Agregar Producto a Inventario')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function reportColumnLabels(): array
    {
        return [
            'branch' => 'Sucursal',
            'category' => 'Categoría',
            'product' => 'Producto',
            'barcode' => 'Código',
            'active_ingredient' => 'Principio activo',
            'quantity' => 'Existencias',
            'available_quantity' => 'Disponible',
            'cost_price' => 'Costo',
            'final_price_without_vat' => 'Precio final sin IVA',
            'final_price_with_vat' => 'Precio final con IVA',
            'storage_location' => 'Ubicación',
            'updated_at' => 'Actualizado',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function downloadInventoryReport(array $data): Response|StreamedResponse
    {
        $format = ($data['output_format'] ?? 'csv') === 'pdf' ? 'pdf' : 'csv';

        return $format === 'pdf'
            ? self::streamInventoryReportPdf($data)
            : self::streamInventoryReportCsv($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function streamInventoryReportCsv(array $data): StreamedResponse
    {
        [$columns, $query] = self::buildReportContext($data);

        $fileName = 'reporte-inventario-'.now()->format('Y-m-d-H-i-s').'.csv';

        return response()->streamDownload(function () use ($columns, $query): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, array_map(fn (string $column): string => self::reportColumnLabels()[$column], $columns));

            foreach ($query->cursor() as $inventory) {
                /** @var Inventory $inventory */
                $row = array_map(
                    fn (string $column): string => self::formatReportValue($inventory, $column),
                    $columns
                );

                fputcsv($stream, $row);
            }

            fclose($stream);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function streamInventoryReportPdf(array $data): StreamedResponse
    {
        [$columns, $query] = self::buildReportContext($data);

        $totalItems = (clone $query)->count();
        $records = (clone $query)
            ->limit(self::MAX_PDF_DETAIL_ROWS)
            ->get();

        $rows = $records->map(function (Inventory $inventory) use ($columns): array {
            $row = [];

            foreach ($columns as $column) {
                $row[$column] = self::sanitizeUtf8(self::formatReportValue($inventory, $column));
            }

            return $row;
        })->all();

        $availableExpression = 'CASE
            WHEN allow_negative_stock = 0 THEN GREATEST(quantity - reserved_quantity, 0)
            ELSE (quantity - reserved_quantity)
        END';

        $summary = [
            'total_items' => $totalItems,
            'total_quantity' => self::formatNumberForReport((float) ((clone $query)->sum('quantity')), 3),
            'total_available' => self::formatNumberForReport((float) ((clone $query)->sum(DB::raw($availableExpression))), 3),
            'avg_cost_price' => self::formatNumberForReport((float) ((clone $query)->avg('cost_price'))),
            'avg_final_price' => self::formatNumberForReport((float) ((clone $query)->avg('final_price_with_vat'))),
            'low_stock_count' => (clone $query)->whereRaw('quantity <= COALESCE(reorder_point, minimum_stock, 0)')->count(),
        ];

        $authUser = request()->user() ?? Auth::user();

        $generatedBy = $authUser instanceof User
            ? ($authUser->email ?? $authUser->name ?? 'usuario')
            : 'sistema';

        $logoPath = public_path('images/logos/farmadoc-ligth.png');
        $pdfLogoDataUri = is_readable($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $filename = 'reporte-inventario-'.now()->format('Y-m-d-H-i-s').'.pdf';

        $pdf = Pdf::loadView('pdf.inventory-report', [
            'columns' => $columns,
            'column_labels' => self::reportColumnLabels(),
            'rows' => $rows,
            'summary' => $summary,
            'filters' => array_map(fn (string $value): string => self::sanitizeUtf8($value), self::formatAppliedFiltersSummary($data)),
            'pdf_detail_limit' => self::MAX_PDF_DETAIL_ROWS,
            'pdf_is_truncated' => $totalItems > self::MAX_PDF_DETAIL_ROWS,
            'generated_at' => now()->format('d/m/Y H:i'),
            'generated_by' => self::sanitizeUtf8($generatedBy),
            'pdf_logo_data_uri' => $pdfLogoDataUri,
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            function () use ($pdf): void {
                echo $pdf->output();
            },
            $filename,
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: list<string>, 1: Builder<Inventory>}
     */
    private static function buildReportContext(array $data): array
    {
        $columns = is_array($data['columns'] ?? null) && $data['columns'] !== []
            ? array_values(array_intersect(array_keys(self::reportColumnLabels()), $data['columns']))
            : array_keys(self::reportColumnLabels());

        $query = Inventory::query()
            ->with(['branch', 'product', 'productCategory']);

        BranchAuthScope::apply($query);
        self::applyReportFilters($query, $data);

        $sortBy = in_array($data['sort_by'] ?? 'updated_at', ['updated_at', 'quantity', 'cost_price', 'final_price_with_vat'], true)
            ? (string) $data['sort_by']
            : 'updated_at';
        $sortDirection = ($data['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortDirection)->orderBy('id');

        return [$columns, $query];
    }

    /**
     * @param  Builder<Inventory>  $query
     * @param  array<string, mixed>  $data
     */
    private static function applyReportFilters(Builder $query, array $data): void
    {
        if (filled($data['updated_from'] ?? null)) {
            $query->whereDate('updated_at', '>=', (string) $data['updated_from']);
        }

        if (filled($data['updated_until'] ?? null)) {
            $query->whereDate('updated_at', '<=', (string) $data['updated_until']);
        }

        if (is_array($data['branch_ids'] ?? null) && $data['branch_ids'] !== []) {
            $query->whereIn('branch_id', $data['branch_ids']);
        }

        if (is_array($data['category_ids'] ?? null) && $data['category_ids'] !== []) {
            $query->whereIn('product_category_id', $data['category_ids']);
        }

        $priceField = in_array($data['price_field'] ?? 'final_price_with_vat', ['cost_price', 'final_price_without_vat', 'final_price_with_vat'], true)
            ? (string) $data['price_field']
            : 'final_price_with_vat';

        if (filled($data['min_price'] ?? null)) {
            $query->where($priceField, '>=', (float) $data['min_price']);
        }

        if (filled($data['max_price'] ?? null)) {
            $query->where($priceField, '<=', (float) $data['max_price']);
        }

        $stockState = (string) ($data['stock_state'] ?? 'all');

        if ($stockState === 'low') {
            $query->whereRaw('quantity <= COALESCE(reorder_point, minimum_stock, 0)');
        } elseif ($stockState === 'out') {
            $query->where('quantity', '<=', 0);
        } elseif ($stockState === 'available') {
            $query->where('quantity', '>', 0);
        }

        $search = trim((string) ($data['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('storage_location', 'like', '%'.$search.'%')
                    ->orWhereHas('product', function (Builder $productQuery) use ($search): void {
                        $productQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('barcode', 'like', '%'.$search.'%');
                    });
            });
        }
    }

    private static function formatReportValue(Inventory $inventory, string $column): string
    {
        return match ($column) {
            'branch' => (string) ($inventory->branch?->name ?? ''),
            'category' => (string) ($inventory->productCategory?->name ?? ''),
            'product' => (string) ($inventory->product?->name ?? ''),
            'barcode' => (string) ($inventory->product?->barcode ?? '000'.$inventory->product_id),
            'active_ingredient' => is_array($inventory->active_ingredient)
                ? implode(', ', array_values(array_filter($inventory->active_ingredient, fn (mixed $value): bool => is_string($value) && filled($value))))
                : '',
            'quantity' => self::formatNumberForReport((float) $inventory->quantity, 3),
            'available_quantity' => self::formatNumberForReport((float) $inventory->available_quantity, 3),
            'cost_price' => self::formatNumberForReport((float) $inventory->cost_price),
            'final_price_without_vat' => self::formatNumberForReport((float) $inventory->final_price_without_vat),
            'final_price_with_vat' => self::formatNumberForReport((float) $inventory->final_price_with_vat),
            'storage_location' => (string) ($inventory->storage_location ?? ''),
            'updated_at' => $inventory->updated_at?->format('Y-m-d H:i:s') ?? '',
            default => '',
        };
    }

    private static function formatNumberForReport(float $value, int $decimals = 2): string
    {
        $formatted = number_format($value, $decimals, '.', ',');

        if ($decimals > 0 && str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted;
    }

    private static function sanitizeUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function formatAppliedFiltersSummary(array $data): array
    {
        $summary = [];

        if (filled($data['updated_from'] ?? null) || filled($data['updated_until'] ?? null)) {
            $summary[] = 'Actualización: '.($data['updated_from'] ?? 'inicio').' a '.($data['updated_until'] ?? 'hoy');
        }

        if (is_array($data['branch_ids'] ?? null) && $data['branch_ids'] !== []) {
            $summary[] = 'Sucursales seleccionadas: '.count($data['branch_ids']);
        }

        if (is_array($data['category_ids'] ?? null) && $data['category_ids'] !== []) {
            $summary[] = 'Categorías seleccionadas: '.count($data['category_ids']);
        }

        if (filled($data['min_price'] ?? null) || filled($data['max_price'] ?? null)) {
            $summary[] = 'Rango de precio: '.($data['min_price'] ?? '0').' a '.($data['max_price'] ?? 'sin tope');
        }

        if (($data['stock_state'] ?? 'all') !== 'all') {
            $summary[] = 'Estado de stock: '.(string) $data['stock_state'];
        }

        if (filled($data['search'] ?? null)) {
            $summary[] = 'Búsqueda: "'.trim((string) $data['search']).'"';
        }

        $summary[] = 'Orden: '.($data['sort_by'] ?? 'updated_at').' '.strtoupper((string) ($data['sort_direction'] ?? 'desc'));

        return $summary;
    }
}
