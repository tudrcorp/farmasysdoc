<?php

use App\Models\Rol;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private string $menuKey = 'otp_codes';

    /**
     * @var list<string>
     */
    private array $managerRoleNames = ['GERENCIA', 'GERENTE'];

    public function up(): void
    {
        Rol::query()->each(function (Rol $rol): void {
            if (! is_array($rol->allowed_menu_items)) {
                return;
            }

            if (! in_array(mb_strtoupper((string) $rol->name), $this->managerRoleNames, true)) {
                return;
            }

            $rol->allowed_menu_items = array_values(array_unique([...$rol->allowed_menu_items, $this->menuKey]));
            $rol->save();
        });
    }

    public function down(): void
    {
        Rol::query()->each(function (Rol $rol): void {
            if (! is_array($rol->allowed_menu_items)) {
                return;
            }

            $rol->allowed_menu_items = array_values(array_diff($rol->allowed_menu_items, [$this->menuKey]));
            $rol->save();
        });
    }
};
