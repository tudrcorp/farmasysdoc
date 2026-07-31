<?php

namespace App\Filament\Resources\Hr\Deductions\Pages;

use App\Filament\Resources\Hr\Deductions\HrDeductionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHrDeduction extends EditRecord
{
    protected static string $resource = HrDeductionResource::class;

    protected static ?string $title = 'Editar deducción';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
