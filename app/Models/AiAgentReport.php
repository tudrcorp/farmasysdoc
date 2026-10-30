<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Reporte generado en el chat. Guarda las filas tal como salieron de la consulta.
 */
class AiAgentReport extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['ai_agent_message_id', 'user_id', 'title', 'columns', 'rows'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'rows' => 'array',
        ];
    }
}
