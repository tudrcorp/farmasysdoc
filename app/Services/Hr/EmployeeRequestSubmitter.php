<?php

namespace App\Services\Hr;

use App\Enums\EmployeeRequestStatus;
use App\Enums\EmployeeRequestType;
use App\Enums\EmploymentCertificateSalaryBasis;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\EmployeeRequestAttachment;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Registra las solicitudes del portal del empleado validando fechas, cruces y soportes.
 * Todas quedan "por aprobar" hasta que el administrador decida.
 */
final class EmployeeRequestSubmitter
{
    public const int MAX_ATTACHMENTS = 5;

    public const int MAX_ATTACHMENT_KB = 5120;

    /**
     * @var list<string>
     */
    public const array ATTACHMENT_MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public function __construct(private EmployeeVacationPlanner $vacationPlanner) {}

    public function submitCertificate(Employee $employee, EmploymentCertificateSalaryBasis $basis): EmployeeRequest
    {
        if (! $employee->hasEmploymentData()) {
            throw ValidationException::withMessages([
                'salaryBasis' => 'Aún no tenemos tu fecha de ingreso o tu cargo. Pídele a RR. HH. que los cargue.',
            ]);
        }

        if ($basis === EmploymentCertificateSalaryBasis::Legal && ! $employee->hasLegalSalary()) {
            throw ValidationException::withMessages([
                'salaryBasis' => 'No tienes sueldo de ley cargado. Elige sueldo integral o consulta con RR. HH.',
            ]);
        }

        return DB::transaction(function () use ($employee, $basis): EmployeeRequest {
            $this->lockEmployee($employee);

            $hasPending = $employee->employeeRequests()
                ->where('type', EmployeeRequestType::EmploymentCertificate)
                ->where('status', EmployeeRequestStatus::Pending)
                ->exists();

            if ($hasPending) {
                throw ValidationException::withMessages([
                    'salaryBasis' => 'Ya tienes una constancia por aprobar. Te avisamos apenas esté lista.',
                ]);
            }

            return $employee->employeeRequests()->create([
                'branch_id' => $employee->branch_id,
                'type' => EmployeeRequestType::EmploymentCertificate,
                'status' => EmployeeRequestStatus::Pending,
                'salary_basis' => $basis,
            ]);
        });
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function submitAbsence(
        Employee $employee,
        EmployeeRequestType $type,
        string $startsOn,
        string $endsOn,
        ?string $reason,
        array $files = [],
    ): EmployeeRequest {
        if (! $type->hasDateRange()) {
            throw ValidationException::withMessages(['startsOn' => 'Tipo de solicitud no válido.']);
        }

        [$start, $end] = $this->parseRange($startsOn, $endsOn);
        $reason = filled($reason) ? trim((string) $reason) : null;

        $this->validateRangeForType($type, $start, $end);
        $this->validateReasonAndFiles($type, $reason, $files);

        $attributes = [
            'branch_id' => $employee->branch_id,
            'type' => $type,
            'status' => EmployeeRequestStatus::Pending,
            'starts_on' => $start,
            'ends_on' => $end,
            'days_count' => (int) $start->diffInDays($end) + 1,
            'reason' => $reason,
        ];

        if ($type === EmployeeRequestType::Vacation) {
            $attributes = [...$attributes, ...$this->vacationAttributes($employee, $start, $end)];
        }

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($employee, $attributes, $start, $end, $files, &$storedPaths): EmployeeRequest {
                $this->lockEmployee($employee);

                $overlaps = $employee->employeeRequests()->overlapping($start, $end)->exists();

                if ($overlaps) {
                    throw ValidationException::withMessages([
                        'startsOn' => 'Ya tienes otra solicitud vigente en esas fechas. Revisa "Mis solicitudes".',
                    ]);
                }

                $request = $employee->employeeRequests()->create($attributes);

                foreach ($files as $file) {
                    $path = $file->store('employee-requests/'.$employee->id.'/'.$request->id, EmployeeRequestAttachment::DISK);

                    if (! is_string($path) || $path === '') {
                        throw ValidationException::withMessages(['files' => 'No pudimos guardar uno de los archivos. Intenta de nuevo.']);
                    }

                    $storedPaths[] = $path;

                    $request->attachments()->create([
                        'path' => $path,
                        'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => (int) $file->getSize(),
                    ]);
                }

                return $request;
            });
        } catch (Throwable $exception) {
            Storage::disk(EmployeeRequestAttachment::DISK)->delete($storedPaths);

            throw $exception;
        }
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function parseRange(string $startsOn, string $endsOn): array
    {
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $startsOn);
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $endsOn);
        } catch (Throwable) {
            $start = $end = false;
        }

        if (! $start instanceof CarbonImmutable || ! $end instanceof CarbonImmutable) {
            throw ValidationException::withMessages(['startsOn' => 'Elige la fecha de inicio y la de fin.']);
        }

        return $end->lessThan($start) ? [$end, $start] : [$start, $end];
    }

    private function validateRangeForType(EmployeeRequestType $type, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $bounds = self::dateBounds($type);
        $maxDays = self::maxSpanDays($type);

        if ($start->lessThan($bounds['min']) || $end->greaterThan($bounds['max'])) {
            throw ValidationException::withMessages([
                'startsOn' => match ($type) {
                    EmployeeRequestType::Vacation => 'Las vacaciones se piden con fechas a partir de mañana.',
                    default => 'Las fechas están fuera del rango permitido para esta solicitud.',
                },
            ]);
        }

        if ((int) $start->diffInDays($end) + 1 > $maxDays) {
            throw ValidationException::withMessages([
                'startsOn' => "El período no puede pasar de {$maxDays} días.",
            ]);
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function validateReasonAndFiles(EmployeeRequestType $type, ?string $reason, array $files): void
    {
        if ($type === EmployeeRequestType::Leave && $reason === null) {
            throw ValidationException::withMessages(['reason' => 'Cuéntanos el motivo del permiso.']);
        }

        if ($type === EmployeeRequestType::MedicalRest && $files === []) {
            throw ValidationException::withMessages(['files' => 'Adjunta al menos un documento que justifique el reposo.']);
        }

        if ($type === EmployeeRequestType::Vacation && $files !== []) {
            throw ValidationException::withMessages(['files' => 'Las vacaciones no llevan documentos.']);
        }

        if (count($files) > self::MAX_ATTACHMENTS) {
            throw ValidationException::withMessages(['files' => 'Puedes adjuntar hasta '.self::MAX_ATTACHMENTS.' archivos.']);
        }

        foreach ($files as $file) {
            $extension = strtolower((string) $file->getClientOriginalExtension());

            if (! in_array($extension, self::ATTACHMENT_MIMES, true) || (int) $file->getSize() > self::MAX_ATTACHMENT_KB * 1024) {
                throw ValidationException::withMessages(['files' => 'Solo PDF o fotos (JPG, PNG, WEBP) de hasta 5 MB.']);
            }
        }
    }

    /**
     * @return array{entitled_days: int|null, entitlement_starts_on: CarbonImmutable|null, is_early: bool, days_count: int}
     */
    private function vacationAttributes(Employee $employee, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $plan = $this->vacationPlanner->forEmployee($employee);

        if ($plan === null) {
            throw ValidationException::withMessages([
                'startsOn' => 'Aún no tenemos tu fecha de ingreso. Pídele a RR. HH. que la cargue.',
            ]);
        }

        $businessDays = $this->vacationPlanner->businessDaysBetween($start, $end);

        if ($businessDays < 1) {
            throw ValidationException::withMessages(['startsOn' => 'El período elegido no tiene días hábiles.']);
        }

        if ($businessDays > $plan['entitled_days']) {
            throw ValidationException::withMessages([
                'startsOn' => "Te corresponden {$plan['entitled_days']} días hábiles y elegiste {$businessDays}.",
            ]);
        }

        return [
            'entitled_days' => $plan['entitled_days'],
            'entitlement_starts_on' => $plan['right_starts_on'],
            'is_early' => $start->lessThan($plan['right_starts_on']),
            'days_count' => $businessDays,
        ];
    }

    /**
     * Fechas mínima y máxima que acepta cada tipo (también las usa el calendario del portal).
     *
     * @return array{min: CarbonImmutable, max: CarbonImmutable}
     */
    public static function dateBounds(EmployeeRequestType $type): array
    {
        $today = CarbonImmutable::today();

        return [
            'min' => match ($type) {
                EmployeeRequestType::Vacation => $today->addDay(),
                EmployeeRequestType::Leave => $today->subDays(30),
                default => $today->subDays(60),
            },
            'max' => $today->addYear(),
        ];
    }

    public static function maxSpanDays(EmployeeRequestType $type): int
    {
        return match ($type) {
            EmployeeRequestType::Vacation => 60,
            EmployeeRequestType::Leave => 30,
            default => 90,
        };
    }

    private function lockEmployee(Employee $employee): void
    {
        Employee::query()->whereKey($employee->getKey())->lockForUpdate()->first(['id']);
    }
}
