<?php

namespace App\Services\Partners;

use App\Models\PartnerPanelActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Escribe la bitácora del panel dale · Aliados. Nunca interrumpe la operación que la origina.
 */
final class PartnerPanelActivityRecorder
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        int $partnerCompanyId,
        string $action,
        string $description,
        ?User $actor = null,
        ?Model $subject = null,
        ?string $subjectLabel = null,
        array $properties = [],
    ): void {
        try {
            PartnerPanelActivity::query()->create([
                'partner_company_id' => $partnerCompanyId,
                'user_id' => $actor?->getKey(),
                'actor_name' => $actor?->name,
                'actor_type' => match (true) {
                    $actor === null => PartnerPanelActivity::ACTOR_SYSTEM,
                    $actor->isPartnerCompanyUser() => PartnerPanelActivity::ACTOR_PARTNER,
                    default => PartnerPanelActivity::ACTOR_FARMADOC,
                },
                'action' => $action,
                'description' => mb_substr($description, 0, 500),
                'subject_type' => $subject !== null ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subjectLabel !== null ? mb_substr($subjectLabel, 0, 255) : null,
                'properties' => $properties === [] ? null : $properties,
                'ip_address' => request()?->ip(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
