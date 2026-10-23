<?php

namespace App\Services\Hr;

use App\Enums\EmployeeRequestStatus;
use App\Enums\EmployeeRequestType;
use App\Jobs\NotifyEmployeeRequestDecisionJob;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\EmploymentCertificateSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Aprobación, rechazo y anulación de solicitudes del portal. Bloquea la fila para evitar
 * decisiones dobles y avisa al empleado (correo y WhatsApp) en segundo plano.
 */
final class EmployeeRequestReviewer
{
    public function approve(EmployeeRequest $request, User $admin, ?string $note = null): EmployeeRequest
    {
        if ($request->type === EmployeeRequestType::EmploymentCertificate && ! EmploymentCertificateSetting::current()->isConfigured()) {
            throw new RuntimeException('Primero configura la plantilla de la constancia de trabajo (texto y firmante).');
        }

        return $this->decide($request, $admin, EmployeeRequestStatus::Approved, $note);
    }

    public function reject(EmployeeRequest $request, User $admin, string $note): EmployeeRequest
    {
        if (trim($note) === '') {
            throw new InvalidArgumentException('Indica el motivo del rechazo.');
        }

        return $this->decide($request, $admin, EmployeeRequestStatus::Rejected, $note);
    }

    public function cancelByEmployee(EmployeeRequest $request, Employee $employee): EmployeeRequest
    {
        if ((int) $request->employee_id !== (int) $employee->id) {
            throw new RuntimeException('Esta solicitud no es tuya.');
        }

        return DB::transaction(function () use ($request): EmployeeRequest {
            $locked = $this->lockPending($request);

            $locked->forceFill([
                'status' => EmployeeRequestStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            return $locked;
        });
    }

    private function decide(EmployeeRequest $request, User $admin, EmployeeRequestStatus $status, ?string $note): EmployeeRequest
    {
        if (! $admin->canAccessHrModule()) {
            throw new RuntimeException('Solo un administrador puede decidir solicitudes.');
        }

        $decided = DB::transaction(function () use ($request, $admin, $status, $note): EmployeeRequest {
            $locked = $this->lockPending($request);

            $locked->forceFill([
                'status' => $status,
                'admin_note' => filled($note) ? trim((string) $note) : null,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'employee_seen_at' => null,
            ])->save();

            NotifyEmployeeRequestDecisionJob::dispatch($locked->id, NotifyEmployeeRequestDecisionJob::CHANNEL_MAIL);
            NotifyEmployeeRequestDecisionJob::dispatch($locked->id, NotifyEmployeeRequestDecisionJob::CHANNEL_WHATSAPP);

            return $locked;
        });

        $request->setRawAttributes($decided->getAttributes(), true);

        return $decided;
    }

    private function lockPending(EmployeeRequest $request): EmployeeRequest
    {
        $locked = EmployeeRequest::query()->whereKey($request->getKey())->lockForUpdate()->first();

        if (! $locked instanceof EmployeeRequest) {
            throw new RuntimeException('La solicitud ya no existe.');
        }

        if (! $locked->isPending()) {
            throw new InvalidArgumentException('La solicitud ya fue '.mb_strtolower($locked->status->label()).'.');
        }

        return $locked;
    }
}
