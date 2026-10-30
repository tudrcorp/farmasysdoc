<?php

use App\Models\Rol;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $allKeys = [
        'hr_exits',
        'hr_settlements',
        'hr_severance_parameters',
    ];

    public function up(): void
    {
        Rol::query()->each(function (Rol $rol): void {
            if (! is_array($rol->allowed_menu_items)) {
                return;
            }

            $grantedKeys = match (true) {
                in_array('hr_employees', $rol->allowed_menu_items, true) => $this->allKeys,
                mb_strtoupper((string) $rol->name) === 'GERENCIA' => ['hr_exits'],
                default => [],
            };

            $rol->allowed_menu_items = array_values(array_unique([...$rol->allowed_menu_items, ...$grantedKeys]));
            $rol->save();
        });
    }

    public function down(): void
    {
        Rol::query()->each(function (Rol $rol): void {
            if (! is_array($rol->allowed_menu_items)) {
                return;
            }

            $rol->allowed_menu_items = array_values(array_diff($rol->allowed_menu_items, $this->allKeys));
            $rol->save();
        });
    }
};
