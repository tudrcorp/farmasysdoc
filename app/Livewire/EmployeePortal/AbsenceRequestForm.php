<?php

namespace App\Livewire\EmployeePortal;

use App\Enums\EmployeeRequestType;
use App\Models\Employee;
use App\Services\Hr\EmployeePortalAccess;
use App\Services\Hr\EmployeeRequestSubmitter;
use App\Services\Hr\EmployeeVacationPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Vacaciones, permisos y reposos: rango de fechas (mismo selector del dashboard), motivo y soportes.
 */
#[Layout('layouts.employee-portal')]
class AbsenceRequestForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $tipo = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public string $reason = '';

    /**
     * @var array<int, TemporaryUploadedFile>
     */
    public array $files = [];

    /**
     * Archivos recién elegidos; se suman a `$files` para poder adjuntar en varias tandas.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $newFiles = [];

    public function mount(string $tipo): void
    {
        abort_unless(EmployeeRequestType::fromPortalSlug($tipo) !== null, 404);

        $this->tipo = $tipo;

        if ($this->type() === EmployeeRequestType::Vacation) {
            $plan = app(EmployeeVacationPlanner::class)->forEmployee($this->employee());

            if ($plan !== null) {
                $this->startsOn = $plan['suggested_starts_on']->toDateString();
                $this->endsOn = $plan['suggested_ends_on']->toDateString();
            }
        }
    }

    public function type(): EmployeeRequestType
    {
        return EmployeeRequestType::fromPortalSlug($this->tipo) ?? EmployeeRequestType::Leave;
    }

    public function employee(): Employee
    {
        $employee = app(EmployeePortalAccess::class)->employee();
        abort_unless($employee instanceof Employee, 403);

        return $employee;
    }

    public function updatedNewFiles(): void
    {
        $this->validate([
            'newFiles.*' => ['file', 'mimes:'.implode(',', EmployeeRequestSubmitter::ATTACHMENT_MIMES), 'max:'.EmployeeRequestSubmitter::MAX_ATTACHMENT_KB],
        ], [
            'newFiles.*.mimes' => 'Solo PDF o fotos (JPG, PNG, WEBP).',
            'newFiles.*.max' => 'Cada archivo puede pesar hasta 5 MB.',
        ]);

        $this->files = array_slice(
            [...array_values($this->files), ...array_values($this->newFiles)],
            0,
            EmployeeRequestSubmitter::MAX_ATTACHMENTS,
        );
        $this->newFiles = [];
    }

    public function removeFile(int $index): void
    {
        if (isset($this->files[$index])) {
            $this->files[$index]->delete();
            unset($this->files[$index]);
            $this->files = array_values($this->files);
        }
    }

    public function submit(): void
    {
        $type = $this->type();

        $this->validate([
            'startsOn' => ['required', 'date_format:Y-m-d'],
            'endsOn' => ['required', 'date_format:Y-m-d'],
            'reason' => [$type === EmployeeRequestType::Leave ? 'required' : 'nullable', 'string', 'max:1000'],
            'files' => [$type === EmployeeRequestType::MedicalRest ? 'required' : 'nullable', 'array', 'max:'.EmployeeRequestSubmitter::MAX_ATTACHMENTS],
            'files.*' => ['file', 'mimes:'.implode(',', EmployeeRequestSubmitter::ATTACHMENT_MIMES), 'max:'.EmployeeRequestSubmitter::MAX_ATTACHMENT_KB],
        ], [
            'startsOn.required' => 'Elige las fechas en el calendario.',
            'endsOn.required' => 'Elige la fecha final en el calendario.',
            'reason.required' => 'Cuéntanos el motivo del permiso.',
            'reason.max' => 'El motivo no puede pasar de 1000 caracteres.',
            'files.required' => 'Adjunta al menos un documento que justifique el reposo.',
            'files.max' => 'Puedes adjuntar hasta '.EmployeeRequestSubmitter::MAX_ATTACHMENTS.' archivos.',
            'files.*.mimes' => 'Solo PDF o fotos (JPG, PNG, WEBP).',
            'files.*.max' => 'Cada archivo puede pesar hasta 5 MB.',
        ]);

        app(EmployeeRequestSubmitter::class)->submitAbsence(
            $this->employee(),
            $type,
            $this->startsOn,
            $this->endsOn,
            $this->reason,
            array_values($this->files),
        );

        session()->flash('ep_request_flash', 'Enviamos tu solicitud de '.mb_strtolower($type->label()).'. Te avisamos por correo y WhatsApp cuando RR. HH. responda.');

        $this->redirectRoute('employee-portal.requests', navigate: true);
    }

    public function render(): View
    {
        $employee = $this->employee();
        $type = $this->type();
        $bounds = EmployeeRequestSubmitter::dateBounds($type);
        $plan = $type === EmployeeRequestType::Vacation
            ? app(EmployeeVacationPlanner::class)->forEmployee($employee)
            : null;

        return view('livewire.employee-portal.absence-request-form', [
            'employee' => $employee,
            'type' => $type,
            'plan' => $plan,
            'blocked' => $type === EmployeeRequestType::Vacation && ! $employee->hasEmploymentData(),
            'pickerConfig' => [
                'today' => CarbonImmutable::today()->toDateString(),
                'min' => $bounds['min']->toDateString(),
                'max' => $bounds['max']->toDateString(),
                'maxDays' => EmployeeRequestSubmitter::maxSpanDays($type),
                'from' => $this->startsOn,
                'to' => $this->endsOn,
                'businessDays' => $type === EmployeeRequestType::Vacation,
                'rightStartsOn' => $plan !== null ? $plan['right_starts_on']->toDateString() : null,
                'entitledDays' => $plan !== null ? $plan['entitled_days'] : null,
            ],
            'presets' => $this->presets($type, $plan),
        ])->title($type->label());
    }

    /**
     * @param  array<string, mixed>|null  $plan
     * @return list<array{label: string, from: string, to: string}>
     */
    private function presets(EmployeeRequestType $type, ?array $plan): array
    {
        $today = CarbonImmutable::today();

        return match ($type) {
            EmployeeRequestType::Vacation => $plan === null ? [] : [[
                'label' => 'Fechas sugeridas',
                'from' => $plan['suggested_starts_on']->toDateString(),
                'to' => $plan['suggested_ends_on']->toDateString(),
            ]],
            EmployeeRequestType::Leave => [
                ['label' => 'Hoy', 'from' => $today->toDateString(), 'to' => $today->toDateString()],
                ['label' => 'Mañana', 'from' => $today->addDay()->toDateString(), 'to' => $today->addDay()->toDateString()],
            ],
            default => [
                ['label' => 'Hoy', 'from' => $today->toDateString(), 'to' => $today->toDateString()],
                ['label' => '3 días desde hoy', 'from' => $today->toDateString(), 'to' => $today->addDays(2)->toDateString()],
                ['label' => '1 semana desde hoy', 'from' => $today->toDateString(), 'to' => $today->addDays(6)->toDateString()],
            ],
        };
    }
}
