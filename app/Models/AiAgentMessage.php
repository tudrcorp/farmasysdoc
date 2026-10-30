<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mensaje del chat. `tool_calls` guarda lo que consultó el agente: es la auditoría de cada respuesta.
 */
class AiAgentMessage extends Model
{
    public const string ROLE_USER = 'user';

    public const string ROLE_ASSISTANT = 'assistant';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ai_agent_conversation_id',
        'role',
        'content',
        'tool_calls',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'failed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'cost_usd' => 'decimal:6',
            'failed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<AiAgentConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiAgentConversation::class, 'ai_agent_conversation_id');
    }

    /**
     * @return HasMany<AiAgentReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(AiAgentReport::class);
    }
}
