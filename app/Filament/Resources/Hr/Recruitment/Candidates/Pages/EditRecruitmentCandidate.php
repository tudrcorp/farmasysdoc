<?php

namespace App\Filament\Resources\Hr\Recruitment\Candidates\Pages;

use App\Filament\Resources\Hr\Recruitment\Candidates\RecruitmentCandidateResource;
use App\Models\RecruitmentCandidate;
use App\Services\Recruitment\CandidateCvImporter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditRecruitmentCandidate extends EditRecord
{
    protected static string $resource = RecruitmentCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('readCvAgain')
                ->label('Leer CV de nuevo')
                ->icon(Heroicon::ArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Solo se llenan los campos vacíos. Lo que ya está guardado no cambia.')
                ->visible(fn (RecruitmentCandidate $record): bool => $record->hasCv())
                ->action(function (RecruitmentCandidate $record): void {
                    app(CandidateCvImporter::class)->read($record);

                    $problem = $record->cv_extraction_error ?? $record->cv_photo_error;

                    Notification::make()
                        ->title(filled($problem) ? 'La lectura terminó con problemas' : 'Lectura terminada')
                        ->body($problem)
                        ->status(filled($problem) ? 'warning' : 'success')
                        ->send();

                    $this->redirect(RecruitmentCandidateResource::getUrl('edit', ['record' => $record]));
                }),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [
            ...$data,
            'reviewed_at' => now(),
            'reviewed_by' => RecruitmentCandidateResource::currentUser()?->id,
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Candidato revisado';
    }

    protected function getRedirectUrl(): ?string
    {
        return RecruitmentCandidateResource::getUrl('index');
    }
}
