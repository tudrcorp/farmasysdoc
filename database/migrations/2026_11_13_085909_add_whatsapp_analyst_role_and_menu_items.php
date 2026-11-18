<?php

use App\Models\Rol;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const ANALYST_ROLE = 'ANALISTA WHATSAPP';

    /**
     * @var list<string>
     */
    private array $analystKeys = [
        'dashboard',
        'whatsapp_inbox',
        'clients',
    ];

    /**
     * @var list<string>
     */
    private array $adminKeys = [
        'whatsapp_inbox',
        'whatsapp_agent_settings',
    ];

    public function up(): void
    {
        $analyst = Rol::query()->where('name', self::ANALYST_ROLE)->first();

        if (! $analyst instanceof Rol) {
            Rol::query()->create([
                'name' => self::ANALYST_ROLE,
                'description' => 'Atiende los pedidos que llegan por WhatsApp (Isabella): toma conversaciones, valida récipes y crea pedidos.',
                'is_active' => true,
                'allowed_menu_items' => $this->analystKeys,
            ]);
        } else {
            $this->appendKeys(self::ANALYST_ROLE, $this->analystKeys);
        }

        $this->appendKeys('ADMINISTRADOR', $this->adminKeys);
    }

    public function down(): void
    {
        Rol::query()->where('name', self::ANALYST_ROLE)->delete();
        $this->removeKeys('ADMINISTRADOR', $this->adminKeys);
    }

    /**
     * @param  list<string>  $keys
     */
    private function appendKeys(string $roleName, array $keys): void
    {
        $rol = Rol::query()->where('name', $roleName)->first();
        if (! $rol instanceof Rol) {
            return;
        }

        $items = is_array($rol->allowed_menu_items) ? $rol->allowed_menu_items : [];
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
