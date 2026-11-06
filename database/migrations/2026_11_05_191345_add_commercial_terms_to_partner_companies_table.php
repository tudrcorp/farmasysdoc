<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separa el límite de crédito aprobado (`credit_limit_usd`) del saldo disponible
     * (`assigned_credit_limit`) y pasa el contacto único a `partner_company_contacts`.
     */
    public function up(): void
    {
        Schema::table('partner_companies', function (Blueprint $table) {
            $table->string('client_type', 40)->nullable()->index()->after('tax_id')
                ->comment('Valor de App\Enums\PartnerCompanyClientType');
            $table->string('payment_modality', 20)->nullable()->index()->after('client_type')
                ->comment('Valor de App\Enums\PartnerCompanyPaymentModality');
            $table->decimal('credit_limit_usd', 14, 2)->nullable()->after('payment_modality')
                ->comment('Límite de crédito aprobado (USD); el saldo disponible vive en assigned_credit_limit');
        });

        $consumedByCompany = DB::table('historical_of_movements')
            ->whereNotNull('partner_company_id')
            ->select('partner_company_id', DB::raw('SUM(total_cost) as consumed'))
            ->groupBy('partner_company_id')
            ->pluck('consumed', 'partner_company_id');

        DB::table('partner_companies')
            ->whereNotNull('assigned_credit_limit')
            ->orderBy('id')
            ->each(function (object $company) use ($consumedByCompany): void {
                $ceiling = round((float) $company->assigned_credit_limit + (float) ($consumedByCompany[$company->id] ?? 0), 2);

                DB::table('partner_companies')->where('id', $company->id)->update([
                    'credit_limit_usd' => $ceiling,
                    'payment_modality' => 'credito',
                ]);
            });

        $now = now();

        DB::table('partner_companies')
            ->where(function ($query): void {
                $query->whereNotNull('contact_name')
                    ->orWhereNotNull('contact_email')
                    ->orWhereNotNull('contact_phone');
            })
            ->orderBy('id')
            ->each(function (object $company) use ($now): void {
                DB::table('partner_company_contacts')->insert([
                    'partner_company_id' => $company->id,
                    'name' => filled($company->contact_name) ? $company->contact_name : 'Contacto principal',
                    'email' => $company->contact_email,
                    'phone' => $company->contact_phone,
                    'is_responsible' => true,
                    'sort' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('partner_companies', function (Blueprint $table) {
            $table->dropIndex(['client_type']);
            $table->dropIndex(['payment_modality']);
            $table->dropColumn(['client_type', 'payment_modality', 'credit_limit_usd']);
        });
    }
};
