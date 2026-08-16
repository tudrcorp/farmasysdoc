<?php

use App\Models\Rol;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $replacedKeys = [
        'hr_assignments',
        'hr_deductions',
    ];

    public function up(): void
    {
        Rol::query()->each(function (Rol $rol): void {
            if (! is_array($rol->allowed_menu_items)) {
                return;
            }

            $items = $rol->allowed_menu_items;
            $hadReplaced = count(array_intersect($items, $this->replacedKeys)) > 0
                || in_array('hr_employees', $items, true)
                || in_array('hr_payroll', $items, true);

            $items = array_values(array_filter(
                $items,
                fn (mixed $key): bool => ! in_array($key, $this->replacedKeys, true),
            ));

            if ($hadReplaced && ! in_array('hr_payroll_concepts', $items, true)) {
                $items[] = 'hr_payroll_concepts';
            }

            $rol->allowed_menu_items = $items;
            $rol->save();
        });
    }

    public function down(): void
    {
        Rol::query()->each(function (Rol $rol): void {
            if (! is_array($rol->allowed_menu_items)) {
                return;
            }

            $hadConcepts = in_array('hr_payroll_concepts', $items, true);

            $items = array_values(array_filter(
                $items,
                fn (mixed $key): bool => $key !== 'hr_payroll_concepts',
            ));

            if ($hadConcepts) {
                foreach ($this->replacedKeys as $key) {
                    if (! in_array($key, $items, true)) {
                        $items[] = $key;
                    }
                }
            }

            $rol->allowed_menu_items = $items;
            $rol->save();
        });
    }
};
