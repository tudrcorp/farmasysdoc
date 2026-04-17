<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Enums\ProductTransferStatus;
use App\Http\Controllers\Controller;
use App\Models\ProductTransfer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryTransferTakeController extends Controller
{
    /**
     * El repartidor toma un traslado pendiente (misma lógica que en Filament).
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

        $transferId = (int) $id;
        $userId = (int) $user->getAuthIdentifier();

        $payload = DB::transaction(function () use ($transferId, $userId, $user): array {
            /** @var ProductTransfer|null $transfer */
            $transfer = ProductTransfer::query()
                ->whereKey($transferId)
                ->lockForUpdate()
                ->first();

            if ($transfer === null) {
                return ['error' => 'not_found'];
            }

            if ($transfer->status === ProductTransferStatus::InProgress && (int) $transfer->delivery_user_id === $userId) {
                return [
                    'ok' => true,
                    'idempotent' => true,
                    'transfer' => $this->transferSummary($transfer),
                ];
            }

            if ($transfer->delivery_user_id !== null && (int) $transfer->delivery_user_id !== $userId) {
                return ['error' => 'taken'];
            }

            if ($transfer->status !== ProductTransferStatus::Pending) {
                return ['error' => 'not_pending'];
            }

            $actor = filled($user->email) ? (string) $user->email : (string) ($user->name ?? 'usuario');

            $transfer->forceFill([
                'status' => ProductTransferStatus::InProgress,
                'delivery_user_id' => $userId,
                'in_progress_at' => now(),
                'updated_by' => $actor,
            ])->save();

            return [
                'ok' => true,
                'idempotent' => false,
                'transfer' => $this->transferSummary($transfer),
            ];
        });

        if (($payload['error'] ?? null) === 'not_found') {
            return response()->json(['message' => __('Traslado no encontrado.')], 404);
        }

        if (($payload['error'] ?? null) === 'taken') {
            return response()->json(['message' => __('Otro repartidor ya tomó este traslado.')], 409);
        }

        if (($payload['error'] ?? null) === 'not_pending') {
            return response()->json(['message' => __('Este traslado ya no está pendiente.')], 409);
        }

        return response()->json([
            'message' => __('Traslado asignado. Buen viaje.'),
            'data' => $payload['transfer'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transferSummary(ProductTransfer $transfer): array
    {
        return [
            'id' => $transfer->id,
            'code' => $transfer->code,
            'status' => $transfer->status->value,
            'status_label' => $transfer->status->label(),
        ];
    }
}
