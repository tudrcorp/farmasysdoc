<?php

namespace App\Support\Audit;

use App\Enums\ProductTransferStatus;
use App\Models\ProductTransfer;
use App\Models\User;
use App\Services\Audit\AuditLogger;

final class ProductTransferSaleAuditLogger
{
    private const SALE_TRANSFER_TYPE = 'sale_transfer';

    public static function isSaleTransfer(ProductTransfer $transfer): bool
    {
        return $transfer->transfer_type === self::SALE_TRANSFER_TYPE;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $event, string $description, ProductTransfer $transfer, array $properties = []): void
    {
        if (! self::isSaleTransfer($transfer)) {
            return;
        }

        $status = $transfer->status;
        $statusValue = $status instanceof ProductTransferStatus ? $status->value : (string) $status;

        $base = [
            'module' => 'sale_transfer',
            'transfer_code' => $transfer->code,
            'from_branch_id' => $transfer->from_branch_id,
            'to_branch_id' => $transfer->to_branch_id,
            'status' => $statusValue,
        ];

        AuditLogger::record(
            $event,
            $description,
            ProductTransfer::class,
            $transfer->getKey(),
            $transfer->code,
            array_merge($base, $properties),
        );
    }

    public static function logCreated(ProductTransfer $transfer): void
    {
        self::record(
            'sale_transfer_created',
            'Traslado de venta creado · '.$transfer->code,
            $transfer,
            [
                'client_id' => $transfer->client_id,
                'sale_id' => $transfer->sale_id,
            ],
        );
    }

    public static function logViewed(ProductTransfer $transfer): void
    {
        self::record(
            'sale_transfer_viewed',
            'Traslado de venta consultado · '.$transfer->code,
            $transfer,
        );
    }

    public static function logDeliveryTook(ProductTransfer $transfer, User $deliveryUser): void
    {
        self::record(
            'sale_transfer_delivery_took',
            'Traslado de venta tomado por delivery · '.$transfer->code,
            $transfer,
            [
                'delivery_user_id' => $deliveryUser->getKey(),
                'delivery_user_email' => $deliveryUser->email,
            ],
        );
    }

    public static function logCompleted(ProductTransfer $transfer, User $actor, ?int $internalSaleId = null): void
    {
        self::record(
            'sale_transfer_completed',
            'Traslado de venta completado · '.$transfer->code,
            $transfer,
            [
                'internal_sale_id' => $internalSaleId,
                'completed_by' => $transfer->completed_by,
                'actor_user_id' => $actor->getKey(),
            ],
        );
    }

    public static function logAdminStatusChanged(
        ProductTransfer $transfer,
        ProductTransferStatus $from,
        ProductTransferStatus $to,
    ): void {
        self::record(
            'sale_transfer_admin_status_changed',
            'Traslado de venta: cambio de estatus ('.$from->label().' → '.$to->label().') · '.$transfer->code,
            $transfer,
            [
                'previous_status' => $from->value,
                'new_status' => $to->value,
            ],
        );
    }

    public static function logDeleted(ProductTransfer $transfer): void
    {
        self::record(
            'sale_transfer_deleted',
            'Traslado de venta eliminado · '.$transfer->code,
            $transfer,
        );
    }
}
