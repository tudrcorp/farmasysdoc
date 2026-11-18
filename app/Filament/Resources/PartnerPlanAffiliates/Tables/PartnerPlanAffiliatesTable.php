<?php

namespace App\Filament\Resources\PartnerPlanAffiliates\Tables;

use App\Enums\PartnerAffiliateSource;
use App\Models\PartnerCompany;
use App\Models\PartnerPlanAffiliate;
use App\Services\Partners\PartnerAffiliateService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class PartnerPlanAffiliatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client:id,name,document_number,phone,email', 'partnerCompany:id,legal_name,trade_name', 'plan:id,name', 'enrolledBy:id,name'])->withCount('sales'))
            ->columns([
                TextColumn::make('client.name')
                    ->label('Afiliado')
                    ->description(fn (PartnerPlanAffiliate $record): string => 'C.I. '.($record->client?->document_number ?? '—'))
                    ->searchable(['name', 'document_number'])
                    ->weight('medium'),
                TextColumn::make('partnerCompany.legal_name')
                    ->label('Aliado')
                    ->description(fn (PartnerPlanAffiliate $record): ?string => $record->plan?->name)
                    ->searchable(),
                TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(fn (PartnerAffiliateSource $state): string => $state->label())
                    ->color(fn (PartnerAffiliateSource $state): string => $state === PartnerAffiliateSource::Counter ? 'info' : 'gray')
                    ->description(fn (PartnerPlanAffiliate $record): ?string => $record->validated_with ? 'Validó: '.$record->validated_with : null),
                TextColumn::make('sales_count')
                    ->label('Compras')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('enrolled_at')
                    ->label('Afiliado el')
                    ->dateTime('d/m/Y')
                    ->description(fn (PartnerPlanAffiliate $record): ?string => $record->enrolledBy?->name)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->state(fn (PartnerPlanAffiliate $record): string => $record->is_active ? 'Activo' : 'De baja')
                    ->badge()
                    ->color(fn (PartnerPlanAffiliate $record): string => $record->is_active ? 'success' : 'danger')
                    ->description(fn (PartnerPlanAffiliate $record): ?string => $record->is_active ? null : ($record->deactivated_via === 'aliado' ? 'Por el aliado' : 'Por Farmadoc'))
                    ->tooltip(fn (PartnerPlanAffiliate $record): ?string => $record->deactivation_reason ? 'Motivo: '.$record->deactivation_reason : null),
            ])
            ->filters([
                SelectFilter::make('partner_company_id')
                    ->label('Aliado')
                    ->options(fn (): array => PartnerCompany::query()->orderBy('legal_name')->pluck('legal_name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('partner_company_plan_id')
                    ->label('Plan Flexible')
                    ->options(fn (): array => app(PartnerAffiliateService::class)->flexibleAssignmentOptions()),
                SelectFilter::make('source')
                    ->label('Origen')
                    ->options(PartnerAffiliateSource::options()),
                TernaryFilter::make('is_active')
                    ->label('Activo')
                    ->default(true),
            ])
            ->recordActions([
                Action::make('deactivate')
                    ->label('Dar de baja')
                    ->icon(Heroicon::NoSymbol)
                    ->color('danger')
                    ->visible(fn (PartnerPlanAffiliate $record): bool => $record->is_active)
                    ->modalHeading('Dar de baja del convenio')
                    ->modalDescription('No podrá comprar por convenio. Sus compras se conservan. El aliado verá la baja y el motivo en su bitácora.')
                    ->schema([
                        Textarea::make('reason')->label('Motivo')->required()->minLength(5)->maxLength(500)->rows(3),
                    ])
                    ->action(function (PartnerPlanAffiliate $record, array $data): void {
                        app(PartnerAffiliateService::class)->setActive($record, false, Auth::user(), (string) $data['reason']);
                        Notification::make()->title('Afiliado dado de baja')->success()->send();
                    }),
                Action::make('reactivate')
                    ->label('Reincorporar')
                    ->icon(Heroicon::CheckCircle)
                    ->color('success')
                    ->visible(fn (PartnerPlanAffiliate $record): bool => ! $record->is_active)
                    ->requiresConfirmation()
                    ->action(function (PartnerPlanAffiliate $record): void {
                        app(PartnerAffiliateService::class)->setActive($record, true, Auth::user());
                        Notification::make()->title('Afiliado reincorporado')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('deactivate')
                    ->label('Dar de baja seleccionados')
                    ->icon(Heroicon::NoSymbol)
                    ->color('danger')
                    ->schema([
                        Textarea::make('reason')->label('Motivo')->required()->minLength(5)->maxLength(500)->rows(3),
                    ])
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, array $data): void {
                        $records->each(fn (PartnerPlanAffiliate $affiliate) => app(PartnerAffiliateService::class)->setActive($affiliate, false, Auth::user(), (string) $data['reason']));
                        Notification::make()->title($records->count().' afiliados dados de baja')->success()->send();
                    }),
            ])
            ->defaultSort('enrolled_at', 'desc')
            ->emptyStateHeading('Sin afiliados')
            ->emptyStateDescription('Cárguelos desde Excel con «Cargar afiliados» o afílielos en caja.');
    }
}
