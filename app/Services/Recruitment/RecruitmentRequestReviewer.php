<?php

namespace App\Services\Recruitment;

use App\Enums\RecruitmentRequestStatus;
use App\Models\RecruitmentRequest;
use App\Models\User;
use RuntimeException;

class RecruitmentRequestReviewer
{
    public function approve(RecruitmentRequest $request, User $reviewer, ?string $note = null): void
    {
        $this->decide($request, $reviewer, RecruitmentRequestStatus::Approved, $note);
    }

    public function reject(RecruitmentRequest $request, User $reviewer, string $note): void
    {
        $this->decide($request, $reviewer, RecruitmentRequestStatus::Rejected, $note);
    }

    public function cancel(RecruitmentRequest $request, User $reviewer, string $note): void
    {
        if (! in_array($request->status, [RecruitmentRequestStatus::Pending, RecruitmentRequestStatus::Approved], true)) {
            throw new RuntimeException('Esta solicitud ya está cerrada.');
        }

        $this->record($request, $reviewer, RecruitmentRequestStatus::Cancelled, $note);
    }

    private function decide(RecruitmentRequest $request, User $reviewer, RecruitmentRequestStatus $status, ?string $note): void
    {
        if (! $request->isPending()) {
            throw new RuntimeException('Esta solicitud ya fue revisada.');
        }

        $this->record($request, $reviewer, $status, $note);
    }

    private function record(RecruitmentRequest $request, User $reviewer, RecruitmentRequestStatus $status, ?string $note): void
    {
        if (! $reviewer->isAdministrator()) {
            throw new RuntimeException('Solo un administrador puede decidir sobre las solicitudes de personal.');
        }

        $request->update([
            'status' => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => filled($note) ? $note : null,
        ]);
    }
}
