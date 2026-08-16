<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('hr_payroll_receipts', 'month')) {
            Schema::table('hr_payroll_receipts', function (Blueprint $table): void {
                $table->unsignedTinyInteger('month')->default(1)->after('year');
            });
        }

        $receipts = DB::table('hr_payroll_receipts')->get();

        foreach ($receipts as $receipt) {
            $month = null;

            if (isset($receipt->payroll_period_id) && filled($receipt->payroll_period_id)) {
                $periodDate = DB::table('payroll_periods')
                    ->where('id', $receipt->payroll_period_id)
                    ->value('period_date');

                if (filled($periodDate)) {
                    $month = (int) Carbon::parse((string) $periodDate)->month;
                }
            }

            DB::table('hr_payroll_receipts')
                ->where('id', $receipt->id)
                ->update(['month' => $month ?? (int) now()->month]);
        }

        $keepIds = DB::table('hr_payroll_receipts')
            ->selectRaw('MAX(id) as id')
            ->groupBy('employee_id', 'year', 'month')
            ->pluck('id');

        DB::table('hr_payroll_receipts')
            ->whereNotIn('id', $keepIds)
            ->delete();

        Schema::table('hr_payroll_receipts', function (Blueprint $table): void {
            $table->dropForeign(['payroll_period_id']);
            $table->dropForeign(['payroll_line_id']);
            $table->dropUnique(['payroll_period_id', 'employee_id']);
            $table->dropColumn(['payroll_period_id', 'payroll_line_id']);
            $table->unique(['employee_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll_receipts', function (Blueprint $table): void {
            $table->dropUnique(['employee_id', 'year', 'month']);
            $table->foreignId('payroll_period_id')->nullable()->constrained('payroll_periods')->nullOnDelete();
            $table->foreignId('payroll_line_id')->nullable()->constrained('payroll_lines')->nullOnDelete();
            $table->dropColumn('month');
        });
    }
};
