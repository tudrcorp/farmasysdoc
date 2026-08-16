<?php

namespace App\Filament\Resources\Hr\Employees\Pages;

use App\Filament\Resources\Hr\Assignments\HrAssignmentResource;
use App\Filament\Resources\Hr\Deductions\HrDeductionResource;
use App\Filament\Resources\Hr\Employees\Actions\ClearEmployeePortalPasswordAction;
use App\Filament\Resources\Hr\Employees\Actions\RequestEmployeeFileEnrollmentAction;
use App\Filament\Resources\Hr\Employees\EmployeeResource;
use App\Filament\Resources\Hr\Loans\HrLoanResource;
use App\Models\Employee;
use App\Models\HrAssignment;
use App\Models\HrDeduction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
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
            RequestEmployeeFileEnrollmentAction::make(),
            ClearEmployeePortalPasswordAction::make(),
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
            ActionGroup::make([
                $this->deleteAssignmentHeaderAction(),
                $this->deleteDeductionHeaderAction(),
            ])
                ->label('Eliminar')
                ->icon(Heroicon::Trash)
                ->color('danger')
                ->button()
                ->visible(fn (): bool => $this->employeeHasAssignments() || $this->employeeHasDeductions()),
            EditAction::make()
                ->label('Editar')
                ->icon(Heroicon::PencilSquare),
        ];
    }

    private function deleteAssignmentHeaderAction(): Action
    {
        return Action::make('deleteAssignment')
            ->label('Eliminar asignación')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->visible(fn (): bool => $this->employeeHasAssignments())
            ->form([
                Select::make('assignment_id')
                    ->label('Asignación')
                    ->options(fn (): array => $this->assignmentDeleteOptions())
                    ->required()
                    ->native(false)
                    ->searchable(),
            ])
            ->requiresConfirmation()
            ->modalHeading('Eliminar asignación')
            ->modalDescription('Se quitará de la ficha. Los periodos ya calculados no cambian.')
            ->modalSubmitActionLabel('Eliminar')
            ->action(function (array $data): void {
                $assignment = $this->employeeOwnedAssignment((int) ($data['assignment_id'] ?? 0));

                if (! $assignment instanceof HrAssignment) {
                    Notification::make()->title('No se encontró la asignación')->danger()->send();

                    return;
                }

                $assignment->delete();
                $this->reloadEmployeeItems();

                Notification::make()->title('Asignación eliminada')->success()->send();
            });
    }

    private function deleteDeductionHeaderAction(): Action
    {
        return Action::make('deleteDeduction')
            ->label('Eliminar deducción')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->visible(fn (): bool => $this->employeeHasDeductions())
            ->form([
                Select::make('deduction_id')
                    ->label('Deducción')
                    ->options(fn (): array => $this->deductionDeleteOptions())
                    ->required()
                    ->native(false)
                    ->searchable(),
            ])
            ->requiresConfirmation()
            ->modalHeading('Eliminar deducción')
            ->modalDescription('Se quitará de la ficha. Los periodos ya calculados no cambian.')
            ->modalSubmitActionLabel('Eliminar')
            ->action(function (array $data): void {
                $deduction = $this->employeeOwnedDeduction((int) ($data['deduction_id'] ?? 0));

                if (! $deduction instanceof HrDeduction) {
                    Notification::make()->title('No se encontró la deducción')->danger()->send();

                    return;
                }

                $deduction->delete();
                $this->reloadEmployeeItems();

                Notification::make()->title('Deducción eliminada')->success()->send();
            });
    }

    private function employeeHasAssignments(): bool
    {
        $employee = $this->getRecord();

        return $employee instanceof Employee && $employee->assignments()->exists();
    }

    private function employeeHasDeductions(): bool
    {
        $employee = $this->getRecord();

        return $employee instanceof Employee && $employee->deductions()->exists();
    }

    /**
     * @return array<int, string>
     */
    private function assignmentDeleteOptions(): array
    {
        $employee = $this->getRecord();
        if (! $employee instanceof Employee) {
            return [];
        }

        return $employee->assignments()
            ->orderBy('concept')
            ->get()
            ->mapWithKeys(fn (HrAssignment $assignment): array => [
                $assignment->id => $assignment->concept.' · US$ '.number_format((float) $assignment->amount_usd, 2, ',', '.'),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function deductionDeleteOptions(): array
    {
        $employee = $this->getRecord();
        if (! $employee instanceof Employee) {
            return [];
        }

        return $employee->deductions()
            ->orderBy('concept')
            ->get()
            ->mapWithKeys(fn (HrDeduction $deduction): array => [
                $deduction->id => $deduction->concept.' · US$ '.number_format((float) $deduction->amount_usd, 2, ',', '.'),
            ])
            ->all();
    }

    private function employeeOwnedAssignment(int $id): ?HrAssignment
    {
        $employee = $this->getRecord();
        if (! $employee instanceof Employee || $id <= 0) {
            return null;
        }

        return $employee->assignments()->whereKey($id)->first();
    }

    private function employeeOwnedDeduction(int $id): ?HrDeduction
    {
        $employee = $this->getRecord();
        if (! $employee instanceof Employee || $id <= 0) {
            return null;
        }

        return $employee->deductions()->whereKey($id)->first();
    }

    private function reloadEmployeeItems(): void
    {
        $employee = $this->getRecord();
        if (! $employee instanceof Employee) {
            return;
        }

        $fresh = $employee->fresh();
        if (! $fresh instanceof Employee) {
            return;
        }

        $fresh->load([
            'branch',
            'assignments' => fn ($q) => $q->latest('id'),
            'deductions' => fn ($q) => $q->latest('id'),
            'loans' => fn ($q) => $q->latest('id'),
        ]);

        $this->record = $fresh;
    }
}
