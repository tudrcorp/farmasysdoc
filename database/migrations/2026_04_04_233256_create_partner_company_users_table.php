<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            if (! Schema::hasColumn('users', 'partner_company_code')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->string('partner_company_code')
                        ->nullable()
                        ->after('partner_company_id')
                        ->comment('Código de la compañía aliada (denormalizado desde partner_companies.code)');
                });
            }

            if (! Schema::hasColumn('users', 'partner_user_is_active')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->boolean('partner_user_is_active')
                        ->default(true)
                        ->after('partner_company_code')
                        ->comment('Solo aplica a usuarios con partner_company_id: puede iniciar sesión en el panel como aliado');
                });
            }
        }

        if (Schema::hasTable('partner_company_users')) {
            return;
        }

        Schema::create('partner_company_users', function (Blueprint $table): void {
            $table->id()->comment('Vinculación formal usuario ↔ compañía aliada');
            $table->foreignId('partner_company_id')
                ->constrained('partner_companies')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['partner_company_id', 'user_id'], 'partner_company_users_company_user_index');
        });

        if (Schema::hasTable('users') && Schema::hasTable('partner_companies')) {
            DB::table('users')
                ->whereNotNull('partner_company_id')
                ->orderBy('id')
                ->chunkById(100, function ($users): void {
                    foreach ($users as $user) {
                        DB::table('partner_company_users')->insertOrIgnore([
                            'partner_company_id' => $user->partner_company_id,
                            'user_id' => $user->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $code = DB::table('partner_companies')
                            ->where('id', $user->partner_company_id)
                            ->value('code');
                        if (filled($code)) {
                            DB::table('users')
                                ->where('id', $user->id)
                                ->update(['partner_company_code' => $code]);
                        }
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_company_users');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'partner_user_is_active')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('partner_user_is_active');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'partner_company_code')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('partner_company_code');
            });
        }
    }
};
