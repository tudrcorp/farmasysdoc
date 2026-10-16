<?php

namespace App\Filament\Resources\SalesVatRetentions\Tables;

use App\Filament\Resources\SalesVatRetentions\SalesVatRetentionResource;
use App\Models\SalesVatRetention;
use App\Support\Fiscal\SalesBookPeriod;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesVatRetentionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('received_on', 'desc')
            ->columns([
                TextColumn::make('received_on')->label('Recibido')->date('d/m/Y')->sortable(),
                TextColumn::make('branch.name')->label('Sucursal')->sortable(),
                TextColumn::make('voucher_number')->label('Comprobante')->searchable()->copyable(),
                TextColumn::make('voucher_issued_on')->label('Emitido')->date('d/m/Y')->toggleable(),
                TextColumn::make('customer_name')->label('Cliente')->searchable()->limit(30),
                TextColumn::make('customer_rif')->label('RIF')->searchable(),
                TextColumn::make('invoice_number')->label('Factura')->searchable(),
                TextColumn::make('invoice_tax_ves')->label('IVA factura')->numeric(2)->toggleable(),
                TextColumn::make('retention_percent')->label('%')->suffix('%')->numeric(0),
                TextColumn::make('retained_amount_ves')->label('Retenido (Bs)')->numeric(2)->sortable(),
                IconColumn::make('closed')
                    ->label('Libro cerrado')
                    ->state(fn (SalesVatRetention $record): bool => SalesVatRetentionResource::isInClosedPeriod($record))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('branch_id')->label('Sucursal')->relationship('branch', 'name'),
                SelectFilter::make('period')
                    ->label('Quincena')
                    ->options(SalesBookPeriod::recentOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        $period = SalesBookPeriod::fromKey((string) $data['value']);

                        return $query->whereBetween('received_on', [$period->start()->toDateString(), $period->end()->toDateString()]);
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
