<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Support\Maps\GeocodeAddressWithGoogle;
use App\Support\Maps\GoogleDirectionsRoute;
use App\Support\Orders\PartnerOrderDeliverySync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryOrderNavigationController extends Controller
{
    /**
     * Metadatos para navegación: prioriza coordenadas guardadas en el pedido; si no, geocodifica la dirección.
     */
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->isDeliveryUser()) {
            return response()->json(['message' => __('No autorizado.')], 403);
        }

        if (! ctype_digit($id)) {
            return response()->json(['message' => __('Pedido no encontrado.')], 404);
        }

        $orderId = (int) $id;
        $userId = (int) $user->getAuthIdentifier();

        $types = PartnerOrderDeliverySync::appRoutableDeliveryTypes();

        /** @var Order|null $order */
        $order = Order::query()
            ->whereKey($orderId)
            ->where('status', OrderStatus::InProgress)
            ->whereHas('deliveries', static function ($query) use ($userId, $types): void {
                $query
                    ->whereIn('delivery_type', $types)
                    ->where('user_id', $userId)
                    ->where('status', DeliveryStatus::InProgress);
            })
            ->first();

        if ($order === null) {
            return response()->json(['message' => __('Pedido no encontrado o no está asignado a ti.')], 404);
        }

        $addressParts = array_filter([
            $order->delivery_address,
            $order->delivery_city,
            $order->delivery_state,
        ], static fn ($v): bool => filled($v));

        $addressLabel = implode(', ', array_map(static fn ($v): string => trim((string) $v), $addressParts));

        $hasPin = $order->delivery_latitude !== null && $order->delivery_longitude !== null;

        if ($hasPin) {
            $destination = [
                'lat' => (float) $order->delivery_latitude,
                'lng' => (float) $order->delivery_longitude,
                'formatted_address' => $addressLabel !== ''
                    ? $addressLabel
                    : __('Punto de entrega (ubicación en mapa)'),
            ];
        } elseif ($addressLabel !== '') {
            $geo = GeocodeAddressWithGoogle::firstResult($addressLabel);
            $destination = $geo === null ? null : [
                'lat' => $geo['lat'],
                'lng' => $geo['lng'],
                'formatted_address' => $geo['formatted_address'],
            ];
        } else {
            return response()->json([
                'message' => __('Este pedido no tiene dirección ni punto de entrega en mapa.'),
            ], 422);
        }

        $addressOut = $addressLabel;
        if ($addressOut === '' && is_array($destination)) {
            $addressOut = (string) ($destination['formatted_address'] ?? '');
        }

        $route = null;
        if (is_array($destination)) {
            $originLat = $request->query('origin_lat');
            $originLng = $request->query('origin_lng');
            if (is_numeric($originLat) && is_numeric($originLng)) {
                $route = GoogleDirectionsRoute::drivingRoute(
                    (float) $originLat,
                    (float) $originLng,
                    (float) $destination['lat'],
                    (float) $destination['lng'],
                );
            }
        }

        return response()->json([
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'address_label' => $addressOut,
                'destination' => $destination,
                'geocoding_configured' => filled(config('services.google.maps_server_api_key')),
                'destination_from_coordinates' => $hasPin,
                'route' => $route,
            ],
        ]);
    }
}
