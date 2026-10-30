<?php

namespace App\Services\Hr;

use App\Enums\EmploymentCertificateSalaryBasis;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\EmploymentCertificateSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Arma la constancia de trabajo desde la plantilla única con los datos actuales del empleado.
 * Se genera al momento de descargar; no se guarda en disco.
 */
final class EmploymentCertificatePdfFactory
{
    public const string ASSETS_DISK = 'local';

    /**
     * @return array<string, string>
     */
    public function placeholderValues(Employee $employee, EmploymentCertificateSalaryBasis $basis, EmploymentCertificateSetting $setting, ?CarbonImmutable $issuedOn = null): array
    {
        $issuedOn ??= CarbonImmutable::today();
        $employee->loadMissing('branch');

        return [
            '{nombre}' => mb_strtoupper((string) preg_replace('/\s+/u', ' ', $employee->fullName()), 'UTF-8'),
            '{cedula}' => $employee->formattedNationalId() ?? (string) $employee->national_id,
            '{cargo}' => (string) $employee->job_title,
            '{sucursal}' => (string) ($employee->branch?->name ?? ''),
            '{fecha_ingreso}' => $employee->hired_at?->locale('es')->translatedFormat('j \d\e F \d\e Y') ?? '',
            '{antiguedad}' => (string) $employee->serviceDurationLabel($issuedOn),
            '{tipo_sueldo}' => mb_strtolower($basis->label(), 'UTF-8'),
            '{sueldo}' => $this->salaryLabel($employee, $basis),
            '{ciudad}' => filled($setting->city) ? (string) $setting->city : 'Caracas',
            '{fecha}' => $issuedOn->day.' días del mes de '.$issuedOn->locale('es')->translatedFormat('F').' de '.$issuedOn->year,
        ];
    }

    public function salaryLabel(Employee $employee, EmploymentCertificateSalaryBasis $basis): string
    {
        return match ($basis) {
            EmploymentCertificateSalaryBasis::Integral => 'US$ '.number_format((float) $employee->monthly_salary_usd, 2, ',', '.'),
            EmploymentCertificateSalaryBasis::Legal => 'Bs '.number_format((float) $employee->legal_salary_ves, 2, ',', '.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(EmployeeRequest $request): array
    {
        $request->loadMissing('employee.branch');
        $employee = $request->employee;
        $basis = $request->salary_basis;

        if (! $employee instanceof Employee || ! $basis instanceof EmploymentCertificateSalaryBasis) {
            throw new RuntimeException('La solicitud no corresponde a una constancia válida.');
        }

        $setting = EmploymentCertificateSetting::current();
        $values = $this->placeholderValues($employee, $basis, $setting);
        $body = strtr(e((string) ($setting->body ?: EmploymentCertificateSetting::DEFAULT_BODY)), array_map('e', $values));

        return [
            'title' => filled($setting->title) ? $setting->title : EmploymentCertificateSetting::DEFAULT_TITLE,
            'paragraphs' => array_values(array_filter(preg_split("/\R{2,}/", $body) ?: [], fn (string $paragraph): bool => trim($paragraph) !== '')),
            'setting' => $setting,
            'logo_data_uri' => $this->publicImageDataUri('images/logos/farmadoc-ligth.png'),
            'signature_data_uri' => $this->storedImageDataUri($setting->signature_path),
            'stamp_data_uri' => $this->storedImageDataUri($setting->stamp_path),
            'reference' => 'CT-'.str_pad((string) $request->id, 6, '0', STR_PAD_LEFT),
        ];
    }

    public function download(EmployeeRequest $request): StreamedResponse
    {
        $pdf = $this->make($request);

        return response()->streamDownload(
            function () use ($pdf): void {
                echo $pdf->output();
            },
            $this->fileName($request),
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function output(EmployeeRequest $request): string
    {
        return $this->make($request)->output();
    }

    public function fileName(EmployeeRequest $request): string
    {
        $name = Str::slug($request->employee?->fullName() ?? 'empleado');

        return "constancia-de-trabajo-{$name}-".now()->format('Y-m-d').'.pdf';
    }

    /**
     * Con subconjunto de fuentes el PDF baja de ~1 MB a decenas de KB: descarga rápida en datos móviles.
     */
    private function make(EmployeeRequest $request): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.employment-certificate', $this->viewData($request))
            ->setPaper('letter', 'portrait')
            ->setOption('enable_font_subsetting', true);
    }

    public function publicImageDataUri(string $relativePath): ?string
    {
        $path = public_path($relativePath);

        if (! is_readable($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    public function storedImageDataUri(?string $path): ?string
    {
        $disk = Storage::disk(self::ASSETS_DISK);

        if (! filled($path) || ! $disk->exists((string) $path)) {
            return null;
        }

        $bytes = $disk->get((string) $path);

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        $mime = match (strtolower(pathinfo((string) $path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
