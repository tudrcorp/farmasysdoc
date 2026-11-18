<?php

namespace App\Filament\Resources\PartnerAgreementSales\Tables;

use App\Enums\PartnerAgreementSaleStatus;
use App\Models\PartnerAgreementSale;
use App\Models\PartnerCompany;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PartnerAgreementSalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['partnerCompany:id,legal_name,trade_name', 'branch:id,name', 'plan:id,name']))
            ->columns([
                TextColumn::make('number')
                    ->label('Nota')
                    ->fontFamily('mono')
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sold_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y h:i a')
                    ->sortable(),
                TextColumn::make('client_name')
                    ->label('Afiliado')
                    ->description(fn (PartnerAgreementSale $record): ?string => $record->client_document ? 'C.I. '.$record->client_document : null)
                    ->searchable(['client_name', 'client_document']),
                TextColumn::make('partnerCompany.legal_name')
                    ->label('Convenio')
                    ->description(fn (PartnerAgreementSale $record): ?string => $record->plan?->name)
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->toggleable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('USD')
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (PartnerAgreementSaleStatus $state): string => $state->label())
                    ->color(fn (PartnerAgreementSaleStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('partner_company_id')
                    ->label('Convenio')
                    ->options(fn (): array => PartnerCompany::query()->orderBy('legal_name')->pluck('legal_name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->options(fn (): array => BranchAuthScope::branchOptions()),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(PartnerAgreementSaleStatus::options()),
                Filter::make('sold_at')
                    ->label('Fecha')
                    ->schema([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->where('sold_at', '>=', $date.' 00:00:00'))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->where('sold_at', '<=', $date.' 23:59:59'))),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
            ])
            ->defaultSort('sold_at', 'desc')
            ->emptyStateHeading('Sin ventas por convenio')
            ->emptyStateDescription('Se registran desde la caja con «Cobrar al convenio» cuando el cliente es afiliado de un plan Flexible.');
    }
}
