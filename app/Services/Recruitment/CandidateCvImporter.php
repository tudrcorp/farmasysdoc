<?php

namespace App\Services\Recruitment;

use App\Enums\RecruitmentCvExtractionStatus;
use App\Models\RecruitmentCandidate;
use App\Support\Recruitment\CvDataNormalizer;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Convierte un CV subido en un candidato sin revisar. Si la lectura falla el CV
 * se conserva y el analista puede reintentar o llenar los datos a mano.
 */
class CandidateCvImporter
{
    public function __construct(
        private GeminiCvReader $reader,
        private CvPhotoCropper $photoCropper,
    ) {}

    public function import(string $cvPath, ?string $originalName): RecruitmentCandidate
    {
        $candidate = RecruitmentCandidate::query()->create([
            'cv_path' => $cvPath,
            'cv_original_name' => $originalName,
        ]);

        return $this->read($candidate);
    }

    /**
     * Solo llena los campos vacíos, para no pisar correcciones del analista.
     */
    public function read(RecruitmentCandidate $candidate): RecruitmentCandidate
    {
        @set_time_limit(180);

        $disk = Storage::disk(RecruitmentCandidate::CV_DISK);
        $path = (string) $candidate->cv_path;

        try {
            $absolutePath = $disk->path($path);
            $mimeType = (string) $disk->mimeType($path);
            $raw = $this->reader->read($absolutePath, $mimeType);
            $normalized = CvDataNormalizer::normalize($raw, $candidate->id);

            foreach ($normalized['attributes'] as $field => $value) {
                if (blank($candidate->{$field}) && filled($value)) {
                    $candidate->{$field} = $value;
                }
            }

            $flags = $normalized['flags'];
            if (blank($candidate->photo_path)) {
                $this->assignPhoto($candidate, $absolutePath, $mimeType, $raw['photo'] ?? null);
                $flags[] = 'photo_path';
            }

            $candidate->cv_extraction_status = RecruitmentCvExtractionStatus::Completed;
            $candidate->cv_extraction_error = null;
            $candidate->cv_extraction_flags = array_values(array_unique($flags));
        } catch (Throwable $exception) {
            report($exception);
            $candidate->cv_extraction_status = RecruitmentCvExtractionStatus::Failed;
            $candidate->cv_extraction_error = $exception->getMessage();
        }

        $candidate->save();

        return $candidate;
    }

    /**
     * Un fallo con la foto no debe perder los datos ya leídos: se guarda el motivo para mostrarlo al analista.
     */
    private function assignPhoto(RecruitmentCandidate $candidate, string $absolutePath, string $mimeType, mixed $photo): void
    {
        $candidate->cv_photo_error = null;

        try {
            $candidate->photo_path = $this->photoCropper->crop($absolutePath, $mimeType, is_array($photo) ? $photo : null);
        } catch (Throwable $exception) {
            report($exception);
            $candidate->cv_photo_error = $exception->getMessage();
        }
    }
}
