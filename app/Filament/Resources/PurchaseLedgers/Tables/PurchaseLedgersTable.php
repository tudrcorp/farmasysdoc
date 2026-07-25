<?php

namespace App\Filament\Resources\PurchaseLedgers\Tables;

use App\Enums\PurchaseLedgerDocumentType;
use App\Models\PurchaseLedger;
use App\Support\Fiscal\VenezuelanRifFormatter;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseLedgersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['purchase', 'purchaseBook']))
            ->emptyStateHeading('Aún no hay registros en el Libro de Compras')
            ->emptyStateDescription('Al guardar una compra se generan automáticamente la factura y, si aplica, el comprobante de retención.')
            ->emptyStateIcon(Heroicon::BookOpen)
            ->persistFiltersInSession()
            ->filtersFormColumns(2)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->defaultSort('operation_number')
            ->columns([
                TextColumn::make('operation_number')
                    ->label('Nº operaciones')
                    ->sortable()
                    ->alignCenter()
                    ->weight('semibold'),
                TextColumn::make('document_type')
                    ->label('Tipo de documento')
                    ->formatStateUsing(fn (?PurchaseLedgerDocumentType $state): string => $state?->label() ?? '—')
                    ->badge()
                    ->color(fn (?PurchaseLedgerDocumentType $state): string => match ($state) {
                        PurchaseLedgerDocumentType::Factura => 'info',
                        PurchaseLedgerDocumentType::ComprobanteDeRetencion => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('document_number')
                    ->label('Factura / documento')
                    ->searchable()
                    ->description(fn (PurchaseLedger $record): ?string => $record->invoice_date?->format('d/m/Y')),
                TextColumn::make('control_number')
                    ->label('Nº de control')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('supplier_name')
                    ->label('Nombre / razón social')
                    ->searchable()
                    ->wrap()
                    ->icon(Heroicon::Truck),
                TextColumn::make('supplier_tax_id')
                    ->label('RIF / cédula')
                    ->searchable()
                    ->formatStateUsing(function (?string $state): string {
                        $formatted = VenezuelanRifFormatter::format($state);

                        return $formatted !== '' ? $formatted : '—';
                    }),
                TextColumn::make('taxpayer_type')
                    ->label('Tipo contribuyente')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_with_vat_and_exempt_ves')
                    ->label('Total grabadas + exentas')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                TextColumn::make('exempt_amount_ves')
                    ->label('Exentas / exoneradas')
                    ->alignEnd()
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state): string => $state !== null ? self::formatBs((float) $state) : '—')
                    ->toggleable(),
                TextColumn::make('export_amount_ves')
                    ->label('Monto exportación')
                    ->alignEnd()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('taxable_base_ves')
                    ->label('Base imponible')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $state !== null ? self::formatBs((float) $state) : '—'),
                TextColumn::make('tax_caused_ves')
                    ->label('IVA')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $state !== null ? self::formatBs((float) $state) : '—'),
                TextColumn::make('taxable_base_reduced_ves')
                    ->label('Base imponible (reducida)')
                    ->alignEnd()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax_reduced_ves')
                    ->label('IVA (reducida)')
                    ->alignEnd()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vat_rate_percent')
                    ->label('Alícuota')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? number_format((float) $state, 0, ',', '.').'%'
                        : '—'),
                TextColumn::make('retention_voucher_issued_at')
                    ->label('Emisión comprobante retención')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('retention_voucher_number')
                    ->label('Nº comprobante retención')
                    ->placeholder('—')
                    ->copyable()
                    ->sortable(),
                TextColumn::make('retention_amount_ves')
                    ->label('Monto retención')
                    ->alignEnd()
                    ->placeholder('—')
                    ->weight('semibold')
                    ->color(fn ($state): string => (float) ($state ?? 0) > 0 ? 'warning' : 'gray')
                    ->formatStateUsing(fn ($state): string => $state !== null ? self::formatBs((float) $state) : '—'),
                TextColumn::make('tax_period')
                    ->label('Periodo')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tax_period')
                    ->label('Periodo')
                    ->options(fn (): array => PurchaseLedger::query()
                        ->select('tax_period')
                        ->distinct()
                        ->orderByDesc('tax_period')
                        ->pluck('tax_period', 'tax_period')
                        ->all())
                    ->default(now()->format('Y/m'))
                    ->searchable()
                    ->native(false),
                SelectFilter::make('document_type')
                    ->label('Tipo de documento')
                    ->options(PurchaseLedgerDocumentType::options()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    private static function formatBs(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' Bs';
    }
}
