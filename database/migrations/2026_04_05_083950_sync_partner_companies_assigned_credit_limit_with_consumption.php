<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deja `assigned_credit_limit` como saldo disponible: si ya hay movimientos, iguala al
 * `remaining_credit` del último registro (idempotente si el ledger ya mantenía la tabla al día).
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('partner_companies')->pluck('id');

        foreach ($ids as $id) {
            $last = DB::table('historical_of_movements')
                ->where('partner_company_id', $id)
                ->orderByDesc('id')
                ->first();

            if ($last === null) {
                continue;
            }

            if ($last->remaining_credit !== null) {
                $newBalance = max(0, round((float) $last->remaining_credit, 2));
            } else {
                $row = DB::table('partner_companies')->where('id', $id)->first();
                if ($row === null || $row->assigned_credit_limit === null) {
                    continue;
                }

                $consumed = (float) DB::table('historical_of_movements')
                    ->where('partner_company_id', $id)
                    ->sum('total_cost');

                $newBalance = round(max(0, (float) $row->assigned_credit_limit - $consumed), 2);
            }

            DB::table('partner_companies')
                ->where('id', $id)
                ->update(['assigned_credit_limit' => $newBalance]);
        }
    }

    /**
     * No reversible sin conocer el estado previo.
     */
    public function down(): void
    {
        //
    }
};
