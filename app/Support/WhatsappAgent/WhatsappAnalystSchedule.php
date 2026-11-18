<?php

namespace App\Support\WhatsappAgent;

use App\Models\WhatsappAgentSetting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Horario en que hay analistas atendiendo (por defecto lunes a sábado de 8:00 a. m. a 8:00 p. m., hora de Caracas).
 */
final class WhatsappAnalystSchedule
{
    private const TIMEZONE = 'America/Caracas';

    public function __construct(private readonly WhatsappAgentSetting $setting) {}

    public static function current(): self
    {
        return new self(WhatsappAgentSetting::current());
    }

    public function isWithinHours(?CarbonInterface $moment = null): bool
    {
        $local = CarbonImmutable::instance($moment ?? now())->setTimezone(self::TIMEZONE);

        if (! in_array($local->dayOfWeekIso, $this->setting->analystDays(), true)) {
            return false;
        }

        $start = $local->setTimeFromTimeString($this->setting->analystStartTime());
        $end = $local->setTimeFromTimeString($this->setting->analystEndTime());

        return $local->greaterThanOrEqualTo($start) && $local->lessThan($end);
    }

    /**
     * Próximo inicio de jornada (para decirle al cliente cuándo le escriben).
     */
    public function nextShiftStart(?CarbonInterface $moment = null): CarbonImmutable
    {
        $local = CarbonImmutable::instance($moment ?? now())->setTimezone(self::TIMEZONE);

        for ($offset = 0; $offset <= 7; $offset++) {
            $day = $local->addDays($offset);

            if (! in_array($day->dayOfWeekIso, $this->setting->analystDays(), true)) {
                continue;
            }

            $start = $day->setTimeFromTimeString($this->setting->analystStartTime());

            if ($start->greaterThan($local)) {
                return $start;
            }
        }

        return $local->addDay()->setTimeFromTimeString($this->setting->analystStartTime());
    }

    public function describe(): string
    {
        $names = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];
        $days = $this->setting->analystDays();
        sort($days);

        $dayText = $days === [1, 2, 3, 4, 5, 6]
            ? 'lunes a sábado'
            : implode(', ', array_map(static fn (int $day): string => $names[$day] ?? (string) $day, $days));

        return $dayText.' de '.$this->setting->analystStartTime().' a '.$this->setting->analystEndTime();
    }
}
