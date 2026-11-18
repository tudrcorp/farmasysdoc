<?php

namespace App\Models;

use App\Enums\WhatsappConversationHandler;
use App\Enums\WhatsappConversationStatus;
use App\Enums\WhatsappFulfillmentType;
use App\Enums\WhatsappMessageAuthor;
use Database\Factories\WhatsappConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Conversación de pedidos por WhatsApp: la atiende el asistente (n8n) y la gestiona un analista.
 *
 * `state` guarda el hilo estructurado que mantiene el asistente entre turnos:
 * etapa, pregunta pendiente y notas.
 */
class WhatsappConversation extends Model
{
    /** @use HasFactory<WhatsappConversationFactory> */
    use HasFactory;

    /**
     * Etapas del pedido que el asistente puede registrar en `state.stage`.
     *
     * @var array<string, string>
     */
    public const STAGES = [
        'saludo' => 'Saludo',
        'necesidad' => 'Identificando qué necesita',
        'recipe' => 'Revisando récipe',
        'cotizacion' => 'Cotizando',
        'datos_cliente' => 'Tomando datos del cliente',
        'entrega' => 'Definiendo entrega',
        'pago' => 'Forma de pago',
        'confirmacion' => 'Confirmando pedido',
        'enviado_analista' => 'Enviado al analista',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'whatsapp_contact_id',
        'status',
        'handled_by',
        'assigned_user_id',
        'branch_id',
        'fulfillment_type',
        'delivery_address',
        'delivery_reference',
        'delivery_latitude',
        'delivery_longitude',
        'delivery_recipient_name',
        'delivery_recipient_phone',
        'payment_method_note',
        'state',
        'summary',
        'escalation_reason',
        'escalated_at',
        'escalated_off_hours',
        'last_inbound_at',
        'last_outbound_at',
        'last_agent_turn_message_id',
        'analyst_last_read_message_id',
        'order_id',
        'pos_held_sale_id',
        'closed_at',
        'closed_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WhatsappConversationStatus::class,
            'handled_by' => WhatsappConversationHandler::class,
            'fulfillment_type' => WhatsappFulfillmentType::class,
            'delivery_latitude' => 'decimal:7',
            'delivery_longitude' => 'decimal:7',
            'state' => 'array',
            'escalated_at' => 'datetime',
            'escalated_off_hours' => 'boolean',
            'last_inbound_at' => 'datetime',
            'last_outbound_at' => 'datetime',
            'last_agent_turn_message_id' => 'integer',
            'analyst_last_read_message_id' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WhatsappContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsappContact::class, 'whatsapp_contact_id');
    }

    /**
     * @return HasMany<WhatsappMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class)->orderBy('id');
    }

    /**
     * @return HasOne<WhatsappMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(WhatsappMessage::class)->latestOfMany();
    }

    /**
     * @return HasMany<WhatsappConversationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(WhatsappConversationItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<PrescriptionReading, $this>
     */
    public function prescriptionReadings(): HasMany
    {
        return $this->hasMany(PrescriptionReading::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<PosHeldSale, $this>
     */
    public function posHeldSale(): BelongsTo
    {
        return $this->belongsTo(PosHeldSale::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /**
     * @param  Builder<WhatsappConversation>  $query
     * @return Builder<WhatsappConversation>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            WhatsappConversationStatus::Converted->value,
            WhatsappConversationStatus::Closed->value,
        ]);
    }

    /**
     * @param  Builder<WhatsappConversation>  $query
     * @return Builder<WhatsappConversation>
     */
    public function scopeAwaitingAnalyst(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            static fn (WhatsappConversationStatus $status): string => $status->value,
            WhatsappConversationStatus::awaitingAnalyst(),
        ));
    }

    /**
     * Agrega `unread_count`: mensajes del cliente que ningún analista ha visto en la bandeja.
     *
     * @param  Builder<WhatsappConversation>  $query
     * @return Builder<WhatsappConversation>
     */
    public function scopeWithUnreadCount(Builder $query): Builder
    {
        return $query->withCount(['messages as unread_count' => fn (Builder $messages): Builder => $messages
            ->where('author', WhatsappMessageAuthor::Customer->value)
            ->whereRaw('whatsapp_messages.id > COALESCE(whatsapp_conversations.analyst_last_read_message_id, 0)')]);
    }

    public function markReadByAnalyst(): void
    {
        $latest = $this->latestInboundMessageId();

        if ($latest !== null && $latest !== (int) $this->analyst_last_read_message_id) {
            $this->forceFill(['analyst_last_read_message_id' => $latest])->saveQuietly();
        }
    }

    public function isHandledByAgent(): bool
    {
        return $this->handled_by === WhatsappConversationHandler::Agent;
    }

    public function isOpen(): bool
    {
        return $this->status instanceof WhatsappConversationStatus && $this->status->isOpen();
    }

    public function stage(): ?string
    {
        $stage = $this->state['stage'] ?? null;

        return is_string($stage) && $stage !== '' ? $stage : null;
    }

    public function stageLabel(): string
    {
        $stage = $this->stage();

        return $stage !== null ? (self::STAGES[$stage] ?? $stage) : '—';
    }

    public function latestInboundMessageId(): ?int
    {
        $id = $this->messages()
            ->where('author', WhatsappMessageAuthor::Customer->value)
            ->max('id');

        return $id !== null ? (int) $id : null;
    }

    public function minutesWaitingForAnalyst(): ?int
    {
        if ($this->escalated_at === null || ! in_array($this->status, WhatsappConversationStatus::awaitingAnalyst(), true)) {
            return null;
        }

        return (int) max(0, $this->escalated_at->diffInMinutes(now()));
    }
}
