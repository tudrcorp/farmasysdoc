<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->boolean('self_applied')->default(false)->after('used_url')->index()
                ->comment('La usó quien la pidió con el check «Usar OTP», sin autorización de un tercero');
            $table->timestamp('self_applied_at')->nullable()->after('self_applied');
            $table->string('self_applied_ip', 45)->nullable()->after('self_applied_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropIndex(['self_applied']);
            $table->dropColumn(['self_applied', 'self_applied_at', 'self_applied_ip']);
        });
    }
};
