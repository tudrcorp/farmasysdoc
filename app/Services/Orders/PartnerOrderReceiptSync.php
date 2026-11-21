<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderPartnerReceipt;
use App\Models\PartnerUncoveredMedicineConnection;
use App\Services\Partners\FarmadocOrderReceiptClient;
use App\Services\Partners\FarmadocOrderReceiptException;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Trae de Tu Dr. Group los pedidos marcados como registrados en inventario y guarda la nota de recibido.
 */
class PartnerOrderReceiptSync
{
    private const ENV_CURSOR_KEY = 'partner-farmadoc-orders:cursor';

    public function __construct(private FarmadocOrderReceiptClient $client) {}

    public function syncAll(): int
    {
        $lock = Cache::lock('partner-order-receipts-sync', 90);

        if (! $lock->get()) {
            return 0;
        }

        try {
            $stored = 0;

            foreach ($this->sources() as $source) {
                $stored += $this->syncSource($source['token'], $source['connection']);
            }

            return $stored;
        } finally {
            $lock->release();
        }
    }

    public function refreshOrder(Order $order): void
    {
        if ($order->partner_company_id === null || blank($order->order_number)) {
            return;
        }

        if (! Cache::add('partner-order-receipt-order:'.$order->getKey(), 1, 20)) {
            $order->loadMissing('partnerReceipt');

            return;
        }

        $token = $this->tokenForPartner((int) $order->partner_company_id) ?? $this->anyToken();

        if ($token === null) {
            return;
        }

        try {
            $payload = $this->client->find($token, (string) $order->order_number);
        } catch (FarmadocOrderReceiptException) {
            return;
        }

        if (is_array($payload)) {
            $this->store($payload, $token);
        }

        $order->unsetRelation('partnerReceipt');
        $order->load('partnerReceipt');
    }

    /**
     * @param  array{token: string, connection: PartnerUncoveredMedicineConnection|null}  $source
     */
    private function syncSource(string $token, ?PartnerUncoveredMedicineConnection $connection): int
    {
        $startedAt = now();
        $cursor = $connection instanceof PartnerUncoveredMedicineConnection
            ? $connection->receipt_synced_at
            : $this->envCursor();
        $stored = 0;
        $page = 1;

        try {
            do {
                $result = $this->client->listRegistered($token, $cursor, $page);

                foreach ($result['rows'] as $row) {
                    if ($this->store($row, $token)) {
                        $stored++;
                    }
                }

                $lastPage = $result['last_page'];
                $page++;
            } while ($page <= $lastPage && $page <= 20);
        } catch (FarmadocOrderReceiptException $exception) {
            $this->rememberError($connection, $exception->getMessage());

            return $stored;
        }

        $this->rememberCursor($connection, $startedAt);

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function store(array $payload, string $token): bool
    {
        $number = trim((string) ($payload['numero'] ?? ''));
        $status = trim((string) ($payload['estatus'] ?? ''));
        $reception = is_array($payload['recepcion'] ?? null) ? $payload['recepcion'] : null;

        if ($number === '' || ($status !== 'registrado-inventario' && $reception === null)) {
            return false;
        }

        $order = Order::query()->where('order_number', $number)->first();

        if (! $order instanceof Order) {
            return false;
        }

        $externalUpdatedAt = $this->date($payload['actualizado_en'] ?? null);
        $receiptNumber = is_array($reception) ? $this->text($reception['nota_recibido'] ?? null) : null;
        $receipt = OrderPartnerReceipt::query()->firstOrNew(['order_id' => $order->getKey()]);
        $pdfIsStale = blank($receipt->pdf_path)
            || $receipt->receipt_number !== $receiptNumber
            || ($externalUpdatedAt instanceof CarbonInterface && ! $externalUpdatedAt->equalTo($receipt->external_updated_at));

        $receipt->fill([
            'partner_company_id' => $order->partner_company_id,
            'external_number' => $number,
            'external_status' => $status !== '' ? $status : 'registrado-inventario',
            'external_status_label' => $this->text($payload['estatus_etiqueta'] ?? null) ?? 'Registrado en inventario',
            'service_order_number' => $this->text($payload['orden_servicio'] ?? null),
            'delivery_note_number' => $this->text($payload['nota_entrega'] ?? null),
            'receipt_number' => $receiptNumber,
            'received_at' => is_array($reception) ? $this->date($reception['registrado_en'] ?? null) : null,
            'received_by' => is_array($reception) ? $this->text($reception['recibido_por'] ?? null) : null,
            'location' => is_array($reception) ? $this->text($reception['ubicacion'] ?? null) : null,
            'is_complete' => is_array($reception) && (bool) ($reception['completo'] ?? false),
            'observations' => is_array($reception) ? $this->text($reception['observaciones'] ?? null) : null,
            'medications' => is_array($reception) ? $this->medications($reception['medicamentos'] ?? null) : [],
            'external_updated_at' => $externalUpdatedAt,
        ])->save();

        if ($receiptNumber !== null && $pdfIsStale) {
            $this->storePdf($receipt, $token, $number);
        }

        return true;
    }

    private function storePdf(OrderPartnerReceipt $receipt, string $token, string $orderNumber): void
    {
        try {
            $binary = $this->client->downloadNote($token, $orderNumber);
        } catch (FarmadocOrderReceiptException) {
            return;
        }

        if ($binary === null) {
            return;
        }

        $path = 'partner-order-receipts/'.$receipt->order_id.'/nota-recibido.pdf';
        Storage::disk('local')->put($path, $binary);
        $receipt->forceFill(['pdf_path' => $path])->save();
    }

    /**
     * @return list<array{nombre: string, codigo_barras: ?string, sku: ?string, cantidad_pedida: ?string, cantidad_recibida: ?string}>
     */
    private function medications(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $lines = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $lines[] = [
                'nombre' => $this->text($row['nombre'] ?? null) ?? 'Medicamento',
                'codigo_barras' => $this->text($row['codigo_barras'] ?? null),
                'sku' => $this->text($row['sku'] ?? null),
                'cantidad_pedida' => $this->text($row['cantidad_pedida'] ?? null),
                'cantidad_recibida' => $this->text($row['cantidad_recibida'] ?? null),
            ];
        }

        return $lines;
    }

    /**
     * @return list<array{token: string, connection: PartnerUncoveredMedicineConnection|null}>
     */
    private function sources(): array
    {
        $sources = [];
        $seen = [];

        PartnerUncoveredMedicineConnection::query()
            ->whereNotNull('token')
            ->orderBy('id')
            ->each(function (PartnerUncoveredMedicineConnection $connection) use (&$sources, &$seen): void {
                $token = trim((string) $connection->token);

                if ($token === '' || ! $this->rememberToken($seen, $token)) {
                    return;
                }

                $sources[] = ['token' => $token, 'connection' => $connection];
            });

        $envToken = trim((string) config('partner_uncovered_medicines.orders_token'));

        if ($envToken !== '' && $this->rememberToken($seen, $envToken)) {
            $sources[] = ['token' => $envToken, 'connection' => null];
        }

        return $sources;
    }

    public function hasToken(): bool
    {
        return $this->sources() !== [];
    }

    private function tokenForPartner(int $partnerCompanyId): ?string
    {
        $connection = PartnerUncoveredMedicineConnection::query()
            ->where('partner_company_id', $partnerCompanyId)
            ->first();

        $token = trim((string) ($connection?->token ?? ''));

        return $token !== '' ? $token : null;
    }

    private function anyToken(): ?string
    {
        $source = $this->sources()[0] ?? null;

        return is_array($source) ? $source['token'] : null;
    }

    /**
     * @param  array<string, true>  $seen
     */
    private function rememberToken(array &$seen, string $token): bool
    {
        $fingerprint = hash('sha256', $token);

        if (isset($seen[$fingerprint])) {
            return false;
        }

        $seen[$fingerprint] = true;

        return true;
    }

    private function rememberCursor(?PartnerUncoveredMedicineConnection $connection, CarbonInterface $startedAt): void
    {
        if ($connection instanceof PartnerUncoveredMedicineConnection) {
            $connection->forceFill([
                'receipt_synced_at' => $startedAt,
                'receipt_sync_error' => null,
            ])->save();

            return;
        }

        Cache::forever(self::ENV_CURSOR_KEY, $startedAt->toIso8601String());
    }

    private function rememberError(?PartnerUncoveredMedicineConnection $connection, string $message): void
    {
        if (! $connection instanceof PartnerUncoveredMedicineConnection) {
            return;
        }

        $connection->forceFill([
            'receipt_sync_error' => $message,
        ])->save();
    }

    private function envCursor(): ?CarbonInterface
    {
        $value = Cache::get(self::ENV_CURSOR_KEY);

        return is_string($value) && $value !== '' ? $this->date($value) : null;
    }

    private function date(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function text(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
