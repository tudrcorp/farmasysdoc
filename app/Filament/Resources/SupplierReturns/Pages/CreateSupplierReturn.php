<?php

namespace App\Filament\Resources\SupplierReturns\Pages;

use App\Filament\Resources\SupplierReturns\SupplierReturnResource;
use App\Filament\Resources\SupplierReturns\Support\SupplierReturnFormData;
use App\Models\User;
use App\Services\Purchases\SupplierReturnException;
use App\Services\Purchases\SupplierReturnWorkflow;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateSupplierReturn extends CreateRecord
{
    protected static string $resource = SupplierReturnResource::class;

    protected static bool $canCreateAnother = false;

    protected static ?string $title = 'Registrar devolución';

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $parts = SupplierReturnFormData::split($data);

        try {
            return app(SupplierReturnWorkflow::class)->register($parts['header'], $parts['items'], $parts['attachments'], $user);
        } catch (SupplierReturnException $exception) {
            Notification::make()->title('No se pudo registrar la devolución')->body($exception->getMessage())->danger()->persistent()->send();
            $this->halt();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Devolución registrada. Se avisó a Administración para revisarla.';
    }

    protected function getRedirectUrl(): string
    {
        return SupplierReturnResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
