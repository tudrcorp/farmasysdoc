<?php

namespace App\Filament\Resources\SalesVatRetentions\Pages;

use App\Filament\Resources\SalesVatRetentions\SalesVatRetentionResource;
use App\Models\User;
use App\Support\Fiscal\SalesBookAccess;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSalesVatRetention extends CreateRecord
{
    protected static string $resource = SalesVatRetentionResource::class;

    protected static ?string $title = 'Registrar comprobante de retención de cliente';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();
        $data['created_by'] = SalesBookAccess::actorLabel($user instanceof User ? $user : null);

        return $data;
    }
}
