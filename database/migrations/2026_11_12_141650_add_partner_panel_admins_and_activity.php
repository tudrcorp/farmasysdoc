<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Panel dale · Aliados:
 * - users.partner_is_admin: administrador del panel (lo designa Farmadoc). Tiene todos los permisos
 *   y es el único que gestiona usuarios. Reemplaza el permiso «users_manage», que deja de asignarse.
 * - partner_plan_affiliates: quién dio de baja al afiliado, por qué y desde dónde.
 * - partner_panel_activities: bitácora de la gestión del aliado (usuarios, permisos, afiliados),
 *   visible para sus administradores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('partner_is_admin')->default(false)->after('partner_user_is_active');
        });

        Schema::table('partner_plan_affiliates', function (Blueprint $table): void {
            $table->foreignId('deactivated_by')->nullable()->after('deactivated_at')->constrained('users')->nullOnDelete();
            $table->string('deactivated_via', 20)->nullable()->after('deactivated_by');
            $table->string('deactivation_reason', 500)->nullable()->after('deactivated_via');
        });

        Schema::create('partner_panel_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_company_id')->constrained('partner_companies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_type', 20);
            $table->string('action', 60);
            $table->string('description', 500);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['partner_company_id', 'created_at']);
            $table->index(['partner_company_id', 'action']);
        });

        DB::table('users')
            ->whereNotNull('partner_company_id')
            ->orderBy('id')
            ->each(function (object $user): void {
                $permissions = json_decode((string) ($user->partner_permissions ?? '[]'), true);
                $permissions = is_array($permissions) ? $permissions : [];
                $isAdmin = in_array('users_manage', $permissions, true);

                DB::table('users')->where('id', $user->id)->update([
                    'partner_is_admin' => $isAdmin,
                    'partner_permissions' => json_encode(array_values(array_diff($permissions, ['users_manage']))),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_panel_activities');

        Schema::table('partner_plan_affiliates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deactivated_by');
            $table->dropColumn(['deactivated_via', 'deactivation_reason']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('partner_is_admin');
        });
    }
};
