<?php

namespace App\Models;

use App\Enums\PrescriptionLegibility;
use App\Enums\WhatsappImageKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lectura de un récipe enviado por WhatsApp (modelo de visión), pendiente de validar por un analista.
 */
class PrescriptionReading extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'whatsapp_conversation_id',
        'whatsapp_message_id',
        'is_prescription',
        'image_kind',
        'description',
        'payment_data',
        'model',
        'legibility',
        'overall_confidence',
        'doctor_name',
        'prescription_date',
        'issues',
        'requires_analyst',
        'has_controlled_substance',
        'raw_response',
        'validated_by_user_id',
        'validated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_prescription' => 'boolean',
            'image_kind' => WhatsappImageKind::class,
            'payment_data' => 'array',
            'legibility' => PrescriptionLegibility::class,
            'overall_confidence' => 'decimal:2',
            'issues' => 'array',
            'requires_analyst' => 'boolean',
            'has_controlled_substance' => 'boolean',
            'raw_response' => 'array',
            'validated_at' => 'datetime',
        ];
    }

    public function isRecipe(): bool
    {
        return $this->image_kind === WhatsappImageKind::Recipe;
    }

    /**
     * Resumen en una línea para el asistente y la bandeja.
     */
    public function summaryLine(): string
    {
        $kind = $this->image_kind ?? WhatsappImageKind::Recipe;

        if ($kind === WhatsappImageKind::PaymentProof) {
            $payment = $this->payment_data ?? [];

            return 'Comprobante de pago'.collect([
                $payment['amount'] ?? null,
                filled($payment['reference'] ?? null) ? 'ref. '.$payment['reference'] : null,
                $payment['bank'] ?? null,
            ])->filter()->map(fn ($part): string => ' · '.$part)->implode('');
        }

        if ($kind === WhatsappImageKind::Recipe) {
            $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

            return 'Récipe ('.mb_strtolower((string) $this->legibility?->label()).')'
                .($items->isNotEmpty() ? ': '.$items->map(fn (PrescriptionReadingItem $item): string => trim($item->medication_name.' '.$item->concentration))->implode(', ') : '');
        }

        return $kind->label().(filled($this->description) ? ': '.$this->description : '');
    }

    /**
     * @return BelongsTo<WhatsappConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'whatsapp_conversation_id');
    }

    /**
     * @return BelongsTo<WhatsappMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id');
    }

    /**
     * @return HasMany<PrescriptionReadingItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionReadingItem::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_user_id');
    }
}
