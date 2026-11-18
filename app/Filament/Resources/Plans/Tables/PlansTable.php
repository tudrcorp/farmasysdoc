<?php

namespace App\Filament\Resources\Plans\Tables;

use App\Enums\PlanPremiumMode;
use App\Enums\PlanStatus;
use App\Filament\Resources\Plans\Actions\PlanActions;
use App\Models\PartnerCompany;
use App\Models\Plan;
use App\Models\PlanService;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['ageRanges', 'affiliateBands.prices'])
                ->withCount(['services', 'formularies', 'partnerCompanies']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Plan')
                    ->description(fn (Plan $record): ?string => $record->description)
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (PlanStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (PlanStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('premium_summary')
                    ->label('Prima mensual')
                    ->state(fn (Plan $record): string => $record->premiumSummary())
                    ->description(fn (Plan $record): string => $record->premium_mode->label()),
                TextColumn::make('services_count')
                    ->label('Servicios')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('formularies_count')
                    ->label('Catálogos')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->sortable(),
                TextColumn::make('partner_companies_count')
                    ->label('Aliados')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->label('Vigente hasta')
                    ->date('d/m/Y')
                    ->placeholder('Sin fecha de fin')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(PlanStatus::options()),
                SelectFilter::make('premium_mode')
                    ->label('Modalidad')
                    ->options(PlanPremiumMode::options()),
                SelectFilter::make('partner_company')
                    ->label('Aliado')
                    ->options(fn (): array => PartnerCompany::query()->orderBy('legal_name')->pluck('legal_name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereHas('partnerCompanies', fn (Builder $inner): Builder => $inner->whereKey($data['value']))),
                SelectFilter::make('service')
                    ->label('Incluye el servicio')
                    ->options(fn (): array => PlanService::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereHas('services', fn (Builder $inner): Builder => $inner->whereKey($data['value']))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    PlanActions::duplicate(),
                    PlanActions::refreshVesPremiums(),
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin planes')
            ->emptyStateDescription('Cree un plan, elija sus servicios, defina la tarifa y asígnelo a los aliados.');
    }
}
