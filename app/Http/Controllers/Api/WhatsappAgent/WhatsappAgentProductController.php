<?php

namespace App\Http\Controllers\Api\WhatsappAgent;

use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsappAgent\SearchWhatsappProductsRequest;
use App\Services\WhatsappAgent\WhatsappProductAvailabilitySearch;
use Illuminate\Http\JsonResponse;

class WhatsappAgentProductController extends Controller
{
    public function search(SearchWhatsappProductsRequest $request, WhatsappProductAvailabilitySearch $search): JsonResponse
    {
        $result = $search->search((string) $request->validated('q'), (int) ($request->validated('limit') ?? 5));

        $anyAvailable = collect($result['results'])->contains(fn (array $row): bool => $row['available_anywhere']);

        return response()->json([
            ...$result,
            'note' => match (true) {
                $result['results'] === [] => 'No está en nuestro inventario. Díselo al cliente con claridad y ofrécele que el equipo se lo consigue; si acepta, agrégalo con agregar_al_pedido (product_id 0) y nota «Por conseguir».',
                ! $anyAvailable => 'Está en el catálogo pero sin existencia en ninguna sucursal. Díselo con claridad y ofrécele que el equipo se lo consigue (o una alternativa con el mismo principio activo y concentración).',
                default => 'Dile al cliente en qué sucursales está disponible y que puede retirarlo en la sucursal que prefiera (el equipo lo lleva a esa sucursal) o recibirlo por delivery. Precios por sucursal ya calculados en USD y Bs.; existencia solo como «disponible» o «quedan pocas unidades».',
            },
        ]);
    }
}
