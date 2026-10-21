<?php

namespace App\Filament\Resources\PettyCashFunds\Pages;

use App\Enums\PettyCashCurrency;
use App\Filament\Resources\PettyCashFunds\Concerns\HasPettyCashFundFormPage;
use App\Filament\Resources\PettyCashFunds\PettyCashFundResource;
use App\Services\Finance\PettyCashAmountResolver;
use App\Services\Finance\PettyCashOpenFundGuard;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use InvalidArgumentException;
use RuntimeException;

class EditPettyCashFund extends EditRecord
{
    use HasPettyCashFundFormPage;

    protected static string $resource = PettyCashFundResource::class;

    protected static ?string $title = 'Editar solicitud de caja chica';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        try {
            app(PettyCashOpenFundGuard::class)->assertCanRequestForBranch(
                (int) ($data['branch_id'] ?? 0),
                (int) $this->getRecord()->getKey(),
            );
        } catch (RuntimeException $e) {
            Notification::make()->title('No se puede guardar')->body($e->getMessage())->danger()->send();
            $this->halt();

            return $data;
        }

        $currency = PettyCashCurrency::tryFrom((string) ($data['requested_currency'] ?? ''))
            ?? PettyCashCurrency::Usd;

        try {
            $resolved = app(PettyCashAmountResolver::class)->resolve(
                $currency,
                (float) ($data['requested_amount'] ?? 0),
                $data['requested_at'] ?? now(),
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Monto inválido')->body($e->getMessage())->danger()->send();
            $this->halt();

            return $data;
        }

        $data['amount_usd'] = $resolved['amount_usd'];
        $data['amount_ves'] = $resolved['amount_ves'];
        $data['bcv_ves_per_usd'] = $resolved['bcv_ves_per_usd'];
        $data['remaining_usd'] = $resolved['amount_usd'];
        $data['remaining_ves'] = $resolved['amount_ves'];

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
