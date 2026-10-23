<?php

namespace App\Livewire\EmployeePortal;

use App\Enums\EmployeeRequestType;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Services\Hr\EmployeePortalAccess;
use App\Services\Hr\EmployeeRequestReviewer;
use App\Support\Hr\EmployeePortalNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.employee-portal')]
#[Title('Mis solicitudes')]
class Requests extends Component
{
    public const int LIST_LIMIT = 50;

    public ?string $flash = null;

    public ?string $errorMessage = null;

    /**
     * Solicitudes cuya decisión el empleado aún no había visto al entrar (se resaltan una vez).
     *
     * @var list<int>
     */
    public array $freshIds = [];

    public function mount(): void
    {
        $employee = $this->employee();
        $this->flash = session()->pull('ep_request_flash');

        $this->freshIds = $employee->employeeRequests()
            ->unseenDecisions()
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($this->freshIds !== []) {
            EmployeePortalNotifications::markAllSeen((int) $employee->id);
            $this->dispatch('ep-notifications-read');
        }
    }

    public function employee(): Employee
    {
        $employee = app(EmployeePortalAccess::class)->employee();
        abort_unless($employee instanceof Employee, 403);

        return $employee;
    }

    public function cancel(int $requestId): void
    {
        $this->flash = null;
        $this->errorMessage = null;
        $employee = $this->employee();
        $request = $employee->employeeRequests()->find($requestId);

        if (! $request instanceof EmployeeRequest) {
            $this->errorMessage = 'No encontramos esa solicitud.';

            return;
        }

        try {
            app(EmployeeRequestReviewer::class)->cancelByEmployee($request, $employee);
            $this->flash = 'Listo, anulamos la solicitud.';
        } catch (Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    /**
     * @return Collection<int, EmployeeRequest>
     */
    private function requests(Employee $employee): Collection
    {
        return $employee->employeeRequests()
            ->with('attachments')
            ->latest('id')
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    public function render(): View
    {
        $employee = $this->employee();
        $employee->loadMissing('branch');

        return view('livewire.employee-portal.requests', [
            'employee' => $employee,
            'requests' => $this->requests($employee),
            'types' => EmployeeRequestType::cases(),
        ]);
    }
}
