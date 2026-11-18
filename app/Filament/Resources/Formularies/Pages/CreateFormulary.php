<?php

namespace App\Filament\Resources\Formularies\Pages;

use App\Filament\Resources\Formularies\FormularyResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateFormulary extends CreateRecord
{
    protected static string $resource = FormularyResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return FormularyResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
