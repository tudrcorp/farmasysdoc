<?php

namespace App\Filament\Resources\Hr\Recruitment\Candidates\Pages;

use App\Filament\Resources\Hr\Recruitment\Candidates\RecruitmentCandidateResource;
use App\Filament\Resources\Hr\Recruitment\Candidates\Schemas\RecruitmentCandidateForm;
use App\Services\Recruitment\CandidateCvImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListRecruitmentCandidates extends ListRecords
{
    protected static string $resource = RecruitmentCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('uploadCv')
                ->label('Cargar CV')
                ->icon(Heroicon::Sparkles)
                ->color('primary')
                ->modalHeading('Cargar curriculum vitae')
                ->modalDescription('La IA lee el CV y llena la ficha. Luego revisas los datos al lado del documento.')
                ->modalSubmitActionLabel('Leer CV')
                ->schema([
                    RecruitmentCandidateForm::cvUpload()->required(),
                ])
                ->action(function (array $data): void {
                    $candidate = app(CandidateCvImporter::class)->import($data['cv_path'], $data['cv_original_name'] ?? null);

                    if (filled($candidate->cv_extraction_error)) {
                        Notification::make()->title('El CV se guardó, pero no se pudo leer')->body('Llena los datos a mano o reintenta la lectura.')->warning()->send();
                    } elseif (filled($candidate->cv_photo_error)) {
                        Notification::make()->title('CV leído, pero sin foto')->body($candidate->cv_photo_error)->warning()->send();
                    } else {
                        Notification::make()->title('CV leído')->body('Revisa los datos antes de guardar.')->success()->send();
                    }

                    $this->redirect(RecruitmentCandidateResource::getUrl('edit', ['record' => $candidate]));
                }),
            CreateAction::make()->label('Carga manual')->color('gray'),
        ];
    }
}
