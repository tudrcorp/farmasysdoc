<?php

namespace App\Filament\Resources\WhatsappConversations\Pages;

use App\Enums\WhatsappConversationHandler;
use App\Enums\WhatsappConversationStatus;
use App\Filament\Resources\WhatsappConversations\Actions\WhatsappConversationActions;
use App\Filament\Resources\WhatsappConversations\WhatsappConversationResource;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\WhatsappAgent\WhatsappConversationWorkflow;
use App\Services\WhatsappAgent\WhatsappOutboundMessenger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Bandeja tipo CRM: lista de chats, conversación directa con el cliente y panel de gestión del pedido.
 */
class WhatsappInbox extends Page
{
    use WithFileUploads;

    public const ATTACHMENT_MAX_KB = 12288;

    /**
     * @var list<string>
     */
    public const ATTACHMENT_MIMES = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];

    protected static string $resource = WhatsappConversationResource::class;

    protected string $view = 'filament.whatsapp-conversations.inbox';

    protected Width|string|null $maxContentWidth = Width::Full;

    public const LIST_LIMIT = 100;

    /**
     * @var array<string, array{label: string, icon: string}>
     */
    public const FOLDERS = [
        'por_atender' => ['label' => 'Por atender', 'icon' => 'heroicon-o-bell-alert'],
        'mios' => ['label' => 'Mis chats', 'icon' => 'heroicon-o-user'],
        'en_gestion' => ['label' => 'En gestión', 'icon' => 'heroicon-o-users'],
        'asistente' => ['label' => 'Con el asistente', 'icon' => 'heroicon-o-sparkles'],
        'cerradas' => ['label' => 'Finalizadas', 'icon' => 'heroicon-o-archive-box'],
    ];

    #[Url(as: 'conversacion')]
    public ?int $selectedId = null;

    #[Url(as: 'bandeja')]
    public string $folder = 'por_atender';

    public string $search = '';

    /**
     * Foto o archivo que el analista va a enviar (subida temporal de Livewire).
     */
    public ?TemporaryUploadedFile $attachment = null;

    public function mount(): void
    {
        if (! array_key_exists($this->folder, self::FOLDERS)) {
            $this->folder = 'por_atender';
        }
    }

    public static function canAccess(array $parameters = []): bool
    {
        return WhatsappConversationResource::canViewAny();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Bandeja WhatsApp';
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * Antes de pintar, la conversación abierta queda como leída.
     */
    public function rendering(): void
    {
        $this->selectedConversation?->markReadByAnalyst();
    }

    public function selectFolder(string $folder): void
    {
        if (array_key_exists($folder, self::FOLDERS)) {
            $this->folder = $folder;
        }
    }

    public function selectConversation(int $conversationId): void
    {
        $this->selectedId = $conversationId;
        unset($this->selectedConversation);
    }

    public function closeConversationPanel(): void
    {
        $this->selectedId = null;
        unset($this->selectedConversation);
    }

    /**
     * El texto llega desde el compositor (Alpine): el mensaje se guarda al instante con ⏱ y sale por la cola.
     */
    public function sendMessage(string $body): void
    {
        $conversation = $this->selectedConversation;
        $user = Auth::user();
        $body = trim($body);

        if (! $conversation instanceof WhatsappConversation || ! $user instanceof User || $body === '') {
            return;
        }

        if (mb_strlen($body) > 4000) {
            Notification::make()->title('El mensaje es muy largo')->body('Máximo 4000 caracteres.')->warning()->send();

            return;
        }

        try {
            app(WhatsappConversationWorkflow::class)->reply($conversation, $user, $body);
        } catch (ValidationException $exception) {
            Notification::make()->title('No se pudo enviar')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();

            return;
        }

        unset($this->selectedConversation, $this->conversations, $this->folderCounts);
    }

    /**
     * Envía la foto o el archivo subido, con el texto del compositor como comentario.
     */
    public function sendAttachment(string $caption = ''): void
    {
        $conversation = $this->selectedConversation;
        $user = Auth::user();

        if (! $conversation instanceof WhatsappConversation || ! $user instanceof User) {
            return;
        }

        try {
            $this->validate([
                'attachment' => ['required', 'file', 'max:'.self::ATTACHMENT_MAX_KB, 'mimes:'.implode(',', self::ATTACHMENT_MIMES)],
            ], [
                'attachment.required' => 'Seleccione una foto o un archivo.',
                'attachment.max' => 'El archivo no puede pesar más de 12 MB.',
                'attachment.mimes' => 'Solo se pueden enviar fotos (JPG, PNG, WEBP), PDF, Word o Excel.',
            ]);
        } catch (ValidationException $exception) {
            Notification::make()->title('No se pudo adjuntar')->body(collect($exception->errors())->flatten()->implode(' '))->warning()->send();
            $this->attachment = null;
            $this->dispatch('whatsapp-attachment-failed');

            return;
        }

        /** @var TemporaryUploadedFile $file */
        $file = $this->attachment;
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'bin'));
        $path = $file->storeAs('whatsapp-agent/media/outbound/'.now()->format('Y/m'), Str::uuid()->toString().'.'.$extension, WhatsappMessage::MEDIA_DISK);

        try {
            app(WhatsappConversationWorkflow::class)->replyWithAttachment(
                $conversation,
                $user,
                (string) $path,
                (string) ($file->getMimeType() ?: 'application/octet-stream'),
                mb_substr($file->getClientOriginalName(), 0, 120),
                mb_substr(trim($caption), 0, 1024) ?: null,
            );
        } catch (ValidationException $exception) {
            Notification::make()->title('No se pudo enviar')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();

            return;
        }

        $this->attachment = null;
        unset($this->selectedConversation, $this->conversations, $this->folderCounts);
        $this->dispatch('whatsapp-attachment-sent');
    }

    public function removeAttachment(): void
    {
        $this->attachment = null;
        $this->resetValidation('attachment');
    }

    public function retryMessage(int $messageId): void
    {
        $message = WhatsappMessage::query()
            ->whereKey($messageId)
            ->where('whatsapp_conversation_id', $this->selectedId)
            ->first();

        if ($message instanceof WhatsappMessage) {
            app(WhatsappOutboundMessenger::class)->retry($message);
            unset($this->selectedConversation);
        }
    }

    /**
     * Mientras haya mensajes saliendo, la vista se refresca más seguido para mostrar el ✓.
     */
    public function pollInterval(): string
    {
        $conversation = $this->selectedConversation;

        return $conversation !== null && $conversation->messages->contains(fn (WhatsappMessage $message): bool => $message->isPendingDelivery())
            ? '1500ms'
            : '5s';
    }

    /**
     * @return list<array{label: string, text: string}>
     */
    public function quickReplies(): array
    {
        $first = collect(preg_split('/\s+/u', trim((string) Auth::user()?->name)) ?: [])
            ->first(fn (string $part): bool => $part !== '' && ! in_array(mb_strtolower($part), ['usuario', 'user', 'admin'], true));
        $name = $first !== null ? $first : 'el equipo';

        return [
            ['label' => 'Saludo', 'text' => '¡Buen día! Le saluda '.$name.', de Farmadoc. Ya estoy revisando su solicitud.'],
            ['label' => 'Un momento', 'text' => 'Con gusto. Permítame un momento mientras verifico la disponibilidad.'],
            ['label' => 'Récipe revisado', 'text' => 'Ya revisé su récipe. ¿Me confirma si desea llevar todos los medicamentos?'],
            ['label' => 'Dirección', 'text' => '¿Me confirma la dirección de entrega con un punto de referencia, por favor?'],
            ['label' => 'Forma de pago', 'text' => '¿Cómo desea pagar: pago móvil, efectivo en dólares o punto de venta?'],
            ['label' => 'Despedida', 'text' => '¡Gracias por preferirnos! Cualquier cosa, aquí estamos para ayudarle.'],
        ];
    }

    #[Computed]
    public function selectedConversation(): ?WhatsappConversation
    {
        if ($this->selectedId === null) {
            return null;
        }

        return WhatsappConversation::query()
            ->with([
                'contact',
                'assignedUser:id,name',
                'branch:id,name',
                'items.product:id,name',
                'prescriptionReadings.items',
                'prescriptionReadings.validatedBy:id,name',
                'order:id,order_number,status',
                'posHeldSale',
                'messages.sentBy:id,name',
                'messages.prescriptionReading.items',
            ])
            ->find($this->selectedId);
    }

    /**
     * @return Collection<int, WhatsappConversation>
     */
    #[Computed]
    public function conversations(): Collection
    {
        $query = WhatsappConversation::query()
            ->with(['contact', 'assignedUser:id,name', 'latestMessage.sentBy:id,name'])
            ->withUnreadCount();

        $this->applyFolder($query, $this->folder);

        $search = trim($this->search);

        if ($search !== '') {
            $digits = (string) preg_replace('/\D+/', '', $search);

            $query->whereHas('contact', function (Builder $contact) use ($search, $digits): void {
                $contact->where('full_name', 'like', '%'.$search.'%')
                    ->orWhere('profile_name', 'like', '%'.$search.'%')
                    ->when($digits !== '', fn (Builder $byDigits): Builder => $byDigits
                        ->orWhere('phone', 'like', '%'.ltrim($digits, '0').'%')
                        ->orWhere('document_number', 'like', '%'.$digits.'%'));
            });
        }

        return $query
            ->orderByRaw('CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END', [
                WhatsappConversationStatus::NeedsAnalyst->value,
                WhatsappConversationStatus::PendingNextShift->value,
            ])
            ->orderByDesc('last_inbound_at')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function folderCounts(): array
    {
        $counts = [];

        foreach (['por_atender', 'mios'] as $folder) {
            $query = WhatsappConversation::query();
            $this->applyFolder($query, $folder);
            $counts[$folder] = $query->count();
        }

        return $counts;
    }

    public function isMine(WhatsappConversation $conversation): bool
    {
        return $conversation->handled_by === WhatsappConversationHandler::Analyst
            && (int) $conversation->assigned_user_id === (int) Auth::id();
    }

    public function takeAction(): Action
    {
        return $this->forSelected(WhatsappConversationActions::take()->label('Tomar'));
    }

    public function assignAction(): Action
    {
        return $this->forSelected(WhatsappConversationActions::assign());
    }

    public function returnToAgentAction(): Action
    {
        return $this->forSelected(WhatsappConversationActions::returnToAgent()->label('Devolver al asistente'));
    }

    public function closeAction(): Action
    {
        return $this->forSelected(WhatsappConversationActions::close()->label('Cerrar'));
    }

    public function convertToPickupAction(): Action
    {
        return $this->forSelected(WhatsappConversationActions::convertToPickup());
    }

    public function convertToDeliveryAction(): Action
    {
        return $this->forSelected(WhatsappConversationActions::convertToDelivery()->label('Delivery'));
    }

    public function registerCustomerAction(): Action
    {
        return $this->forSelected(WhatsappConversationActions::registerCustomer());
    }

    public function editItemsAction(): Action
    {
        return $this->forSelected(WhatsappConversationActions::editItems());
    }

    public function editDeliveryAction(): Action
    {
        return $this->forSelected(WhatsappConversationActions::editDelivery());
    }

    public function validatePrescriptionsAction(): Action
    {
        return $this->forSelected(WhatsappConversationActions::validatePrescriptions());
    }

    private function forSelected(Action $action): Action
    {
        return $action
            ->record(fn (): ?WhatsappConversation => $this->selectedConversation)
            ->after(function (): void {
                unset($this->selectedConversation, $this->conversations, $this->folderCounts);
            });
    }

    /**
     * @param  Builder<WhatsappConversation>  $query
     */
    private function applyFolder(Builder $query, string $folder): void
    {
        match ($folder) {
            'por_atender' => $query->awaitingAnalyst(),
            'mios' => $query->open()
                ->where('handled_by', WhatsappConversationHandler::Analyst->value)
                ->where('assigned_user_id', Auth::id()),
            'en_gestion' => $query->where('status', WhatsappConversationStatus::InProgress->value),
            'asistente' => $query->where('status', WhatsappConversationStatus::Bot->value),
            'cerradas' => $query->whereIn('status', [WhatsappConversationStatus::Converted->value, WhatsappConversationStatus::Closed->value]),
            default => $query->open(),
        };
    }
}
