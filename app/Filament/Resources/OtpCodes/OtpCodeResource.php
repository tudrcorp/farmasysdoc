<?php

namespace App\Filament\Resources\OtpCodes;

use App\Filament\Resources\OtpCodes\Pages\ListOtpCodes;
use App\Filament\Resources\OtpCodes\Tables\OtpCodesTable;
use App\Models\OtpCode;
use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class OtpCodeResource extends Resource
{
    public const MENU_KEY = 'otp_codes';

    protected static ?string $model = OtpCode::class;

    protected static ?string $navigationLabel = 'Claves OTP';

    protected static ?string $modelLabel = 'Clave OTP';

    protected static ?string $pluralModelLabel = 'Claves OTP';

    protected static ?int $navigationSort = 940;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Key;

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    /**
     * Administradores, y gerentes con el ítem «Claves OTP» habilitado en su rol.
     */
    public static function canViewAny(): bool
    {
        if (FarmaadminDeliveryUserAccess::denies(static::class)) {
            return false;
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        return $user->isManager() && $user->canAccessFarmaadminMenuKey(self::MENU_KEY);
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
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        $active = OtpCode::query()->visibleTo($user)->active()->count();

        return $active > 0 ? (string) $active : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Claves vigentes por autorizar';
    }

    /**
     * @return Builder<OtpCode>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'branch:id,name',
                'requestedBy:id,name,email',
                'usedBy:id,name,email',
            ]);

        $user = Auth::user();

        return $user instanceof User
            ? $query->visibleTo($user)
            : $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return OtpCodesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOtpCodes::route('/'),
        ];
    }
}
