<?php

namespace App\Filament\Resources\Hr\Recruitment\Candidates\Actions;

use App\Enums\RecruitmentRequestStatus;
use App\Filament\Resources\Hr\Recruitment\Applications\RecruitmentApplicationResource;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentRequest;
use App\Services\Recruitment\RecruitmentSelectionService;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;

final class ApplyCandidateToRequestAction
{
    public static function make(): Action
    {
        return Action::make('applyToRequest')
            ->label('Postular')
            ->icon(Heroicon::ArrowRightCircle)
            ->color('primary')
            ->modalHeading('Postular a una solicitud de personal')
            ->modalSubmitActionLabel('Postular')
            ->visible(fn (RecruitmentCandidate $record): bool => $record->isReviewed()
                && $record->employee_id === null
                && (RecruitmentApplicationResource::currentUser()?->canAccessRecruitmentSelection() ?? false))
            ->schema([
                Select::make('recruitment_request_id')
                    ->label('Solicitud de personal')
                    ->options(fn (): array => self::approvedRequestOptions())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (RecruitmentCandidate $record, array $data): void {
                try {
                    $request = BranchAuthScope::apply(RecruitmentRequest::query())->findOrFail($data['recruitment_request_id']);
                    app(RecruitmentSelectionService::class)->apply($record, $request, RecruitmentApplicationResource::currentUser());
                    Notification::make()->title('Candidato postulado')->success()->send();
                } catch (Throwable $exception) {
                    Notification::make()->title('No se pudo postular')->body($exception->getMessage())->danger()->send();
                }
            });
    }

    /**
     * @return array<int, string>
     */
    public static function approvedRequestOptions(): array
    {
        return BranchAuthScope::apply(RecruitmentRequest::query())
            ->with('branch')
            ->where('status', RecruitmentRequestStatus::Approved)
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (RecruitmentRequest $request): array => [$request->id => $request->optionLabel()])
            ->all();
    }
}
