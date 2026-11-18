<?php

namespace App\Filament\BusinessPartners\Resources\PlanAffiliates;

use App\Enums\PartnerAgreementSaleStatus;
use App\Enums\PartnerPanelPermission;
use App\Filament\BusinessPartners\Resources\PlanAffiliates\Pages\ListPlanAffiliates;
use App\Models\PartnerPlanAffiliate;
use App\Models\Plan;
use App\Services\Partners\PartnerAffiliateService;
use App\Support\Partners\PartnerPanel;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * «Afiliados» del aliado: quién puede comprar con cargo al convenio y cuánto ha consumido
 * (permiso «Planes y convenios»). Con «Dar de baja afiliados» se retira o reincorpora a un
 * afiliado indicando el motivo; queda en la bitácora. Las altas las hace Farmadoc.
 */
class PlanAffiliateResource extends Resource
{
    protected static ?string $model = PartnerPlanAffiliate::class;

    protected static ?string $slug = 'afiliados';

    protected static ?string $modelLabel = 'afiliado';

    protected static ?string $pluralModelLabel = 'afiliados';

    protected static ?string $navigationLabel = 'Afiliados';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Identification;

    protected static ?int $navigationSort = 12;

    protected static bool $isGloballySearchable = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Mi gestión';
    }

    public static function getBreadcrumb(): string
    {
        return 'Afiliados';
    }

    public static function canViewAny(): bool
    {
        return PartnerPanel::can(PartnerPanelPermission::PlansAndAgreements);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $partnerId = PartnerPanel::companyId();

        return parent::getEloquentQuery()
            ->when($partnerId === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($partnerId !== null, fn (Builder $query) => $query->where('partner_company_id', $partnerId));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['client:id,name,document_number,phone', 'plan:id,name', 'deactivatedBy:id,name'])
                ->withCount(['sales as purchases_count' => fn (Builder $q) => $q->where('status', PartnerAgreementSaleStatus::Completed->value)])
                ->withSum(['sales as consumed_usd' => fn (Builder $q) => $q->where('status', PartnerAgreementSaleStatus::Completed->value)], 'total')
                ->withMax('sales as last_purchase_at', 'sold_at'))
            ->columns([
                TextColumn::make('client.name')
                    ->label('Afiliado')
                    ->description(fn (PartnerPlanAffiliate $record): string => 'C.I. '.($record->client?->document_number ?? '—'))
                    ->searchable(['name', 'document_number'])
                    ->weight('medium'),
                TextColumn::make('plan.name')
                    ->label('Plan'),
                TextColumn::make('purchases_count')
                    ->label('Compras')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('consumed_usd')
                    ->label('Consumido')
                    ->money('USD')
                    ->placeholder('$0,00')
                    ->sortable(),
                TextColumn::make('last_purchase_at')
                    ->label('Última compra')
                    ->dateTime('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('enrolled_at')
                    ->label('Afiliado desde')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estado')
                    ->state(fn (PartnerPlanAffiliate $record): string => $record->is_active ? 'Activo' : 'De baja')
                    ->badge()
                    ->color(fn (PartnerPlanAffiliate $record): string => $record->is_active ? 'success' : 'danger')
                    ->description(fn (PartnerPlanAffiliate $record): ?string => $record->is_active ? null : trim(
                        ($record->deactivated_at?->format('d/m/Y') ?? '').' · '
                        .($record->deactivated_via === 'aliado' ? ($record->deactivatedBy?->name ?? 'Su equipo') : 'Farmadoc')
                    ))
                    ->tooltip(fn (PartnerPlanAffiliate $record): ?string => $record->is_active ? null : ($record->deactivation_reason ? 'Motivo: '.$record->deactivation_reason : null)),
            ])
            ->filters([
                SelectFilter::make('plan_id')
                    ->label('Plan')
                    ->options(fn (): array => Plan::query()
                        ->whereIn('id', PartnerPlanAffiliate::query()->where('partner_company_id', PartnerPanel::companyId())->distinct()->pluck('plan_id'))
                        ->pluck('name', 'id')
                        ->all()),
                TernaryFilter::make('is_active')->label('Activo')->default(true),
            ])
            ->recordActions([
                Action::make('deactivate')
                    ->label('Dar de baja')
                    ->icon(Heroicon::UserMinus)
                    ->color('danger')
                    ->visible(fn (PartnerPlanAffiliate $record): bool => $record->is_active && PartnerPanel::can(PartnerPanelPermission::DeactivateAffiliates))
                    ->modalHeading(fn (PartnerPlanAffiliate $record): string => 'Dar de baja a '.$record->client?->name)
                    ->modalDescription('Dejará de poder comprar con cargo al convenio desde ahora. Sus compras anteriores se conservan. Quedará registrado quién lo hizo y el motivo.')
                    ->modalIcon(Heroicon::UserMinus)
                    ->modalSubmitActionLabel('Dar de baja')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Motivo')
                            ->placeholder('Ej.: Egresó de la empresa el 10/11/2026')
                            ->required()
                            ->minLength(5)
                            ->maxLength(500)
                            ->rows(3),
                    ])
                    ->action(function (PartnerPlanAffiliate $record, array $data): void {
                        self::runGuarded(fn () => app(PartnerAffiliateService::class)->setActive($record, false, PartnerPanel::user(), (string) $data['reason']));
                        Notification::make()->title('Afiliado dado de baja')->body($record->client?->name.' ya no puede comprar por convenio.')->success()->send();
                    }),
                Action::make('reactivate')
                    ->label('Reincorporar')
                    ->icon(Heroicon::UserPlus)
                    ->color('success')
                    ->visible(fn (PartnerPlanAffiliate $record): bool => ! $record->is_active && PartnerPanel::can(PartnerPanelPermission::DeactivateAffiliates))
                    ->requiresConfirmation()
                    ->modalHeading(fn (PartnerPlanAffiliate $record): string => 'Reincorporar a '.$record->client?->name)
                    ->modalDescription('Podrá volver a comprar con cargo al convenio.')
                    ->modalSubmitActionLabel('Reincorporar')
                    ->action(function (PartnerPlanAffiliate $record): void {
                        self::runGuarded(fn () => app(PartnerAffiliateService::class)->setActive($record, true, PartnerPanel::user()));
                        Notification::make()->title('Afiliado reincorporado')->success()->send();
                    }),
            ])
            ->defaultSort('enrolled_at', 'desc')
            ->emptyStateIcon(Heroicon::OutlinedIdentification)
            ->emptyStateHeading('Aún no hay afiliados')
            ->emptyStateDescription('Farmadoc registra a sus afiliados al activar el convenio o cuando se validan en mostrador.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlanAffiliates::route('/'),
        ];
    }

    /**
     * Muestra las reglas del servicio como aviso y detiene la acción.
     */
    private static function runGuarded(callable $operation): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            Notification::make()->title((string) collect($exception->errors())->flatten()->first())->danger()->send();

            throw new Halt;
        }
    }
}
