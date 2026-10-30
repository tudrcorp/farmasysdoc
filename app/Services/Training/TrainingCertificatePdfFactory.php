<?php

namespace App\Services\Training;

use App\Models\EmploymentCertificateSetting;
use App\Models\TrainingEnrollment;
use App\Services\Hr\EmploymentCertificatePdfFactory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Certificado de capacitación. Usa el firmante, la firma y el sello de la plantilla
 * de constancia de trabajo. Se genera al descargar; no se guarda en disco.
 */
final class TrainingCertificatePdfFactory
{
    public function __construct(private EmploymentCertificatePdfFactory $assets) {}

    /**
     * @return array<string, mixed>
     */
    public function viewData(TrainingEnrollment $enrollment): array
    {
        $enrollment->loadMissing(['employee', 'session.course', 'session.facilitatorEmployee']);

        if (! $enrollment->canDownloadCertificate()) {
            throw new RuntimeException('Este participante no tiene certificado disponible.');
        }

        $setting = EmploymentCertificateSetting::current();

        if (blank($setting->signer_name)) {
            throw new RuntimeException('Primero configura el firmante en la plantilla de constancia.');
        }

        $session = $enrollment->session;
        $course = $session->course;

        return [
            'employee_name' => mb_strtoupper($enrollment->employee->fullName(), 'UTF-8'),
            'national_id' => $enrollment->employee->formattedNationalId() ?? (string) $enrollment->employee->national_id,
            'course_name' => $course->name,
            'course_type' => $course->type->certificateLabel(),
            'duration_label' => $this->durationLabel((float) $course->duration_hours),
            'date_label' => $session->ends_at->locale('es')->translatedFormat('j \d\e F \d\e Y'),
            'facilitator' => $session->facilitatorLabel(),
            'expires_on' => $enrollment->expires_on?->format('d/m/Y'),
            'setting' => $setting,
            'logo_data_uri' => $this->assets->publicImageDataUri('images/logos/farmadoc-ligth.png'),
            'signature_data_uri' => $this->assets->storedImageDataUri($setting->signature_path),
            'stamp_data_uri' => $this->assets->storedImageDataUri($setting->stamp_path),
            'reference' => 'CAP-'.str_pad((string) $enrollment->id, 6, '0', STR_PAD_LEFT),
        ];
    }

    private function durationLabel(float $hours): string
    {
        $amount = rtrim(rtrim(number_format($hours, 2, ',', '.'), '0'), ',');

        return $hours === 1.0 ? "{$amount} hora" : "{$amount} horas";
    }

    public function download(TrainingEnrollment $enrollment): StreamedResponse
    {
        $pdf = Pdf::loadView('pdf.training-certificate', $this->viewData($enrollment))
            ->setPaper('letter', 'landscape')
            ->setOption('enable_font_subsetting', true);

        return response()->streamDownload(
            function () use ($pdf): void {
                echo $pdf->output();
            },
            'certificado-'.Str::slug($enrollment->employee->fullName()).'-'.$enrollment->id.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
