<?php

namespace App\Models;

use App\Enums\WhatsappMessageAuthor;
use App\Enums\WhatsappMessageType;
use Database\Factories\WhatsappMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WhatsappMessage extends Model
{
    /** @use HasFactory<WhatsappMessageFactory> */
    use HasFactory;

    public const MEDIA_DISK = 'local';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'whatsapp_conversation_id',
        'author',
        'type',
        'body',
        'provider_message_id',
        'media_url',
        'media_path',
        'media_mime',
        'transcription',
        'latitude',
        'longitude',
        'sent_by_user_id',
        'delivery_failed',
        'delivery_status',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'author' => WhatsappMessageAuthor::class,
            'type' => WhatsappMessageType::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'delivery_failed' => 'boolean',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<WhatsappConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'whatsapp_conversation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * @return HasOne<PrescriptionReading, $this>
     */
    public function prescriptionReading(): HasOne
    {
        return $this->hasOne(PrescriptionReading::class);
    }

    public function isPendingDelivery(): bool
    {
        return $this->delivery_status === self::STATUS_PENDING;
    }

    public function hasStoredMedia(): bool
    {
        return filled($this->media_path);
    }

    /**
     * Texto que entiende el asistente: el cuerpo, la transcripción de la nota de voz o la ubicación.
     */
    public function textForAgent(): string
    {
        $parts = [];

        if (filled($this->body)) {
            $parts[] = (string) $this->body;
        }

        if (filled($this->transcription)) {
            $parts[] = '[Nota de voz] '.$this->transcription;
        }

        if ($this->type === WhatsappMessageType::Location && $this->latitude !== null) {
            $parts[] = '[Ubicación compartida] '.$this->latitude.', '.$this->longitude;
        }

        if ($parts === [] && $this->type instanceof WhatsappMessageType && $this->type !== WhatsappMessageType::Text) {
            $parts[] = '['.$this->type->label().']';
        }

        return implode("\n", $parts);
    }
}
