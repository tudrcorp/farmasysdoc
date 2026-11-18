<?php

namespace App\Filament\BusinessPartners\Resources\AgreementConsumptions;

use App\Enums\PartnerAgreementSaleStatus;
use App\Enums\PartnerPanelPermission;
use App\Filament\BusinessPartners\Resources\AgreementConsumptions\Pages\ListAgreementConsumptions;
use App\Filament\BusinessPartners\Resources\AgreementConsumptions\Pages\ViewAgreementConsumption;
use App\Models\Branch;
use App\Models\PartnerAgreementSale;
use App\Support\Partners\PartnerPanel;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * «Consumos del convenio» (plan Flexible): cada nota de entrega emitida en Farmadoc a un afiliado
 * del aliado, con su detalle. Solo lectura; permiso «Estado de cuenta».
 */
class AgreementConsumptionResource extends Resource
{
    protected static ?string $model = PartnerAgreementSale::class;

    protected static ?string $slug = 'consumos';

    protected static ?string $modelLabel = 'consumo';

    protected static ?string $pluralModelLabel = 'consumos del convenio';

    protected static ?string $navigationLabel = 'Consumos del convenio';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ReceiptPercent;

    protected static ?int $navigationSort = 15;

    protected static bool $isGloballySearchable = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Mi gestión';
    }

    public static function getBreadcrumb(): string
    {
        return 'Consumos del convenio';
    }

    public static function canViewAny(): bool
    {
        return PartnerPanel::can(PartnerPanelPermission::Account);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny() && $record instanceof PartnerAgreementSale && (int) $record->partner_company_id === PartnerPanel::companyId();
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['branch:id,name', 'plan:id,name'])->withCount('items'))
            ->columns([
                TextColumn::make('sold_at')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y h:i a')
                    ->sortable(),
                TextColumn::make('number')
                    ->label('Nota')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('client_name')
                    ->label('Afiliado')
                    ->description(fn (PartnerAgreementSale $record): ?string => $record->client_document ? 'C.I. '.$record->client_document : null)
                    ->searchable(['client_name', 'client_document'])
                    ->weight('medium'),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->toggleable(),
                TextColumn::make('items_count')
                    ->label('Productos')
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('total')
                    ->label('Monto')
                    ->money('USD')
                    ->sortable()
                    ->weight('semibold')
                    ->summarize(Sum::make()->money('USD')->label('Total')->query(fn ($query) => $query->where('status', PartnerAgreementSaleStatus::Completed->value))),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (PartnerAgreementSaleStatus $state): string => $state === PartnerAgreementSaleStatus::Cancelled ? 'Anulada (reintegrada)' : 'Aplicada')
                    ->color(fn (PartnerAgreementSaleStatus $state): string => $state === PartnerAgreementSaleStatus::Cancelled ? 'gray' : 'success'),
            ])
            ->filters([
                Filter::make('sold_at')
                    ->label('Fecha')
                    ->schema([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->where('sold_at', '>=', $date.' 00:00:00'))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->where('sold_at', '<=', $date.' 23:59:59'))),
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->options(fn (): array => Branch::query()
                        ->whereIn('id', PartnerAgreementSale::query()->where('partner_company_id', PartnerPanel::companyId())->distinct()->pluck('branch_id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([PartnerAgreementSaleStatus::Completed->value => 'Aplicada', PartnerAgreementSaleStatus::Cancelled->value => 'Anulada (reintegrada)']),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver detalle'),
            ])
            ->defaultSort('sold_at', 'desc')
            ->emptyStateIcon(Heroicon::OutlinedReceiptPercent)
            ->emptyStateHeading('Aún no hay consumos')
            ->emptyStateDescription('Cuando sus afiliados compren en Farmadoc con cargo al convenio, verá aquí cada nota de entrega.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Consumo')
                    ->icon(Heroicon::ReceiptPercent)
                    ->schema([
                        Grid::make(['default' => 2, 'md' => 4])
                            ->schema([
                                TextEntry::make('number')->label('Nota de entrega')->fontFamily('mono')->weight('bold'),
                                TextEntry::make('sold_at')->label('Fecha y hora')->dateTime('d/m/Y h:i a'),
                                TextEntry::make('branch.name')->label('Sucursal'),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->formatStateUsing(fn (PartnerAgreementSaleStatus $state): string => $state === PartnerAgreementSaleStatus::Cancelled ? 'Anulada (reintegrada)' : 'Aplicada')
                                    ->color(fn (PartnerAgreementSaleStatus $state): string => $state === PartnerAgreementSaleStatus::Cancelled ? 'gray' : 'success'),
                                TextEntry::make('plan.name')->label('Plan'),
                                TextEntry::make('payment')->label('Forma de pago')->state(PartnerAgreementSale::PAYMENT_LABEL),
                                TextEntry::make('total')->label('Monto')->money('USD')->weight('bold')->color('primary'),
                                TextEntry::make('fund_balance_after_usd')->label('Disponible tras el consumo')->money('USD')->placeholder('—'),
                            ]),
                        TextEntry::make('cancellation_reason')
                            ->label('Motivo de la anulación')
                            ->visible(fn (PartnerAgreementSale $record): bool => $record->isCancelled())
                            ->helperText('El monto se reintegró al fondo del convenio.'),
                    ])
                    ->columnSpanFull(),
                Section::make('Afiliado')
                    ->icon(Heroicon::User)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 4])->schema([
                            TextEntry::make('client_name')->label('Nombre')->weight('semibold'),
                            TextEntry::make('client_document')->label('Cédula')->placeholder('—'),
                            TextEntry::make('client_phone')->label('Teléfono')->placeholder('—'),
                            TextEntry::make('client_email')->label('Correo')->placeholder('—'),
                        ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Productos')
                    ->icon(Heroicon::ShoppingBag)
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Producto'),
                                TableColumn::make('Cantidad')->alignEnd(),
                                TableColumn::make('Precio unit.')->alignEnd(),
                                TableColumn::make('IVA')->alignEnd(),
                                TableColumn::make('Importe')->alignEnd(),
                            ])
                            ->schema([
                                TextEntry::make('product_name_snapshot'),
                                TextEntry::make('quantity')->numeric(decimalPlaces: 0, locale: 'es')->alignEnd(),
                                TextEntry::make('unit_price')->money('USD')->alignEnd(),
                                TextEntry::make('tax_amount')->money('USD')->alignEnd(),
                                TextEntry::make('line_total')->money('USD')->weight('semibold')->alignEnd(),
                            ]),
                        Grid::make(['default' => 3, 'md' => 6])
                            ->schema([
                                TextEntry::make('subtotal')->label('Subtotal')->money('USD')->columnStart(['md' => 4]),
                                TextEntry::make('tax_total')->label('IVA')->money('USD'),
                                TextEntry::make('total')->label('Total')->money('USD')->weight('bold'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgreementConsumptions::route('/'),
            'view' => ViewAgreementConsumption::route('/{record}'),
        ];
    }
}
