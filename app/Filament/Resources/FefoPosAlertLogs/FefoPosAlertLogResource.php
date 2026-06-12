<?php

namespace App\Filament\Resources\FefoPosAlertLogs;

use App\Filament\Resources\FefoPosAlertLogs\Pages\ManageFefoPosAlertLogs;
use App\Filament\Resources\FefoPosAlertLogs\Pages\ViewFefoPosAlertLog;
use App\Filament\Resources\FefoPosAlertLogs\Schemas\FefoPosAlertLogInfolist;
use App\Filament\Resources\FefoPosAlertLogs\Tables\FefoPosAlertLogsTable;
use App\Models\FefoPosAlertLog;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class FefoPosAlertLogResource extends Resource
{
    protected static ?string $model = FefoPosAlertLog::class;

    protected static ?string $navigationLabel = 'Alertas FEFO en caja';

    protected static ?string $modelLabel = 'Alerta FEFO';

    protected static ?string $pluralModelLabel = 'Alertas FEFO en caja';

    protected static ?string $recordTitleAttribute = 'product_name';

    protected static ?int $navigationSort = 13;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BellAlert;

    public static function getNavigationGroup(): ?string
    {
        return 'Inventario';
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        $user = request()->user() ?? Auth::user();

        return $user instanceof User && $user->isAdministrator();
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pendingToday = FefoPosAlertLog::query()
            ->whereDate('notified_at', today())
            ->whereNull('sale_id')
            ->count();

        return $pendingToday > 0 ? (string) $pendingToday : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * @return Builder<FefoPosAlertLog>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'branch:id,name',
                'user:id,name,email',
                'product:id,name,barcode',
                'sale:id,sale_number,sold_at',
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FefoPosAlertLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FefoPosAlertLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFefoPosAlertLogs::route('/'),
            'view' => ViewFefoPosAlertLog::route('/{record}'),
        ];
    }
}
