<?php

namespace App\Support\Inventory;

use Illuminate\Support\Facades\Log;
use Throwable;

final class InventoryAuditTrace
{
    public static function info(string $event, array $context = []): void
    {
        self::write('info', $event, $context);
    }

    public static function error(string $event, array $context = []): void
    {
        self::write('error', $event, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function write(string $level, string $event, array $context): void
    {
        $payload = [
            'event' => $event,
            'request_id' => self::requestId(),
            'user_id' => auth()->id(),
            ...$context,
        ];

        try {
            Log::channel('inventory_audit')->{$level}('inventory_audit.'.$event, $payload);
        } catch (Throwable) {
            Log::{$level}('inventory_audit.'.$event, $payload);
        }
    }

    public static function requestId(): string
    {
        $request = request();
        $existing = $request->attributes->get('inventory_audit_rid');
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $id = bin2hex(random_bytes(6));
        $request->attributes->set('inventory_audit_rid', $id);

        return $id;
    }
}
