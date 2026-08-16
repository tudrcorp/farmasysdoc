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

    public function storeSignatureFromDataUrl(Employee $employee, string $dataUrl): void
    {
        $this->storeDataUrl($employee, $dataUrl, 'employees/signatures', 'signature_path');
    }

    public function storeFingerprintFromDataUrl(Employee $employee, string $dataUrl): void
    {
        $this->storeDataUrl($employee, $dataUrl, 'employees/fingerprints', 'fingerprint_path');
    }

    public function storeSignatureUpload(Employee $employee, UploadedFile $file): void
    {
        $this->storeUpload($employee, $file, 'employees/signatures', 'signature_path');
    }

    public function storeFingerprintUpload(Employee $employee, UploadedFile $file): void
    {
        $this->storeUpload($employee, $file, 'employees/fingerprints', 'fingerprint_path');
    }

    private function storeDataUrl(Employee $employee, string $dataUrl, string $directory, string $attribute): void
    {
        [$binary, $extension] = $this->decodeImageDataUrl($dataUrl);
        $this->assertValidImageBinary($binary);

        $path = $directory.'/'.$employee->getKey().'_'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $binary);
        $this->replacePath($employee, $attribute, $path);
    }

    private function storeUpload(Employee $employee, UploadedFile $file, string $directory, string $attribute): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'image' => 'No se pudo leer el archivo. Inténtalo de nuevo.',
            ]);
        }

        $path = $file->store($directory, 'public');

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'image' => 'No se pudo guardar el archivo.',
            ]);
        }

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
