<?php

namespace App\Filament\Resources\WhatsappConversations\Pages;

use App\Enums\WhatsappConversationStatus;
use App\Filament\Resources\WhatsappConversations\WhatsappConversationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListWhatsappConversations extends ListRecords
{
    protected static string $resource = WhatsappConversationResource::class;

    protected static ?string $title = 'Historial de conversaciones de WhatsApp';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('inbox')
                ->label('Volver a la bandeja')
                ->icon(Heroicon::ChatBubbleLeftRight)
                ->url(WhatsappConversationResource::getUrl('index')),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tab = fn (string $label, WhatsappConversationStatus $status, Heroicon $icon, ?string $badgeColor = null): Tab => Tab::make($label)
            ->icon($icon)
            ->badge(fn (): ?int => $badgeColor !== null ? (WhatsappConversationResource::getEloquentQuery()->where('status', $status->value)->count() ?: null) : null)
            ->badgeColor($badgeColor ?? 'gray')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status->value));

        return [
            'needs_analyst' => $tab('Requieren analista', WhatsappConversationStatus::NeedsAnalyst, Heroicon::BellAlert, 'danger'),
            'pending_shift' => $tab('Pendientes de la jornada', WhatsappConversationStatus::PendingNextShift, Heroicon::Moon, 'warning'),
            'in_progress' => $tab('En gestión', WhatsappConversationStatus::InProgress, Heroicon::User, 'primary'),
            'bot' => $tab('Con el asistente', WhatsappConversationStatus::Bot, Heroicon::Sparkles, 'info'),
            'converted' => $tab('Pedidos creados', WhatsappConversationStatus::Converted, Heroicon::CheckCircle),
            'closed' => $tab('Cerradas', WhatsappConversationStatus::Closed, Heroicon::XCircle),
            'all' => Tab::make('Todas'),
        ];
    }
}
