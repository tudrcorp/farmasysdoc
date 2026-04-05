<?php

namespace App\Filament\Resources\Deliveries;

use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Filament\Resources\Deliveries\Pages\CreateDelivery;
use App\Filament\Resources\Deliveries\Pages\EditDelivery;
use App\Filament\Resources\Deliveries\Pages\ListDeliveries;
use App\Filament\Resources\Deliveries\Pages\ViewDelivery;
use App\Filament\Resources\Deliveries\Schemas\DeliveryForm;
use App\Filament\Resources\Deliveries\Schemas\DeliveryInfolist;
use App\Filament\Resources\Deliveries\Tables\DeliveriesTable;
use App\Models\Delivery;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class DeliveryResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = Delivery::class;

    protected static ?string $navigationLabel = 'Entregas';

    protected static ?string $modelLabel = 'entrega';

    protected static ?string $pluralModelLabel = 'entregas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Truck;

    public static function getNavigationGroup(): ?string
    {
        $user = auth()->user();

        return $user instanceof User ? $user->navigationOperationsGroupLabel() : 'Farmadoc®';
    }

    public static function getNavigationSort(): ?int
    {
        return 15;
    }

    public static function form(Schema $schema): Schema
    {
        return DeliveryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DeliveryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeliveriesTable::configure($table);
    }

    /**
     * @param  Model|Delivery|null  $record
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if ($record instanceof Delivery) {
            $line = filled($record->order_number)
                ? $record->order_number
                : 'Registro #'.(string) $record->getKey();

            return 'Entrega · '.$line;
        }

        return parent::getRecordTitle($record);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliveries::route('/'),
            'create' => CreateDelivery::route('/create'),
            'view' => ViewDelivery::route('/{record}'),
            'edit' => EditDelivery::route('/{record}/edit'),
        ];
    }
}
