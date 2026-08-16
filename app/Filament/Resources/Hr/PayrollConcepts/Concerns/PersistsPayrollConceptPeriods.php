<?php

namespace App\Filament\Resources\Hr\PayrollConcepts\Concerns;

use App\Filament\Resources\Hr\PayrollConcepts\Schemas\HrPayrollConceptForm;
use App\Models\HrPayrollConcept;

trait PersistsPayrollConceptPeriods
{
    /**
     * @var list<int|string>
     */
    private array $selectedPayrollPeriodIds = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractPeriodIds($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractPeriodIds($data);
    }

    protected function afterCreate(): void
    {
        $this->persistSelectedPeriods();
    }

    protected function afterSave(): void
    {
        $this->persistSelectedPeriods();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function extractPeriodIds(array $data): array
    {
        $ids = $data['payroll_period_ids'] ?? [];
        $this->selectedPayrollPeriodIds = is_array($ids) ? array_values($ids) : [];
        unset($data['payroll_period_ids']);

        return $data;
    }

    private function persistSelectedPeriods(): void
    {
        $record = $this->record;

        if ($record instanceof HrPayrollConcept) {
            HrPayrollConceptForm::persistSelectedPeriods($record, [
                'payroll_period_ids' => $this->selectedPayrollPeriodIds,
            ]);
        }
    }
}
