<?php

namespace App\Filament\Resources\Hr\Employees\Pages;

use App\Filament\Resources\Hr\Assignments\HrAssignmentResource;
use App\Filament\Resources\Hr\Deductions\HrDeductionResource;
use App\Filament\Resources\Hr\Employees\EmployeeResource;
use App\Filament\Resources\Hr\Loans\HrLoanResource;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();

        return $record instanceof Employee
            ? $record->fullName()
            : 'Detalle del empleado';
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $record = $this->getRecord();
        if (! $record instanceof Employee) {
            return null;
        }

        $record->loadMissing('branch');

        $parts = array_filter([
            'C.I. '.$record->national_id,
            $record->branch?->name,
            $record->is_active ? 'Activo' : 'Inactivo',
        ]);

        return implode(' · ', $parts);
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $model = $this->getRecord();
        if ($model instanceof Employee) {
            $model->load([
                'branch',
                'assignments' => fn ($q) => $q->latest('id'),
                'deductions' => fn ($q) => $q->latest('id'),
                'loans' => fn ($q) => $q->latest('id'),
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        $employee = $this->getRecord();
        $employeeId = $employee instanceof Employee ? $employee->getKey() : null;

        return [
            Action::make('newAssignment')
                ->label('Asignación')
                ->icon(Heroicon::PlusCircle)
                ->color('gray')
                ->url(fn (): string => HrAssignmentResource::getUrl('create').'?employee_id='.$employeeId),
            Action::make('newDeduction')
                ->label('Deducción')
                ->icon(Heroicon::MinusCircle)
                ->color('gray')
                ->url(fn (): string => HrDeductionResource::getUrl('create').'?employee_id='.$employeeId),
            Action::make('newLoan')
                ->label('Préstamo')
                ->icon(Heroicon::Banknotes)
                ->color('gray')
                ->url(fn (): string => HrLoanResource::getUrl('create').'?employee_id='.$employeeId),
            EditAction::make()
                ->label('Editar')
                ->icon(Heroicon::PencilSquare),
        ];
    }
}
