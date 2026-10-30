<?php

namespace App\Services\AiAgent;

use App\Models\AiAgentMessage;

/**
 * Tope de gasto mensual del agente. Es una estimación por tokens: el cobro real lo hace Google.
 */
class AiAgentBudget
{
    public function limitUsd(): float
    {
        return (float) config('ai_agent.monthly_budget_usd');
    }

    public function spentThisMonthUsd(): float
    {
        return (float) AiAgentMessage::query()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('cost_usd');
    }

    public function isExhausted(): bool
    {
        return $this->spentThisMonthUsd() >= $this->limitUsd();
    }

    public function isNearLimit(): bool
    {
        return $this->spentThisMonthUsd() >= $this->limitUsd() * (float) config('ai_agent.budget_warning_ratio');
    }

    public function costUsd(int $inputTokens, int $outputTokens): float
    {
        $today = now()->toDateString();
        $prices = collect(config('ai_agent.prices'))->last(fn (array $price): bool => $price['from'] <= $today);

        return round(($inputTokens * $prices['input'] + $outputTokens * $prices['output']) / 1_000_000, 6);
    }
}
