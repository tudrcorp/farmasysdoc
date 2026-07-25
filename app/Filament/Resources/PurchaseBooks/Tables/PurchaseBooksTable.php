<?php

namespace App\Filament\Resources\PurchaseBooks\Tables;

use App\Filament\Resources\PurchaseBooks\PurchaseBookResource;
use App\Models\PurchaseBook;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

class PurchaseBooksTable
{
    /**
     * Cache de conteos por grupo dentro del mismo request (evita N+1 al renderizar encabezados).
     *
     * @var array<string, int>
     */
    private static array $groupInvoiceCounts = [];

    public static function configure(Table $table): Table
    {
        $supplierAndDateGroup = Group::make('supplier_and_invoice_date')
            ->label('Proveedor y fecha')
            ->titlePrefixedWithLabel(false)
            ->collapsible()
            ->getKeyFromRecordUsing(fn (PurchaseBook $record): string => self::groupKeyForRecord($record))
            ->getTitleFromRecordUsing(function (PurchaseBook $record): string {
                $date = $record->invoice_date?->format('d/m/Y') ?? 'Sin fecha';
                $count = self::invoiceCountForGroup($record);
                $countLabel = $count === 1 ? '1 factura' : $count.' facturas';

                return $record->supplier_name.' · '.$date.' · '.$countLabel;
            })
            ->getDescriptionFromRecordUsing(fn (PurchaseBook $record): Htmlable => self::groupDescriptionWithPrintAction($record))
            ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query
                ->orderBy('supplier_name', $direction)
                ->orderBy('invoice_date', $direction)
                ->orderBy('voucher_number', $direction));

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['purchase']))
            ->defaultSort('supplier_name')
            ->defaultGroup($supplierAndDateGroup)
            ->groups([
                $supplierAndDateGroup,
                Group::make('supplier_name')
                    ->label('Proveedor')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
                    ->getDescriptionFromRecordUsing(fn (PurchaseBook $record): string => 'RIF: '.($record->supplier_rif ?: '—')),
                Group::make('invoice_date')
                    ->label('Fecha de factura')
                    ->date()
                    ->collapsible(),
            ])
            ->groupingSettingsHidden()
            ->collapsedGroupsByDefault()
            ->striped()
            ->searchPlaceholder('Comprobante, proveedor, RIF o factura…')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->extremePaginationLinks()
            ->deferLoading()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->filtersFormColumns(3)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateHeading('Aún no hay retenciones registradas')
            ->emptyStateDescription('Al guardar una compra con IVA se crea automáticamente una fila aquí. Las compras exentas (sin impuesto) no generan registro.')
            ->emptyStateIcon(Heroicon::BookOpen)
            ->recordUrl(fn (PurchaseBook $record): string => PurchaseBookResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->columns([
                TextColumn::make('voucher_number')
                    ->label('Comprobante')
                    ->sortable()
                    ->searchable()
                    ->weight('semibold')
                    ->fontFamily('mono')
                    ->icon(Heroicon::Hashtag)
                    ->iconColor('primary')
                    ->copyable()
                    ->copyMessage('Comprobante copiado')
                    ->description(fn (PurchaseBook $record): ?string => filled($record->invoice_control_number)
                        ? 'Control: '.$record->invoice_control_number
                        : null)
                    ->tooltip('Número de comprobante de retención'),
                TextColumn::make('tax_period')
                    ->label('Periodo')
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),
                TextColumn::make('operation_number')
                    ->label('Operación')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray')
                    ->tooltip('Número de operaciones del periodo'),
                TextColumn::make('invoice_number')
                    ->label('Factura')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (PurchaseBook $record): string => $record->invoice_date?->format('d/m/Y') ?? 'Sin fecha')
                    ->icon(Heroicon::DocumentText)
                    ->iconColor('gray'),
                TextColumn::make('seniat_retention_percent')
                    ->label('% ret.')
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state === null => 'gray',
                        (float) $state <= 0 => 'success',
                        (float) $state >= 100 => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? number_format((float) $state, 0, ',', '.').'%'
                        : '—')
                    ->tooltip('Porcentaje de retención SENIAT del proveedor'),
                TextColumn::make('taxable_base_ves')
                    ->label('Base imponible')
                    ->alignEnd()
                    ->sortable()
                    ->color('gray')
                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                TextColumn::make('tax_caused_ves')
                    ->label('IVA causado')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state))
                    ->description(fn (PurchaseBook $record): string => number_format((float) $record->vat_rate_percent, 0).'%'),
                TextColumn::make('tax_retained_ves')
                    ->label('Retenido')
                    ->alignEnd()
                    ->sortable()
                    ->weight('semibold')
                    ->color(fn ($state): string => (float) $state > 0 ? 'warning' : 'success')
                    ->icon(fn ($state): Heroicon => (float) $state > 0 ? Heroicon::Banknotes : Heroicon::CheckCircle)
                    ->iconColor(fn ($state): string => (float) $state > 0 ? 'warning' : 'success')
                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state))
                    ->tooltip('Impuesto retenido = IVA causado × % SENIAT'),
                TextColumn::make('invoice_total_ves')
                    ->label('Total factura')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                TextColumn::make('supplier_name')
                    ->label('Proveedor')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('supplier_rif')
                    ->label('RIF')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('invoice_date')
                    ->label('Fecha factura')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vat_rate_percent')
                    ->label('Alícuota')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' %'),
                TextColumn::make('purchase.purchase_number')
                    ->label('Orden compra')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('bcv_rate_at_invoice')
                    ->label('Tasa BCV')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? number_format((float) $state, 4, ',', '.')
                        : '—'),
                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tax_period')
                    ->label('Periodo')
                    ->options(fn (): array => PurchaseBook::query()
                        ->select('tax_period')
                        ->distinct()
                        ->orderByDesc('tax_period')
                        ->pluck('tax_period', 'tax_period')
                        ->all())
                    ->default(now()->format('Y/m'))
                    ->searchable()
                    ->native(false)
                    ->indicateUsing(function (array $state): ?string {
                        $value = $state['value'] ?? null;

                        return filled($value) ? 'Periodo: '.$value : null;
                    }),
                SelectFilter::make('supplier_rif')
                    ->label('Proveedor')
                    ->options(fn (): array => PurchaseBook::query()
                        ->select(['supplier_rif', 'supplier_name'])
                        ->distinct()
                        ->orderBy('supplier_name')
                        ->get()
                        ->mapWithKeys(fn (PurchaseBook $book): array => [
                            (string) $book->supplier_rif => $book->supplier_name.' ('.$book->supplier_rif.')',
                        ])
                        ->all())
                    ->searchable()
                    ->native(false)
                    ->indicateUsing(function (array $state): ?string {
                        $value = $state['value'] ?? null;
                        if (blank($value)) {
                            return null;
                        }

                        $name = PurchaseBook::query()->where('supplier_rif', $value)->value('supplier_name');

                        return 'Proveedor: '.($name ?: $value);
                    }),
                Filter::make('invoice_date_between')
                    ->label('Fecha de factura')
                    ->columns(2)
                    ->form([
                        DatePicker::make('invoice_from')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('invoice_until')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['invoice_from'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('invoice_date', '>=', (string) $data['invoice_from']),
                            )
                            ->when(
                                filled($data['invoice_until'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('invoice_date', '<=', (string) $data['invoice_until']),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (filled($data['invoice_from'] ?? null)) {
                            $indicators[] = 'Factura desde: '.$data['invoice_from'];
                        }
                        if (filled($data['invoice_until'] ?? null)) {
                            $indicators[] = 'Factura hasta: '.$data['invoice_until'];
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->icon(Heroicon::Eye)
                    ->color('gray'),
            ]);
    }

    private static function formatBs(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' Bs';
    }

    private static function groupKeyForRecord(PurchaseBook $record): string
    {
        return ($record->supplier_rif ?: $record->supplier_name)
            .'|'.($record->invoice_date?->toDateString() ?? 'sin-fecha');
    }

    private static function groupDescriptionWithPrintAction(PurchaseBook $record): HtmlString
    {
        $rif = filled($record->supplier_rif) ? (string) $record->supplier_rif : 'Sin RIF';
        $pct = $record->seniat_retention_percent !== null
            ? number_format((float) $record->seniat_retention_percent, 0, ',', '.').'% retención SENIAT'
            : 'Retención no indicada';

        $meta = e($rif).' · '.e($pct);

        if (blank($record->supplier_rif) || $record->invoice_date === null) {
            return new HtmlString($meta);
        }

        $url = URL::temporarySignedRoute(
            'purchase-books.retention-voucher-pdf',
            now()->addMinutes(30),
            [
                'supplier_rif' => (string) $record->supplier_rif,
                'invoice_date' => $record->invoice_date->toDateString(),
            ],
        );

        $printerIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="farmadoc-pb-print-icon" aria-hidden="true">'
            .'<path fill-rule="evenodd" d="M5 2.75C5 1.784 5.784 1 6.75 1h6.5c.966 0 1.75.784 1.75 1.75v3.552c.377.046.752.108 1.126.187A2.25 2.25 0 0 1 18 8.698V14.25A2.25 2.25 0 0 1 15.75 16.5h-.75v1.75A1.75 1.75 0 0 1 13.25 20h-6.5A1.75 1.75 0 0 1 5 18.25V16.5h-.75A2.25 2.25 0 0 1 2 14.25V8.698A2.25 2.25 0 0 1 3.874 6.49c.374-.08.75-.141 1.126-.187V2.75ZM6.5 4.302V2.75a.25.25 0 0 1 .25-.25h6.5a.25.25 0 0 1 .25.25v1.552A39.19 39.19 0 0 0 10 5.5c-1.27 0-2.52.07-3.75.202ZM5.75 16.5h8.5v1.75a.25.25 0 0 1-.25.25h-6.5a.25.25 0 0 1-.25-.25V16.5Z" clip-rule="evenodd"/>'
            .'<path d="M10 8a.75.75 0 0 1 .75.75v.01a.75.75 0 0 1-1.5 0V8.75A.75.75 0 0 1 10 8Zm-3.75.75a.75.75 0 0 0-1.5 0v.01a.75.75 0 0 0 1.5 0V8.75Zm7.5 0a.75.75 0 0 0-1.5 0v.01a.75.75 0 0 0 1.5 0V8.75Z"/>'
            .'</svg>';

        $button = '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer"'
            .' class="farmadoc-pb-print-btn"'
            .' x-on:click.stop'
            .' onclick="event.stopPropagation(); event.stopImmediatePropagation();"'
            .' title="Imprimir comprobante de retención IVA del grupo">'
            .$printerIcon
            .'<span>Imprimir PDF</span>'
            .'</a>';

        return new HtmlString(
            '<span class="farmadoc-pb-group-desc">'
            .'<span class="farmadoc-pb-group-meta">'.$meta.'</span>'
            .$button
            .'</span>'
        );
    }

    private static function invoiceCountForGroup(PurchaseBook $record): int
    {
        $key = self::groupKeyForRecord($record);

        if (array_key_exists($key, self::$groupInvoiceCounts)) {
            return self::$groupInvoiceCounts[$key];
        }

        $query = PurchaseBook::query();

        if (filled($record->supplier_rif)) {
            $query->where('supplier_rif', $record->supplier_rif);
        } else {
            $query->where('supplier_name', $record->supplier_name)
                ->where(function (Builder $builder): void {
                    $builder->whereNull('supplier_rif')->orWhere('supplier_rif', '');
                });
        }

        if ($record->invoice_date !== null) {
            $query->whereDate('invoice_date', $record->invoice_date->toDateString());
        } else {
            $query->whereNull('invoice_date');
        }

        return self::$groupInvoiceCounts[$key] = $query->count();
    }
}
