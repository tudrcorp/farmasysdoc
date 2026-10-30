<?php

namespace App\Services\Recruitment;

use App\Enums\RecruitmentStage;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Movimientos del proceso de selección. Cada cambio queda en el historial.
 */
class RecruitmentSelectionService
{
    public function apply(RecruitmentCandidate $candidate, RecruitmentRequest $request, User $user): RecruitmentApplication
    {
        if (! $candidate->isReviewed()) {
            throw new RuntimeException('Primero hay que revisar y guardar los datos del candidato.');
        }

        if (! $request->isApproved()) {
            throw new RuntimeException('La solicitud de personal debe estar aprobada.');
        }

        if ($candidate->applications()->where('recruitment_request_id', $request->id)->exists()) {
            throw new RuntimeException('Este candidato ya participa en esa solicitud.');
        }

        return DB::transaction(function () use ($candidate, $request, $user): RecruitmentApplication {
            $application = RecruitmentApplication::query()->create([
                'recruitment_request_id' => $request->id,
                'recruitment_candidate_id' => $candidate->id,
                'stage' => RecruitmentStage::Applied,
            ]);

            $this->log($application, $user, null, RecruitmentStage::Applied);

            return $application;
        });
    }

    public function move(RecruitmentApplication $application, RecruitmentStage $stage, User $user, ?string $note = null): void
    {
        if (! $stage->isOpen()) {
            throw new RuntimeException('Para contratar o descartar usa la acción correspondiente.');
        }

        if ($application->stage === RecruitmentStage::Hired) {
            throw new RuntimeException('Un candidato contratado no puede cambiar de etapa.');
        }

        if ($application->stage === $stage) {
            return;
        }

        if (! $application->recruitmentRequest->isApproved()) {
            throw new RuntimeException('La solicitud de personal ya no está activa.');
        }

        $this->transition($application, $stage, $user, $note, ['discard_reason' => null]);
    }

    public function discard(RecruitmentApplication $application, string $reason, User $user): void
    {
        if (! $application->stage->isOpen()) {
            throw new RuntimeException('Este proceso ya está cerrado.');
        }

        $this->transition($application, RecruitmentStage::Discarded, $user, $reason, ['discard_reason' => $reason]);
    }

    public function addNote(RecruitmentApplication $application, string $note, User $user): void
    {
        $this->log($application, $user, null, null, $note);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function transition(RecruitmentApplication $application, RecruitmentStage $stage, ?User $user, ?string $note, array $attributes = []): void
    {
        DB::transaction(function () use ($application, $stage, $user, $note, $attributes): void {
            $from = $application->stage;
            $application->update([...$attributes, 'stage' => $stage]);
            $this->log($application, $user, $from, $stage, $note);
        });
    }

    private function log(RecruitmentApplication $application, ?User $user, ?RecruitmentStage $from, ?RecruitmentStage $to, ?string $note = null): void
    {
        $application->events()->create([
            'user_id' => $user?->id,
            'from_stage' => $from,
            'to_stage' => $to,
            'note' => filled($note) ? $note : null,
        ]);
    }
}
