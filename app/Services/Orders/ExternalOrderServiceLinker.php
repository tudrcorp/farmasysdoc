<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderService;
use Illuminate\Support\Str;

/**
 * Abre la orden de servicio de un pedido externo y la deja ligada a ese pedido.
 */
final class ExternalOrderServiceLinker
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function create(Order $order, array $context, string $actor): OrderService
    {
        if ($order->partner_company_id === null) {
            throw new \InvalidArgumentException('El pedido no tiene compañía aliada para abrir la orden de servicio.');
        }

        $order->loadMissing('items.product');

        $service = OrderService::query()->create([
            'service_order_number' => 'ORD-TEMP-'.(string) Str::uuid(),
            'partner_company_id' => $order->partner_company_id,
            'client_id' => $order->client_id,
            'branch_id' => $order->branch_id,
            'order_id' => $order->getKey(),
            'status' => $this->status($context['status'] ?? null),
            'priority' => $this->priority($context['priority'] ?? null),
            'service_type' => $this->limit($context['service_type'] ?? null, 255) ?? 'medicamentos',
            'authorization_reference' => null,
            'external_reference' => $this->limit($context['external_reference'] ?? null, 255) ?? $order->order_number,
            'patient_name' => $this->limit($context['patient_name'] ?? null, 255),
            'patient_document' => $this->limit($context['patient_document'] ?? null, 255),
            'patient_phone' => $this->phone($context['patient_phone'] ?? null),
            'patient_email' => $this->email($context['patient_email'] ?? null),
            'ordered_at' => $order->created_at ?? now(),
            'scheduled_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'subtotal' => 0,
            'tax_total' => 0,
            'discount_total' => 0,
            'total' => 0,
            'diagnosis' => $this->text($context['diagnosis'] ?? null),
            'notes' => $order->notes,
            'items' => $this->medicationItems($order, $context['items'] ?? null),
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        $service->update([
            'service_order_number' => OrderService::formatServiceOrderNumber($service->getKey()),
        ]);

        return $service->fresh() ?? $service;
    }

    /**
     * @return list<array{name: string, barcode: string, indicacion: string}>
     */
    private function medicationItems(Order $order, mixed $provided): array
    {
        if (is_array($provided) && $provided !== []) {
            $rows = [];

            foreach ($provided as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '') {
                    continue;
                }

                $rows[] = [
                    'name' => $name,
                    'barcode' => trim((string) ($row['barcode'] ?? '')),
                    'indicacion' => trim((string) ($row['indicacion'] ?? '')),
                ];
            }

            if ($rows !== []) {
                return $rows;
            }
        }

        return $order->items
            ->map(function (OrderItem $item): array {
                $name = trim((string) ($item->product_name_snapshot ?? ''));
                $quantity = rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.');

                return [
                    'name' => $name !== '' ? $name : 'Medicamento',
                    'barcode' => trim((string) ($item->product?->barcode ?? '')),
                    'indicacion' => 'Cantidad: '.($quantity !== '' ? $quantity : '1'),
                ];
            })
            ->values()
            ->all();
    }

    private function status(mixed $value): string
    {
        $status = is_string($value) ? strtolower(trim($value)) : '';

        if ($status === 'en proceso') {
            $status = 'en-proceso';
        }

        return in_array($status, ['borrador', 'aprobada', 'en-proceso', 'finalizada', 'cancelada'], true)
            ? $status
            : 'en-proceso';
    }

    private function priority(mixed $value): string
    {
        $priority = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($priority, ['baja', 'media', 'alta', 'urgente'], true)
            ? $priority
            : 'media';
    }

    private function phone(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits === '' ? null : $this->limit($digits, 40);
    }

    private function email(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $email = strtolower(trim($value));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $this->limit($email, 255) : null;
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function limit(mixed $value, int $length): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_strlen($value) > $length ? mb_substr($value, 0, $length) : $value;
    }
}
