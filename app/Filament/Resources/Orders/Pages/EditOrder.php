<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Support\Orders\OrderTotalsCalculator;
use App\Support\Orders\PartnerOrderDeliverySync;
use App\Support\Orders\PartnerOrderFormMutator;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = auth()->user();
        if ($user instanceof User && $user->isAdministrator()) {
            $data['order_for_partner'] = filled($data['partner_company_id'] ?? null);
        }

        $record = $this->getRecord();
        if ($record instanceof Order) {
            $record->loadMissing('items');
            $itemStates = $record->items->map(fn ($i): array => [
                'product_id' => $i->product_id,
                'quantity' => (float) $i->quantity,
            ])->all();
            $data = array_merge($data, OrderTotalsCalculator::aggregateFromItemStates($itemStates));
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        $record->recalculateTotalsFromItems();
        PartnerOrderDeliverySync::sync($record, auth()->user());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->user()?->email
            ?? auth()->user()?->name
            ?? 'sistema';

        $user = auth()->user();
        if ($user instanceof User && $user->isPartnerCompanyUser() && ! $user->isAdministrator()) {
            $data['partner_company_id'] = (int) $user->partner_company_id;
            $data['client_id'] = null;
        } elseif ($user instanceof User && $user->isAdministrator()) {
            if (! empty($data['partner_company_id'] ?? null)) {
                $data['client_id'] = null;
            } else {
                $data['partner_company_id'] = null;
            }
        } elseif ($user instanceof User) {
            unset($data['partner_company_id']);
        }

        $data = PartnerOrderFormMutator::sanitizeForSave($data, $user);

        unset($data['items']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
