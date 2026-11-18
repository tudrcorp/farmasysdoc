<?php

namespace App\Filament\Resources\FormularyCategories\Pages;

use App\Filament\Resources\FormularyCategories\FormularyCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFormularyCategory extends EditRecord
{
    protected static string $resource = FormularyCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return FormularyCategoryResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
