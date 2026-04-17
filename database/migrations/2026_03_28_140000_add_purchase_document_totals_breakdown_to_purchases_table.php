<?php

use App\Models\Purchase;
use App\Support\Purchases\PurchaseDocumentTotals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->decimal('subtotal_exempt_amount', 14, 2)->default(0)->comment('Suma bases líneas sin IVA');
            $table->decimal('subtotal_taxable_amount', 14, 2)->default(0)->comment('Suma bases líneas gravadas');
            $table->decimal('document_discount_percent', 5, 2)->default(0)->comment('Descuento global sobre subtotal sumatoria');
            $table->decimal('document_discount_amount', 14, 2)->default(0)->comment('Monto del descuento documento');
            $table->decimal('net_exempt_after_document_discount', 14, 2)->default(0)->comment('Base exenta tras desc. documento');
            $table->decimal('net_taxable_after_document_discount', 14, 2)->default(0)->comment('Base gravada tras desc. documento');
        });

        Purchase::query()->with(['items' => fn ($q) => $q->orderBy('line_number')->orderBy('id')])->chunkById(100, function ($purchases): void {
            foreach ($purchases as $purchase) {
                $state = $purchase->items->map(fn ($line) => $line->toDocumentTotalsState())->values()->all();
                $h = PurchaseDocumentTotals::documentHeaderWithDocumentDiscount($state, 0.0);
                $purchase->forceFill([
                    'subtotal_exempt_amount' => $h['subtotal_exempt_amount'],
                    'subtotal_taxable_amount' => $h['subtotal_taxable_amount'],
                    'document_discount_percent' => $h['document_discount_percent'],
                    'document_discount_amount' => $h['document_discount_amount'],
                    'net_exempt_after_document_discount' => $h['net_exempt_after_document_discount'],
                    'net_taxable_after_document_discount' => $h['net_taxable_after_document_discount'],
                    'subtotal' => $h['subtotal'],
                    'discount_total' => $h['discount_total'],
                    'tax_total' => $h['tax_total'],
                    'total' => $h['total'],
                ])->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropColumn([
                'subtotal_exempt_amount',
                'subtotal_taxable_amount',
                'document_discount_percent',
                'document_discount_amount',
                'net_exempt_after_document_discount',
                'net_taxable_after_document_discount',
            ]);
        });
    }
};
