<?php

namespace App\Filament\Resources\SalesVatRetentions;

use App\Filament\Resources\SalesVatRetentions\Pages\CreateSalesVatRetention;
use App\Filament\Resources\SalesVatRetentions\Pages\EditSalesVatRetention;
use App\Filament\Resources\SalesVatRetentions\Pages\ListSalesVatRetentions;
use App\Filament\Resources\SalesVatRetentions\Schemas\SalesVatRetentionForm;
use App\Filament\Resources\SalesVatRetentions\Tables\SalesVatRetentionsTable;
use App\Models\SalesVatRetention;
use App\Models\User;
use App\Services\Fiscal\SalesBookPeriodLock;
use App\Support\Filament\BranchAuthScope;
use App\Support\Fiscal\SalesBookPeriod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SalesVatRetentionResource extends Resource
{
    protected static ?string $model = SalesVatRetention::class;

    protected static ?string $navigationLabel = 'Retenciones IVA (clientes)';

    protected static ?string $modelLabel = 'comprobante de retención de cliente';

    protected static ?string $pluralModelLabel = 'retenciones de IVA de clientes';

    protected static ?string $recordTitleAttribute = 'voucher_number';

    protected static ?int $navigationSort = 14;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ReceiptRefund;

    public static function getNavigationGroup(): ?string
    {
        $user = auth()->user();

        return $user instanceof User ? $user->navigationOperationsGroupLabel() : 'Farmadoc®';
    }

    public static function form(Schema $schema): Schema
    {
        return SalesVatRetentionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesVatRetentionsTable::configure($table);
    }

    /**
     * @return Builder<SalesVatRetention>
     */
    public static function getEloquentQuery(): Builder
    {
        return BranchAuthScope::apply(parent::getEloquentQuery()->with(['branch', 'fiscalPrinter']));
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof SalesVatRetention && ! self::isInClosedPeriod($record);
    }

    public static function canDelete(Model $record): bool
    {
        return self::canEdit($record);
    }

    public static function isInClosedPeriod(SalesVatRetention $record): bool
    {
        return $record->received_on !== null
            && app(SalesBookPeriodLock::class)->isClosed((int) $record->branch_id, SalesBookPeriod::containing($record->received_on));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesVatRetentions::route('/'),
            'create' => CreateSalesVatRetention::route('/create'),
            'edit' => EditSalesVatRetention::route('/{record}/edit'),
        ];
    }
}
