<?php

namespace App\Filament\Resources\FormularyCategories\Pages;

use App\Filament\Resources\FormularyCategories\FormularyCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFormularyCategory extends CreateRecord
{
    protected static string $resource = FormularyCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return FormularyCategoryResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
