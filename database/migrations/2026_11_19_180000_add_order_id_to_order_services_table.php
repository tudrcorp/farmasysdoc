<?php

use App\Models\Order;
use App\Models\OrderService;
use App\Models\PartnerCompany;
use App\Services\Orders\ExternalOrderServiceLinker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Liga cada orden de servicio al pedido que la originó.
     * El pedido IC-2757 ya estaba creado sin orden de servicio: se completa aquí.
     */
    public function up(): void
    {
        if (Schema::hasTable('order_services') && ! Schema::hasColumn('order_services', 'order_id')) {
            Schema::table('order_services', function (Blueprint $table): void {
                $table->foreignId('order_id')
                    ->nullable()
                    ->unique()
                    ->after('branch_id')
                    ->constrained('orders')
                    ->nullOnDelete()
                    ->comment('Pedido de Farmadoc asociado a esta orden de servicio');
            });
        }

        $this->attachExistingIntegracorpOrder();
    }

    public function down(): void
    {
        if (Schema::hasTable('order_services') && Schema::hasColumn('order_services', 'order_id')) {
            Schema::table('order_services', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('order_id');
            });
        }
    }

    private function attachExistingIntegracorpOrder(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('order_services', 'order_id')) {
            return;
        }

        $order = Order::query()->where('order_number', 'IC-2757-20261119173757-9893')->first();

        if ($order === null || OrderService::query()->where('order_id', $order->getKey())->exists()) {
            return;
        }

        if ($order->partner_company_id === null && filled($order->partner_company_code)) {
            $partnerId = PartnerCompany::query()->where('code', $order->partner_company_code)->value('id');

            if ($partnerId !== null) {
                $order->update(['partner_company_id' => $partnerId]);
                $order->refresh();
            }
        }

        if ($order->partner_company_id === null) {
            return;
        }

        app(ExternalOrderServiceLinker::class)->create($order, [
            'patient_name' => 'CUESTA BERRIO HERMINIO',
            'patient_document' => '23782362',
            'patient_phone' => '04163034074',
            'patient_email' => 'herminiocuesta246@gmail.com',
            'diagnosis' => "1- DISCOPATIA LUMBAR A PRECISAR \n2- LITIASIS RENAL A PRECISAR \n3- GASTROENTERITIS AGUDA DE ETIOLOGIA A PRECISAR",
            'external_reference' => 'REF-23711',
            'service_type' => 'medicamentos',
            'status' => 'en-proceso',
            'priority' => 'media',
        ], 'api:IntegraCorp');
    }
};
