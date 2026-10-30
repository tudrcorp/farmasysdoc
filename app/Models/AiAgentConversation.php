<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAgentConversation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'title'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AiAgentMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiAgentMessage::class)->orderBy('id');
    }
}
