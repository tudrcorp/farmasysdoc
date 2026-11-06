<?php

namespace App\Filament\Resources\SupplierReturns\Pages;

use App\Filament\Resources\SupplierReturns\SupplierReturnResource;
use App\Filament\Resources\SupplierReturns\Support\SupplierReturnFormData;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Services\Purchases\SupplierReturnException;
use App\Services\Purchases\SupplierReturnWorkflow;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditSupplierReturn extends EditRecord
{
    protected static string $resource = SupplierReturnResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        return $record instanceof SupplierReturn ? SupplierReturnFormData::fillFromRecord($record, $data) : $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $record instanceof SupplierReturn, 403);

        $parts = SupplierReturnFormData::split($data);

        try {
            return app(SupplierReturnWorkflow::class)->update($record, $parts['header'], $parts['items'], $parts['attachments'], $user);
        } catch (SupplierReturnException $exception) {
            Notification::make()->title('No se pudo guardar la devolución')->body($exception->getMessage())->danger()->persistent()->send();
            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return SupplierReturnResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
