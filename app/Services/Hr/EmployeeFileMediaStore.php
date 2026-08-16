<?php

namespace App\Services\Hr;

use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EmployeeFileMediaStore
{
    public const MAX_BYTES = 2_097_152;

    public function __construct(
        private EmployeeFileBackgroundRemover $backgroundRemover,
    ) {}

    public function storeSignatureFromDataUrl(Employee $employee, string $dataUrl): void
    {
        $this->storeDataUrl($employee, $dataUrl, 'employees/signatures', 'signature_path', EmployeeFileBackgroundRemover::SIGNATURE);
    }

    public function storeFingerprintFromDataUrl(Employee $employee, string $dataUrl): void
    {
        $this->storeDataUrl($employee, $dataUrl, 'employees/fingerprints', 'fingerprint_path', EmployeeFileBackgroundRemover::FINGERPRINT);
    }

    public function storeSignatureUpload(Employee $employee, UploadedFile $file): void
    {
        $this->storeUpload($employee, $file, 'employees/signatures', 'signature_path', EmployeeFileBackgroundRemover::SIGNATURE);
    }

    public function storeFingerprintUpload(Employee $employee, UploadedFile $file): void
    {
        $this->storeUpload($employee, $file, 'employees/fingerprints', 'fingerprint_path', EmployeeFileBackgroundRemover::FINGERPRINT);
    }

    private function storeDataUrl(Employee $employee, string $dataUrl, string $directory, string $attribute, string $mode): void
    {
        [$binary] = $this->decodeImageDataUrl($dataUrl);
        $this->assertValidImageBinary($binary);
        $this->storePng($employee, $this->backgroundRemover->toTransparentPng($binary, $mode), $directory, $attribute);
    }

    private function storeUpload(Employee $employee, UploadedFile $file, string $directory, string $attribute, string $mode): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'image' => 'No se pudo leer el archivo. Inténtalo de nuevo.',
            ]);
        }

        $binary = file_get_contents($file->getRealPath());

        if (! is_string($binary) || $binary === '') {
            throw ValidationException::withMessages([
                'image' => 'No se pudo leer el archivo.',
            ]);
        }

        $this->assertValidImageBinary($binary);
        $this->storePng($employee, $this->backgroundRemover->toTransparentPng($binary, $mode), $directory, $attribute);
    }

    private function storePng(Employee $employee, string $png, string $directory, string $attribute): void
    {
        $path = $directory.'/'.$employee->getKey().'_'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $png);
        $this->replacePath($employee, $attribute, $path);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function decodeImageDataUrl(string $dataUrl): array
    {
        if (! preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,/', $dataUrl, $matches)) {
            throw ValidationException::withMessages([
                'image' => 'La imagen no es válida.',
            ]);
        }

        $extension = in_array($matches[1], ['jpeg', 'jpg'], true) ? 'jpg' : $matches[1];
        $commaPosition = strpos($dataUrl, ',');

        if ($commaPosition === false) {
            throw ValidationException::withMessages([
                'image' => 'La imagen no es válida.',
            ]);
        }

        $binary = base64_decode(substr($dataUrl, $commaPosition + 1), true);

        if ($binary === false || strlen($binary) < 80 || strlen($binary) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'image' => 'La imagen está vacía o es demasiado grande.',
            ]);
        }

        return [$binary, $extension];
    }

    private function assertValidImageBinary(string $binary): void
    {
        if (@getimagesizefromstring($binary) === false) {
            throw ValidationException::withMessages([
                'image' => 'No se pudo leer la imagen.',
            ]);
        }
    }

    private function replacePath(Employee $employee, string $attribute, string $path): void
    {
        $previous = $employee->{$attribute};
        $employee->update([$attribute => $path]);

        if (filled($previous) && $previous !== $path && Storage::disk('public')->exists((string) $previous)) {
            Storage::disk('public')->delete((string) $previous);
        }
    }
}
