<?php

namespace App\Models;

use App\Support\WhatsappAgent\IsabellaDefaultPrompt;
use App\Support\WhatsappAgent\WhatsappAgentUrls;
use App\Support\WhatsappAgent\WhatsappPhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Configuración del asistente de pedidos por WhatsApp (fila única id = 1).
 *
 * Los tokens se guardan cifrados con la APP_KEY; nunca se muestran completos en el panel.
 */
class WhatsappAgentSetting extends Model
{
    public const DEFAULT_ANALYST_DAYS = [1, 2, 3, 4, 5, 6];

    public const DEFAULT_ANALYST_START = '08:00';

    public const DEFAULT_ANALYST_END = '20:00';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'is_enabled',
        'test_mode',
        'test_phone_numbers',
        'agent_name',
        'ultramsg_instance_url',
        'ultramsg_token',
        'webhook_secret',
        'n8n_webhook_url',
        'agent_api_token',
        'analyst_schedule',
        'message_grouping_seconds',
        'analyst_idle_minutes',
        'prescription_confidence_threshold',
        'system_prompt',
        'suggested_products_notes',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'ultramsg_token',
        'agent_api_token',
        'webhook_secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'test_mode' => 'boolean',
            'test_phone_numbers' => 'array',
            'ultramsg_token' => 'encrypted',
            'agent_api_token' => 'encrypted',
            'analyst_schedule' => 'array',
            'message_grouping_seconds' => 'integer',
            'analyst_idle_minutes' => 'integer',
            'prescription_confidence_threshold' => 'decimal:2',
        ];
    }

    /**
     * Registro único de configuración; lo crea con secretos aleatorios la primera vez.
     */
    public static function current(): self
    {
        /** @var self */
        return self::query()->firstOrCreate(
            ['id' => 1],
            [
                'webhook_secret' => self::newWebhookSecret(),
                'agent_api_token' => self::newAgentApiToken(),
                'analyst_schedule' => [
                    'days' => self::DEFAULT_ANALYST_DAYS,
                    'start' => self::DEFAULT_ANALYST_START,
                    'end' => self::DEFAULT_ANALYST_END,
                ],
            ],
        );
    }

    public static function newWebhookSecret(): string
    {
        return Str::lower(Str::random(40));
    }

    public static function newAgentApiToken(): string
    {
        return 'wa_'.Str::random(48);
    }

    public function agentDisplayName(): string
    {
        return filled($this->agent_name) ? (string) $this->agent_name : 'Isabella';
    }

    public function effectiveSystemPrompt(): string
    {
        return filled($this->system_prompt)
            ? (string) $this->system_prompt
            : IsabellaDefaultPrompt::text($this->agentDisplayName());
    }

    public function hasUltramsgCredentials(): bool
    {
        return filled($this->ultramsg_instance_url) && filled($this->ultramsg_token);
    }

    /**
     * El asistente responde solo si está encendido y tiene a dónde enviar el turno (n8n).
     */
    public function agentCanRespond(): bool
    {
        return $this->is_enabled && filled($this->n8n_webhook_url) && $this->hasUltramsgCredentials();
    }

    /**
     * En modo prueba el asistente solo conversa con los teléfonos de la lista.
     */
    public function allowsAgentFor(string $phone): bool
    {
        if (! $this->test_mode) {
            return true;
        }

        $normalized = WhatsappPhoneNumber::normalize($phone);

        return $normalized !== null && in_array($normalized, $this->normalizedTestPhoneNumbers(), true);
    }

    /**
     * @return list<string>
     */
    public function normalizedTestPhoneNumbers(): array
    {
        $numbers = [];

        foreach ((array) ($this->test_phone_numbers ?? []) as $row) {
            $value = is_array($row) ? ($row['phone'] ?? null) : $row;
            $normalized = WhatsappPhoneNumber::normalize((string) $value);

            if ($normalized !== null) {
                $numbers[] = $normalized;
            }
        }

        return array_values(array_unique($numbers));
    }

    public function agentApiTokenMatches(?string $token): bool
    {
        $expected = (string) ($this->agent_api_token ?? '');

        return $expected !== '' && filled($token) && hash_equals($expected, (string) $token);
    }

    public function webhookUrl(): string
    {
        return WhatsappAgentUrls::ultramsgWebhook((string) $this->webhook_secret);
    }

    /**
     * @return list<int> Días ISO (1 = lunes … 7 = domingo).
     */
    public function analystDays(): array
    {
        $days = $this->analyst_schedule['days'] ?? self::DEFAULT_ANALYST_DAYS;

        return array_values(array_map('intval', is_array($days) ? $days : self::DEFAULT_ANALYST_DAYS));
    }

    public function analystStartTime(): string
    {
        return (string) ($this->analyst_schedule['start'] ?? self::DEFAULT_ANALYST_START);
    }

    public function analystEndTime(): string
    {
        return (string) ($this->analyst_schedule['end'] ?? self::DEFAULT_ANALYST_END);
    }
}
