<?php

namespace App\Filament\Resources\SalesVatRetentions\Pages;

use App\Filament\Resources\SalesVatRetentions\SalesVatRetentionResource;
use App\Models\User;
use App\Support\Fiscal\SalesBookAccess;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditSalesVatRetention extends EditRecord
{
    protected static string $resource = SalesVatRetentionResource::class;

    protected static ?string $title = 'Editar comprobante de retención de cliente';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = Auth::user();
        $data['updated_by'] = SalesBookAccess::actorLabel($user instanceof User ? $user : null);

        return $data;
    }
}
