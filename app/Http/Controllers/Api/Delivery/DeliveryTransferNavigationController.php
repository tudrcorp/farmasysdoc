<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Enums\ProductTransferStatus;
use App\Http\Controllers\Controller;
use App\Models\ProductTransfer;
use App\Models\User;
use App\Support\Maps\GeocodeAddressWithGoogle;
use App\Support\Maps\GoogleDirectionsRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryTransferNavigationController extends Controller
{
    /**
     * Destino del traslado: sucursal receptora (dirección geocodificada si aplica).
     */
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->isDeliveryUser()) {
            return response()->json(['message' => __('No autorizado.')], 403);
        }

        if (! ctype_digit($id)) {
            return response()->json(['message' => __('Traslado no encontrado.')], 404);
        }

        $userId = (int) $user->getAuthIdentifier();

        /** @var ProductTransfer|null $transfer */
        $transfer = ProductTransfer::query()
            ->whereKey((int) $id)
            ->where('status', ProductTransferStatus::InProgress)
            ->where('delivery_user_id', $userId)
            ->with('toBranch')
            ->first();

        if ($transfer === null) {
            return response()->json(['message' => __('Traslado no encontrado o no está asignado a ti.')], 404);
        }

        $branch = $transfer->toBranch;
        $addressParts = array_filter([
            $branch?->address,
            $branch?->city,
            $branch?->state,
        ], static fn ($v): bool => filled($v));

        $addressLabel = implode(', ', array_map(static fn ($v): string => trim((string) $v), $addressParts));

        if ($addressLabel === '') {
            return response()->json([
                'message' => __('La sucursal destino no tiene dirección configurada.'),
            ], 422);
        }

        $destination = GeocodeAddressWithGoogle::firstResult($addressLabel);

        $destinationPayload = $destination === null ? null : [
            'lat' => $destination['lat'],
            'lng' => $destination['lng'],
            'formatted_address' => $destination['formatted_address'],
        ];

        $route = null;
        if (is_array($destinationPayload)) {
            $originLat = $request->query('origin_lat');
            $originLng = $request->query('origin_lng');
            if (is_numeric($originLat) && is_numeric($originLng)) {
                $route = GoogleDirectionsRoute::drivingRoute(
                    (float) $originLat,
                    (float) $originLng,
                    (float) $destinationPayload['lat'],
                    (float) $destinationPayload['lng'],
                );
            }
        }

        return response()->json([
            'data' => [
                'transfer_id' => $transfer->id,
                'code' => $transfer->code,
                'order_number' => $transfer->code,
                'address_label' => $addressLabel,
                'destination' => $destinationPayload,
                'geocoding_configured' => filled(config('services.google.maps_server_api_key')),
                'route' => $route,
            ],
        ]);
    }
}
