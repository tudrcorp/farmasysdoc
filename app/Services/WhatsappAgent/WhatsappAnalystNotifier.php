<?php

namespace App\Services\WhatsappAgent;

use App\Filament\Resources\WhatsappConversations\WhatsappConversationResource;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisos en la campana del panel para quienes atienden la bandeja de WhatsApp.
 */
final class WhatsappAnalystNotifier
{
    public const MENU_KEY = 'whatsapp_inbox';

    public function conversationEscalated(WhatsappConversation $conversation): void
    {
        $name = $conversation->contact?->displayName() ?? 'Cliente';

        foreach ($this->inboxUsers() as $user) {
            $this->send(
                $user,
                'WhatsApp: '.$name.' necesita un analista',
                (string) ($conversation->escalation_reason ?? 'El asistente pasó la conversación al equipo.'),
                $conversation,
                'danger',
            );
        }
    }

    public function customerWroteToAnalyst(WhatsappConversation $conversation, WhatsappMessage $message): void
    {
        $user = $conversation->assignedUser;

        if (! $user instanceof User) {
            return;
        }

        $this->send(
            $user,
            'WhatsApp: nuevo mensaje de '.($conversation->contact?->displayName() ?? 'un cliente'),
            mb_strimwidth($message->textForAgent(), 0, 140, '…'),
            $conversation,
            'info',
        );
    }

    public function conversationReturnedForIdle(WhatsappConversation $conversation, User $analyst, int $idleMinutes): void
    {
        $this->send(
            $analyst,
            'WhatsApp: el asistente retomó a '.($conversation->contact?->displayName() ?? 'un cliente'),
            'El cliente escribió y no hubo respuesta en '.$idleMinutes.' min. Puede volver a tomar la conversación cuando quiera.',
            $conversation,
            'warning',
        );
    }

    public function conversationAssigned(WhatsappConversation $conversation, User $assignee, User $actor): void
    {
        if ((int) $assignee->getKey() === (int) $actor->getKey()) {
            return;
        }

        $this->send(
            $assignee,
            'WhatsApp: '.$actor->name.' le asignó una conversación',
            'Cliente: '.($conversation->contact?->displayName() ?? 'Cliente').'.',
            $conversation,
            'info',
        );
    }

    /**
     * Resumen al iniciar la jornada con lo que llegó fuera de horario.
     */
    public function shiftStartDigest(int $pendingCount): void
    {
        if ($pendingCount <= 0) {
            return;
        }

        foreach ($this->inboxUsers() as $user) {
            try {
                Notification::make()
                    ->title($pendingCount === 1 ? '1 pedido de WhatsApp quedó pendiente fuera de horario' : $pendingCount.' pedidos de WhatsApp quedaron pendientes fuera de horario')
                    ->body('Revise los resúmenes en «Pendientes de la jornada» y comuníquese con los clientes.')
                    ->warning()
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->actions([
                        Action::make('openInbox')
                            ->label('Abrir bandeja')
                            ->url(WhatsappConversationResource::getUrl('index', panel: 'farmaadmin')),
                    ])
                    ->sendToDatabase($user);
            } catch (Throwable $exception) {
                Log::warning('Asistente WhatsApp: no se pudo enviar el resumen de la jornada', ['error' => $exception->getMessage()]);
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function inboxUsers(): Collection
    {
        return User::query()
            ->get()
            ->filter(fn (User $user): bool => ! $user->isDeliveryUser()
                && ! $user->isAdministrator()
                && $user->canAccessFarmaadminMenuKey(self::MENU_KEY))
            ->values();
    }

    private function send(User $user, string $title, string $body, WhatsappConversation $conversation, string $status): void
    {
        try {
            Notification::make()
                ->title($title)
                ->body($body)
                ->status($status)
                ->icon('heroicon-o-chat-bubble-left-right')
                ->actions([
                    Action::make('openConversation')
                        ->label('Ver conversación')
                        ->url(WhatsappConversationResource::getUrl('index', ['conversacion' => $conversation->getKey()], panel: 'farmaadmin')),
                ])
                ->sendToDatabase($user);
        } catch (Throwable $exception) {
            Log::warning('Asistente WhatsApp: no se pudo notificar al analista', ['error' => $exception->getMessage()]);
        }
    }
}
