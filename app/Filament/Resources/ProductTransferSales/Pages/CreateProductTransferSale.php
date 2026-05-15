<?php

namespace App\Filament\Resources\ProductTransferSales\Pages;

use App\Filament\Resources\ProductTransferSales\ProductTransferSaleResource;
use App\Filament\Resources\ProductTransferSales\Schemas\ProductTransferSaleForm;
use App\Models\Client;
use App\Models\ProductTransfer;
use App\Models\User;
use App\Support\Audit\ProductTransferSaleAuditLogger;
use App\Support\Deliveries\SaleTransferDeliveryCreator;
use App\Support\Filament\BranchAuthScope;
use App\Support\ProductTransfers\NotifyAdministratorsOnManagerTransferRequested;
use App\Support\ProductTransferStockValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateProductTransferSale extends CreateRecord
{
    protected static string $resource = ProductTransferSaleResource::class;

    public function getHeading(): string|Htmlable|null
    {
        return 'Crear traslado de venta';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = Auth::user();
        $label = $actor !== null
            ? (filled($actor->email) ? (string) $actor->email : (string) ($actor->name ?? 'usuario'))
            : 'sistema';

        $data['status'] = 'pending';
        $data['transfer_type'] = 'sale_transfer';
        $data['client_id'] = self::resolveClientIdFromFormData($data);

        if (! ProductTransferSaleForm::userMaySelectFromBranchOnSaleTransfer()) {
            $lockedFrom = BranchAuthScope::suggestedBranchIdForOperationalForm();
            if ($lockedFrom !== null) {
                $data['from_branch_id'] = $lockedFrom;
            }
        }

        $fromBranchId = (int) ($data['from_branch_id'] ?? 0);
        ProductTransferStockValidator::assertSufficientStockAtBranch($fromBranchId, $data['items'] ?? []);

        $data = array_merge(
            collect($data)->except([
                'items',
                'quick_client_name',
                'quick_client_document',
                'quick_client_phone',
            ])->all(),
            [
                'created_by' => $label,
                'updated_by' => $label,
                'code' => 'PENDING-'.str_replace('-', '', (string) Str::uuid()),
            ],
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $record->forceFill([
            'code' => ProductTransfer::automaticSaleTransferCodeForId((int) $record->getKey()),
        ])->save();

        $record->refresh();
        $record->loadMissing(['items.product', 'client', 'fromBranch', 'toBranch']);
        SaleTransferDeliveryCreator::syncFromTransfer($record);

        ProductTransferSaleAuditLogger::logCreated($record);

        try {
            $actor = Auth::user();
            app(NotifyAdministratorsOnManagerTransferRequested::class)->notify(
                $record->fresh(['items.product', 'fromBranch', 'toBranch']),
                $actor instanceof User ? $actor : null,
            );
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar WhatsApp de traslado de venta registrado', [
                'transfer_id' => $record->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function resolveClientIdFromFormData(array $data): ?int
    {
        $existingClientId = $data['client_id'] ?? null;
        if (filled($existingClientId)) {
            return (int) $existingClientId;
        }

        $quickName = trim((string) ($data['quick_client_name'] ?? ''));
        $quickDoc = trim((string) ($data['quick_client_document'] ?? ''));
        $quickPhone = trim((string) ($data['quick_client_phone'] ?? ''));
        $anyQuick = $quickName !== '' || $quickDoc !== '' || $quickPhone !== '';

        if (! $anyQuick) {
            return null;
        }

        if ($quickName === '' || $quickDoc === '' || $quickPhone === '') {
            throw ValidationException::withMessages([
                'quick_client_name' => 'Para registrar cliente nuevo complete nombre, cédula y teléfono.',
                'quick_client_document' => 'Para registrar cliente nuevo complete nombre, cédula y teléfono.',
                'quick_client_phone' => 'Para registrar cliente nuevo complete nombre, cédula y teléfono.',
            ]);
        }

        $existingByDoc = Client::query()
            ->where('document_number', $quickDoc)
            ->first();

        if ($existingByDoc instanceof Client) {
            return (int) $existingByDoc->id;
        }

        return (int) self::createClientFromTransferQuickForm($quickName, $quickDoc, $quickPhone)->id;
    }

    private static function createClientFromTransferQuickForm(string $name, string $documentNumber, string $phone): Client
    {
        $user = Auth::user();
        $actor = filled($user?->email)
            ? (string) $user->email
            : (filled($user?->name) ? (string) $user->name : 'traslado_venta');

        return Client::query()->create([
            'name' => $name,
            'document_type' => 'CC',
            'document_number' => $documentNumber,
            'email' => 'traslado-venta+'.Str::uuid()->toString().'@mostrador.invalid',
            'phone' => $phone,
            'address' => '—',
            'city' => '—',
            'state' => '—',
            'country' => 'Colombia',
            'status' => 'active',
            'customer_discount' => 0,
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);
    }
}
