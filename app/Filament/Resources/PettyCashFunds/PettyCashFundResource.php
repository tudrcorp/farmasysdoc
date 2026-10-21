<?php

namespace App\Filament\Resources\PettyCashFunds;

use App\Filament\Resources\PettyCashFunds\Concerns\ChecksPettyCashAccess;
use App\Filament\Resources\PettyCashFunds\Pages\CreatePettyCashFund;
use App\Filament\Resources\PettyCashFunds\Pages\EditPettyCashFund;
use App\Filament\Resources\PettyCashFunds\Pages\ListPettyCashFunds;
use App\Filament\Resources\PettyCashFunds\Pages\ViewPettyCashFund;
use App\Filament\Resources\PettyCashFunds\Schemas\PettyCashFundForm;
use App\Filament\Resources\PettyCashFunds\Schemas\PettyCashFundInfolist;
use App\Filament\Resources\PettyCashFunds\Tables\PettyCashFundsTable;
use App\Models\PettyCashFund;
use App\Support\Filament\BranchAuthScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PettyCashFundResource extends Resource
{
    use ChecksPettyCashAccess;

    protected static ?string $model = PettyCashFund::class;

    protected static ?string $navigationLabel = 'Caja chica';

    protected static ?string $modelLabel = 'caja chica';

    protected static ?string $pluralModelLabel = 'cajas chicas';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 42;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    public static function form(Schema $schema): Schema
    {
        return PettyCashFundForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PettyCashFundInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PettyCashFundsTable::configure($table);
    }

    /**
     * @return Builder<PettyCashFund>
     */
    public static function getEloquentQuery(): Builder
    {
        return BranchAuthScope::apply(parent::getEloquentQuery());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPettyCashFunds::route('/'),
            'create' => CreatePettyCashFund::route('/create'),
            'view' => ViewPettyCashFund::route('/{record}'),
            'edit' => EditPettyCashFund::route('/{record}/edit'),
        ];
    }
}
