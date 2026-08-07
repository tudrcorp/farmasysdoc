<?php

namespace App\Services\Sales;

use App\Models\Client;
use App\Models\ClientDiscountGroup;
use Illuminate\Support\Facades\DB;

class ClientCommercialDiscountAssigner
{
    /**
     * Asigna descuento individual y desvincula al cliente de cualquier grupo.
     */
    public function assignIndividual(Client $client, float $percent): void
    {
        $percent = max(0.0, min(100.0, round($percent, 2)));

        DB::transaction(function () use ($client, $percent): void {
            $client->discountGroups()->detach();
            $client->forceFill([
                'customer_discount' => $percent,
            ])->save();
        });
    }

    /**
     * Quita el descuento individual del cliente.
     */
    public function clearIndividual(Client $client): void
    {
        $client->forceFill([
            'customer_discount' => 0,
        ])->save();
    }

    /**
     * Sincroniza clientes del grupo. Un cliente solo puede pertenecer a un grupo
     * y no puede tener descuento individual a la vez (se limpia al asociar).
     *
     * @param  list<int>  $clientIds
     */
    public function syncGroupClients(ClientDiscountGroup $group, array $clientIds): void
    {
        $clientIds = array_values(array_unique(array_filter(
            array_map('intval', $clientIds),
            static fn (int $id): bool => $id > 0,
        )));

        DB::transaction(function () use ($group, $clientIds): void {
            if ($clientIds !== []) {
                DB::table('client_discount_group_client')
                    ->whereIn('client_id', $clientIds)
                    ->where('client_discount_group_id', '!=', $group->id)
                    ->delete();

                Client::query()
                    ->whereIn('id', $clientIds)
                    ->update(['customer_discount' => 0]);
            }

            $group->clients()->sync($clientIds);
        });
    }
}
