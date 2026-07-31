<?php

namespace App\Filament\Resources\Hr\Loans\Pages;

use App\Filament\Resources\Hr\Loans\HrLoanResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditHrLoan extends EditRecord
{
    protected static string $resource = HrLoanResource::class;

    protected static ?string $title = 'Editar préstamo';

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
        if (($data['installment_mode'] ?? null) === 'percentage') {
            $data['fixed_installment_usd'] = null;
            $data['installments_count'] = null;
        }

        if (($data['installment_mode'] ?? null) === 'fixed') {
            $data['salary_percentage'] = null;
        }

        return $data;
    }
}
