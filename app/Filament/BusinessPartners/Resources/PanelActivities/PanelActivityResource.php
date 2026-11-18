<?php

namespace App\Filament\BusinessPartners\Resources\PanelActivities;

use App\Filament\BusinessPartners\Resources\PanelActivities\Pages\ListPanelActivities;
use App\Models\PartnerPanelActivity;
use App\Support\Partners\PartnerPanel;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * «Bitácora» del panel: quién hizo qué sobre usuarios, permisos y afiliados de la empresa, tanto su
 * equipo como Farmadoc. Solo administradores del panel; solo lectura.
 */
class PanelActivityResource extends Resource
{
    protected static ?string $model = PartnerPanelActivity::class;

    protected static ?string $slug = 'bitacora';

    protected static ?string $modelLabel = 'registro';

    protected static ?string $pluralModelLabel = 'bitácora';

    protected static ?string $navigationLabel = 'Bitácora';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ClipboardDocumentCheck;

    protected static ?int $navigationSort = 20;

    protected static bool $isGloballySearchable = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Equipo';
    }

    public static function getBreadcrumb(): string
    {
        return 'Bitácora';
    }

    public static function canViewAny(): bool
    {
        return PartnerPanel::user()?->isPartnerAdmin() ?? false;
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
        $actions = PartnerPanelActivity::actions();

        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y h:i a')
                    ->sortable(),
                TextColumn::make('action')
                    ->label('Acción')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $actions[$state]['label'] ?? $state)
                    ->color(fn (string $state): string => $actions[$state]['color'] ?? 'gray')
                    ->icon(fn (string $state): ?string => $actions[$state]['icon'] ?? null),
                TextColumn::make('description')
                    ->label('Detalle')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('actor_name')
                    ->label('Hecho por')
                    ->state(fn (PartnerPanelActivity $record): string => $record->actor_name ?: $record->actorTypeLabel())
                    ->description(fn (PartnerPanelActivity $record): string => $record->actorTypeLabel())
                    ->searchable(['actor_name']),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Acción')
                    ->options(collect($actions)->map(fn (array $action): string => $action['label'])->all())
                    ->multiple(),
                SelectFilter::make('actor_type')
                    ->label('Hecho por')
                    ->options([PartnerPanelActivity::ACTOR_PARTNER => 'Su equipo', PartnerPanelActivity::ACTOR_FARMADOC => 'Farmadoc']),
                Filter::make('created_at')
                    ->label('Fecha')
                    ->schema([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->where('created_at', '>=', $date.' 00:00:00'))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->where('created_at', '<=', $date.' 23:59:59'))),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck)
            ->emptyStateHeading('Sin registros todavía')
            ->emptyStateDescription('Aquí verá cada cambio de usuarios, permisos y afiliados de su empresa.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPanelActivities::route('/'),
        ];
    }
}
