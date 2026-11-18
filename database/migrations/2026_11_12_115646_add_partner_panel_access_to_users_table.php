<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Acceso al panel de aliados (dale · Aliados): permisos por usuario, invitación por correo,
 * activación (el usuario define su clave) y último ingreso. Los usuarios de aliados que ya
 * existían quedan activados con todos los permisos y con su vínculo en `partner_company_users`.
 * Agrega además los índices que usan el tablero y los listados del panel.
 */
return new class extends Migration
{
    private const ALL_PERMISSIONS = ['dashboard', 'plans_agreements', 'account', 'orders_view', 'orders_create', 'api_docs', 'users_manage'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('partner_permissions')->nullable()->after('partner_user_is_active');
            $table->timestamp('partner_access_sent_at')->nullable()->after('partner_permissions');
            $table->timestamp('partner_activated_at')->nullable()->after('partner_access_sent_at');
            $table->timestamp('partner_last_login_at')->nullable()->after('partner_activated_at');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['partner_company_id', 'created_at'], 'orders_partner_created_index');
            $table->index(['partner_company_id', 'status'], 'orders_partner_status_index');
        });

        Schema::table('historical_of_movements', function (Blueprint $table): void {
            $table->index(['partner_company_id', 'created_at'], 'historical_movements_partner_created_index');
        });

        $now = now();

        DB::table('users')
            ->whereNotNull('partner_company_id')
            ->orderBy('id')
            ->each(function (object $user) use ($now): void {
                DB::table('users')->where('id', $user->id)->update([
                    'partner_permissions' => json_encode(self::ALL_PERMISSIONS),
                    'partner_activated_at' => $now,
                ]);

                $linked = DB::table('partner_company_users')->where('user_id', $user->id)->exists();

                if (! $linked) {
                    DB::table('partner_company_users')->insert([
                        'partner_company_id' => $user->partner_company_id,
                        'user_id' => $user->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('historical_of_movements', function (Blueprint $table): void {
            $table->dropIndex('historical_movements_partner_created_index');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_partner_created_index');
            $table->dropIndex('orders_partner_status_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['partner_permissions', 'partner_access_sent_at', 'partner_activated_at', 'partner_last_login_at']);
        });
    }
};
