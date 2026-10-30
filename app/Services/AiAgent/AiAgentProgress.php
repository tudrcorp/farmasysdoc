<?php

namespace App\Services\AiAgent;

use Closure;
use Illuminate\Support\Str;

/**
 * Avances en vivo del agente. Limita la frecuencia del texto porque cada envío
 * vuelve a convertir todo el Markdown acumulado.
 */
final class AiAgentProgress
{
    /**
     * @var array<string, string>
     */
    private const array TOOL_LABELS = [
        'sales_summary' => 'Consultando ventas…',
        'top_products' => 'Revisando los productos…',
        'low_stock' => 'Revisando el inventario…',
        'expiring_lots' => 'Revisando vencimientos…',
        'finance_snapshot' => 'Revisando cuentas por pagar y cobrar…',
        'list_tables' => 'Buscando los datos…',
        'describe_table' => 'Buscando los datos…',
        'run_sql' => 'Consultando la base de datos…',
        'create_report' => 'Generando el reporte…',
    ];

    private string $text = '';

    private float $lastSentAt = 0.0;

    public function __construct(
        private Closure $sendAnswer,
        private Closure $sendStatus,
        private float $minIntervalSeconds = 0.08,
    ) {}

    public function onTool(string $tool): void
    {
        ($this->sendStatus)(self::TOOL_LABELS[$tool] ?? 'Consultando…');
    }

    public function onText(string $fragment): void
    {
        if ($this->text === '') {
            ($this->sendStatus)('');
        }

        $this->text .= $fragment;

        if (microtime(true) - $this->lastSentAt >= $this->minIntervalSeconds) {
            $this->lastSentAt = microtime(true);
            ($this->sendAnswer)((string) Str::markdown($this->text, ['html_input' => 'escape', 'allow_unsafe_links' => false]));
        }
    }

    /**
     * El texto previo a una consulta no es la respuesta final.
     */
    public function resetText(): void
    {
        $this->text = '';
        ($this->sendAnswer)('');
        ($this->sendStatus)('Pensando…');
    }
}
