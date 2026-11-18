<?php

namespace App\Models;

use App\Support\WhatsappAgent\WhatsappPhoneNumber;
use Database\Factories\WhatsappContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Persona que escribe al WhatsApp de pedidos. Se enlaza con el cliente de la caja ({@see Client}) por cédula.
 */
class WhatsappContact extends Model
{
    /** @use HasFactory<WhatsappContactFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'phone',
        'profile_name',
        'full_name',
        'document_type',
        'document_number',
        'client_id',
        'client_data_mismatch',
        'client_data_mismatch_note',
        'last_message_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'client_data_mismatch' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<WhatsappConversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsappConversation::class);
    }

    public function displayName(): string
    {
        if (filled($this->full_name)) {
            return (string) $this->full_name;
        }

        if (filled($this->profile_name)) {
            return (string) $this->profile_name;
        }

        return $this->localPhone();
    }

    /**
     * Teléfono en formato local venezolano (04121234567), como lo guarda la caja.
     */
    public function localPhone(): string
    {
        return WhatsappPhoneNumber::toLocal((string) $this->phone);
    }

    public function formattedDocument(): ?string
    {
        if (blank($this->document_number)) {
            return null;
        }

        return mb_strtoupper((string) ($this->document_type ?: 'V')).'-'.$this->document_number;
    }
}
