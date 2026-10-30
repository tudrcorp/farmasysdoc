<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Parámetros de la liquidación (una sola fila). Parten de los mínimos de la LOTTT
 * y se pueden subir si la empresa paga más.
 */
class SeveranceSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'utilities_days_per_year',
        'vacation_base_days',
        'vacation_max_days',
        'vacation_bonus_base_days',
        'vacation_bonus_max_days',
    ];

    /**
     * @var array<string, int>
     */
    protected $attributes = [
        'utilities_days_per_year' => 30,
        'vacation_base_days' => 15,
        'vacation_max_days' => 30,
        'vacation_bonus_base_days' => 15,
        'vacation_bonus_max_days' => 30,
    ];

    public static function current(): self
    {
        return self::query()->oldest('id')->first() ?? self::query()->create();
    }

    /**
     * @return array{utilities_days_per_year: int, vacation_base_days: int, vacation_max_days: int, vacation_bonus_base_days: int, vacation_bonus_max_days: int}
     */
    public function toParameters(): array
    {
        return array_map('intval', $this->only($this->fillable));
    }
}
