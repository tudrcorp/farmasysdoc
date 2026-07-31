<?php

use App\Models\Rol;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $adminKeys = [
        'hr_employees',
        'hr_assignments',
        'hr_deductions',
        'hr_loans',
        'hr_payroll',
    ];

    /**
     * @var list<string>
     */
    private array $gerenciaKeys = [
        'hr_loans',
    ];

    public function up(): void
    {
        $this->appendKeys('ADMINISTRADOR', $this->adminKeys);
        $this->appendKeys('GERENCIA', $this->gerenciaKeys);
    }

    public function down(): void
    {
        $this->removeKeys('ADMINISTRADOR', $this->adminKeys);
        $this->removeKeys('GERENCIA', $this->gerenciaKeys);
    }

    /**
     * @param  list<string>  $keys
     */
    private function appendKeys(string $roleName, array $keys): void
    {
        $rol = Rol::query()->where('name', $roleName)->first();
        if (! $rol instanceof Rol || ! is_array($rol->allowed_menu_items)) {
            return;
        }

        $items = $rol->allowed_menu_items;
        foreach ($keys as $key) {
            if (! in_array($key, $items, true)) {
                $items[] = $key;
            }
        }

        $rol->allowed_menu_items = $items;
        $rol->save();
    }

    /**
     * @param  list<string>  $keys
     */
    private function removeKeys(string $roleName, array $keys): void
    {
        $rol = Rol::query()->where('name', $roleName)->first();
        if (! $rol instanceof Rol || ! is_array($rol->allowed_menu_items)) {
            return;
        }

        $rol->allowed_menu_items = array_values(array_filter(
            $rol->allowed_menu_items,
            static fn (mixed $key): bool => ! in_array($key, $keys, true),
        ));
        $rol->save();
    }
};
