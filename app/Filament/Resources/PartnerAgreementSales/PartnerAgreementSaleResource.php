<?php

namespace App\Filament\Resources\PartnerAgreementSales;

use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Filament\Resources\PartnerAgreementSales\Pages\ListPartnerAgreementSales;
use App\Filament\Resources\PartnerAgreementSales\Pages\ViewPartnerAgreementSale;
use App\Filament\Resources\PartnerAgreementSales\Schemas\PartnerAgreementSaleInfolist;
use App\Filament\Resources\PartnerAgreementSales\Tables\PartnerAgreementSalesTable;
use App\Models\PartnerAgreementSale;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Ventas por convenio (plan Flexible): notas de entrega emitidas en caja con cargo al fondo del
 * aliado. Las ve quien tiene el menú «Ventas por convenio» o «Ventas» (el cajero llega aquí al
 * cerrar la venta); los cajeros solo ven las suyas. Se acotan por sucursal.
 */
class PartnerAgreementSaleResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = PartnerAgreementSale::class;

    protected static ?string $slug = 'partner-agreement-sales';

    protected static ?string $modelLabel = 'venta por convenio';

    protected static ?string $pluralModelLabel = 'ventas por convenio';

    protected static ?string $navigationLabel = 'Ventas por convenio';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        $user = auth()->user();

        return $user instanceof User ? $user->navigationOperationsGroupLabel() : 'Farmadoc®';
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
        $query = BranchAuthScope::apply(parent::getEloquentQuery());
        $user = auth()->user();

        if ($user instanceof User && $user->isCashier() && ! $user->isAdministrator() && ! $user->isManager()) {
            $query->where('sold_by', $user->getKey());
        }

        return $query;
    }

    public static function infolist(Schema $schema): Schema
    {
        return PartnerAgreementSaleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartnerAgreementSalesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerAgreementSales::route('/'),
            'view' => ViewPartnerAgreementSale::route('/{record}'),
        ];
    }
}
