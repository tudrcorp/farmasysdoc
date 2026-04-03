<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_transfer_items')) {
            Schema::create('product_transfer_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_transfer_id')->constrained('product_transfers')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->decimal('quantity', 12, 3);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('product_transfers') && Schema::hasColumn('product_transfers', 'product_id')) {
            $rows = DB::table('product_transfers')->select(['id', 'product_id', 'quantity'])->get();
            foreach ($rows as $row) {
                if ($row->product_id === null) {
                    continue;
                }
                $exists = DB::table('product_transfer_items')
                    ->where('product_transfer_id', $row->id)
                    ->where('product_id', $row->product_id)
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('product_transfer_items')->insert([
                    'product_transfer_id' => $row->id,
                    'product_id' => $row->product_id,
                    'quantity' => $row->quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('product_transfers', function (Blueprint $table): void {
                $table->dropColumn(['product_id', 'quantity']);
            });
        }

        if (! Schema::hasColumn('product_transfers', 'total_transfer_cost')) {
            Schema::table('product_transfers', function (Blueprint $table): void {
                $table->decimal('total_transfer_cost', 12, 2)->nullable();
            });
        }

        if (! Schema::hasColumn('product_transfers', 'completed_by')) {
            Schema::table('product_transfers', function (Blueprint $table): void {
                $table->string('completed_by')->nullable();
            });
        }

        if (! Schema::hasColumn('product_transfers', 'completed_at')) {
            Schema::table('product_transfers', function (Blueprint $table): void {
                $table->timestamp('completed_at')->nullable();
            });
        }

        if (! Schema::hasColumn('product_transfers', 'sale_id')) {
            Schema::table('product_transfers', function (Blueprint $table): void {
                $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_transfers', 'sale_id')) {
            Schema::table('product_transfers', function (Blueprint $table): void {
                $table->dropForeign(['sale_id']);
                $table->dropColumn('sale_id');
            });
        }

        if (Schema::hasColumn('product_transfers', 'completed_at')) {
            Schema::table('product_transfers', function (Blueprint $table): void {
                $table->dropColumn('completed_at');
            });
        }

        if (Schema::hasColumn('product_transfers', 'completed_by')) {
            Schema::table('product_transfers', function (Blueprint $table): void {
                $table->dropColumn('completed_by');
            });
        }

        if (Schema::hasColumn('product_transfers', 'total_transfer_cost')) {
            Schema::table('product_transfers', function (Blueprint $table): void {
                $table->dropColumn('total_transfer_cost');
            });
        }

        if (Schema::hasTable('product_transfer_items') && ! Schema::hasColumn('product_transfers', 'product_id')) {
            Schema::table('product_transfers', function (Blueprint $table): void {
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->integer('quantity')->nullable();
            });

            $items = DB::table('product_transfer_items')
                ->orderBy('id')
                ->get()
                ->groupBy('product_transfer_id');

            foreach ($items as $transferId => $group) {
                $first = $group->first();
                DB::table('product_transfers')
                    ->where('id', $transferId)
                    ->update([
                        'product_id' => $first->product_id,
                        'quantity' => (int) $first->quantity,
                    ]);
            }

            Schema::dropIfExists('product_transfer_items');
        }
    }
};
