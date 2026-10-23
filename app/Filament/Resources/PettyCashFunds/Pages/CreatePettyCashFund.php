<?php

namespace App\Filament\Resources\PettyCashFunds\Pages;

use App\Enums\PettyCashCurrency;
use App\Enums\PettyCashFundStatus;
use App\Filament\Resources\PettyCashFunds\Concerns\HasPettyCashFundFormPage;
use App\Filament\Resources\PettyCashFunds\PettyCashFundResource;
use App\Models\User;
use App\Services\Finance\PettyCashAmountResolver;
use App\Services\Finance\PettyCashOpenFundGuard;
use App\Support\Finance\NotifyAdministratorsPettyCashRequested;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class CreatePettyCashFund extends CreateRecord
{
    use HasPettyCashFundFormPage;

    protected static string $resource = PettyCashFundResource::class;

    protected static ?string $title = 'Solicitar caja chica';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = PettyCashFundResource::currentUser();
        if (! $user instanceof User) {
            Notification::make()->title('Debe iniciar sesión.')->danger()->send();
            $this->halt();

            return $data;
        }

        try {
            app(PettyCashOpenFundGuard::class)->assertCanRequestForBranch((int) ($data['branch_id'] ?? 0));
        } catch (RuntimeException $e) {
            Notification::make()->title('No se puede solicitar')->body($e->getMessage())->danger()->send();
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

        $data['requested_by'] = $user->id;
        $data['status'] = PettyCashFundStatus::PendingApproval->value;
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

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        try {
            app(NotifyAdministratorsPettyCashRequested::class)->notify(
                $record,
                PettyCashFundResource::currentUser(),
            );
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar WhatsApp de solicitud de caja chica', [
                'petty_cash_fund_id' => $record->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
