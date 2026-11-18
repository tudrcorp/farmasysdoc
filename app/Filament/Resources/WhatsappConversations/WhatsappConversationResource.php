<?php

namespace App\Filament\Resources\WhatsappConversations;

use App\Filament\Resources\WhatsappConversations\Pages\ListWhatsappConversations;
use App\Filament\Resources\WhatsappConversations\Pages\WhatsappInbox;
use App\Filament\Resources\WhatsappConversations\Tables\WhatsappConversationsTable;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Bandeja de pedidos por WhatsApp. Las conversaciones llegan sin sucursal (la elige el analista al
 * crear el pedido), por eso no se filtran por sucursal: las ve todo el que tenga la bandeja en su rol.
 */
class WhatsappConversationResource extends Resource
{
    public const MENU_KEY = 'whatsapp_inbox';

    protected static ?string $model = WhatsappConversation::class;

    protected static ?string $navigationLabel = 'Bandeja WhatsApp';

    protected static ?string $modelLabel = 'Conversación de WhatsApp';

    protected static ?string $pluralModelLabel = 'Bandeja WhatsApp';

    protected static ?int $navigationSort = 5;

    protected static string|UnitEnum|null $navigationGroup = 'operations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChatBubbleLeftRight;

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        if (FarmaadminDeliveryUserAccess::denies(static::class)) {
            return false;
        }

        $user = Auth::user();

        return $user instanceof User && $user->canAccessFarmaadminMenuKey(self::MENU_KEY);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
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
        if (! static::canViewAny()) {
            return null;
        }

        $waiting = WhatsappConversation::query()->awaitingAnalyst()->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Conversaciones que esperan a un analista';
    }

    /**
     * @return Builder<WhatsappConversation>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['contact', 'assignedUser:id,name', 'branch:id,name']);
    }

    public static function table(Table $table): Table
    {
        return WhatsappConversationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => WhatsappInbox::route('/'),
            'history' => ListWhatsappConversations::route('/historial'),
        ];
    }
}
