<?php

namespace App\Filament\Resources\Hr\Recruitment\Applications\Pages;

use App\Enums\RecruitmentRequestStatus;
use App\Enums\RecruitmentStage;
use App\Filament\Resources\Hr\Recruitment\Applications\RecruitmentApplicationResource;
use App\Filament\Resources\Hr\Recruitment\Candidates\Actions\ApplyCandidateToRequestAction;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentRequest;
use App\Services\Recruitment\RecruitmentSelectionService;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Throwable;

class ManageRecruitmentSelection extends Page
{
    protected static string $resource = RecruitmentApplicationResource::class;

    protected string $view = 'filament.recruitment.selection-board';

    protected static ?string $title = 'Selección';

    public ?int $requestId = null;

    /**
     * @return array<int, string>
     */
    public function getRequestOptions(): array
    {
        return ApplyCandidateToRequestAction::approvedRequestOptions();
    }

    /**
     * @return Collection<string, Collection<int, RecruitmentApplication>>
     */
    public function getApplicationsByStage(): Collection
    {
        return RecruitmentApplicationResource::getEloquentQuery()
            ->whereHas('recruitmentRequest', fn ($query) => $query->where('status', RecruitmentRequestStatus::Approved))
            ->when($this->requestId !== null, fn ($query) => $query->where('recruitment_request_id', $this->requestId))
            ->latest('updated_at')
            ->get()
            ->groupBy(fn (RecruitmentApplication $application): string => $application->stage->value);
    }

    public function moveApplication(int $applicationId, string $stage): void
    {
        $target = RecruitmentStage::tryFrom($stage);
        $application = RecruitmentApplicationResource::getEloquentQuery()->find($applicationId);

        if ($target === null || $application === null) {
            return;
        }

        try {
            app(RecruitmentSelectionService::class)->move($application, $target, RecruitmentApplicationResource::currentUser());
            Notification::make()->title("Movido a {$target->label()}")->success()->send();
        } catch (Throwable $exception) {
            Notification::make()->title('No se pudo mover')->body($exception->getMessage())->danger()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addCandidate')
                ->label('Agregar candidato')
                ->icon(Heroicon::UserPlus)
                ->modalHeading('Agregar candidato al proceso')
                ->modalDescription('Solo aparecen candidatos con sus datos revisados.')
                ->modalSubmitActionLabel('Agregar')
                ->schema([
                    Select::make('recruitment_request_id')
                        ->label('Solicitud de personal')
                        ->options(fn (): array => $this->getRequestOptions())
                        ->default($this->requestId)
                        ->searchable()
                        ->required(),
                    Select::make('recruitment_candidate_id')
                        ->label('Candidato')
                        ->options(fn (): array => $this->candidateOptions())
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        $request = BranchAuthScope::apply(RecruitmentRequest::query())->findOrFail($data['recruitment_request_id']);
                        $candidate = RecruitmentCandidate::query()->findOrFail($data['recruitment_candidate_id']);
                        app(RecruitmentSelectionService::class)->apply($candidate, $request, RecruitmentApplicationResource::currentUser());
                        Notification::make()->title('Candidato agregado')->success()->send();
                    } catch (Throwable $exception) {
                        Notification::make()->title('No se pudo agregar')->body($exception->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function candidateOptions(): array
    {
        return RecruitmentCandidate::query()
            ->whereNotNull('reviewed_at')
            ->whereNull('employee_id')
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(fn (RecruitmentCandidate $candidate): array => [
                $candidate->id => "{$candidate->fullName()} · {$candidate->national_id}",
            ])
            ->all();
    }
}
