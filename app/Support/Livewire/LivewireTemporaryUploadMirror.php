<?php

declare(strict_types=1);

namespace App\Support\Livewire;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * El temporal de Livewire se escribe en un disco (`public` o `local`) y el guardado
 * lo vuelve a leer en el disco configurado en ese momento. Si no coinciden, fileSize()
 * lanza UnableToRetrieveMetadata y el modal de Filament falla.
 */
final class LivewireTemporaryUploadMirror
{
    public function restoreComponent(object $component): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        foreach (['data', 'mountedActions'] as $property) {
            if (! property_exists($component, $property)) {
                continue;
            }

            $this->restoreValue($component->{$property});
        }
    }

    public function ensureFilename(string $filename): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $filename = basename($filename);

        if (! $this->isTemporaryFilename($filename)) {
            return;
        }

        $this->ensureRelativePath($this->expectedPath($filename), $filename, logIfMissing: false);
    }

    public function mirrorUploadResponse(Response $response): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $content = $response->getContent();

        if (! is_string($content) || ! str_contains($content, 'paths')) {
            return;
        }

        preg_match_all('/[A-Za-z0-9]{30,}\.[A-Za-z0-9]{2,8}/', $content, $matches);

        foreach (array_unique($matches[0]) as $filename) {
            if (! is_string($filename)) {
                continue;
            }

            $this->ensureFilename($filename);
        }
    }

    private function restoreValue(mixed $value, int $depth = 0): void
    {
        if ($depth > 12) {
            return;
        }

        if ($value instanceof TemporaryUploadedFile) {
            $this->restoreUploadedFile($value);

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $item) {
            $this->restoreValue($item, $depth + 1);
        }
    }

    private function restoreUploadedFile(TemporaryUploadedFile $file): void
    {
        $location = $this->fileLocation($file);
        $filename = basename($location['path']);

        if (! $this->isTemporaryFilename($filename) || ! $this->isSafeRelativePath($location['path'])) {
            return;
        }

        $this->ensureRelativePath($location['path'], $filename, logIfMissing: true, extraDisk: $location['disk']);
    }

    private function ensureRelativePath(string $expectedPath, string $filename, bool $logIfMissing, ?string $extraDisk = null): void
    {
        if (! $this->isSafeRelativePath($expectedPath)) {
            return;
        }

        $disks = $this->localDiskNames($extraDisk);

        if ($disks === []) {
            return;
        }

        $source = $this->locate($expectedPath, $filename, $disks);

        if ($source === null) {
            if ($logIfMissing) {
                Log::error('Livewire temporary upload is missing on every local disk.', [
                    'path' => $expectedPath,
                    'disks' => $disks,
                ]);
            }

            return;
        }

        foreach ($disks as $disk) {
            if ($disk === $source['disk'] && $source['path'] === $expectedPath) {
                continue;
            }

            if ($this->exists($disk, $expectedPath)) {
                continue;
            }

            if (! $this->copy($source['disk'], $source['path'], $disk, $expectedPath)) {
                continue;
            }

            $this->copy($source['disk'], $source['path'].'.json', $disk, $expectedPath.'.json');

            Log::warning('Livewire temporary upload restored from another disk.', [
                'path' => $expectedPath,
                'from_disk' => $source['disk'],
                'from_path' => $source['path'],
                'to_disk' => $disk,
            ]);
        }
    }

    /**
     * @param  list<string>  $disks
     * @return array{disk: string, path: string}|null
     */
    private function locate(string $expectedPath, string $filename, array $disks): ?array
    {
        $directory = $this->directory();
        $paths = array_values(array_unique([
            $expectedPath,
            $directory.'/'.$directory.'/'.$filename,
        ]));

        foreach ($disks as $disk) {
            foreach ($paths as $path) {
                if (! $this->isSafeRelativePath($path)) {
                    continue;
                }

                if ($this->exists($disk, $path)) {
                    return ['disk' => $disk, 'path' => $path];
                }
            }
        }

        return null;
    }

    private function copy(string $fromDisk, string $fromPath, string $toDisk, string $toPath): bool
    {
        if (! $this->exists($fromDisk, $fromPath)) {
            return false;
        }

        $stream = null;

        try {
            $stream = Storage::disk($fromDisk)->readStream($fromPath);

            if (! is_resource($stream)) {
                return false;
            }

            return Storage::disk($toDisk)->writeStream($toPath, $stream) !== false;
        } catch (Throwable $exception) {
            Log::error('Unable to mirror Livewire temporary upload.', [
                'from_disk' => $fromDisk,
                'from_path' => $fromPath,
                'to_disk' => $toDisk,
                'to_path' => $toPath,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function exists(string $disk, string $path): bool
    {
        try {
            return Storage::disk($disk)->exists($path);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function localDiskNames(?string $extraDisk = null): array
    {
        $names = [
            $extraDisk,
            (string) (config('livewire.temporary_file_upload.disk') ?: config('filesystems.default')),
            'public',
            'local',
        ];

        $disks = [];

        foreach (array_unique($names) as $name) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            if (config('filesystems.disks.'.$name.'.driver') !== 'local') {
                continue;
            }

            $disks[] = $name;
        }

        return $disks;
    }

    /**
     * @return array{path: string, disk: string}
     */
    private function fileLocation(TemporaryUploadedFile $file): array
    {
        $reflection = new ReflectionClass($file);

        return [
            'path' => (string) $reflection->getProperty('path')->getValue($file),
            'disk' => (string) $reflection->getProperty('disk')->getValue($file),
        ];
    }

    private function expectedPath(string $filename): string
    {
        return FileUploadConfiguration::path($filename, false);
    }

    private function directory(): string
    {
        return trim((string) (config('livewire.temporary_file_upload.directory') ?: 'livewire-tmp'), '/');
    }

    private function isTemporaryFilename(string $filename): bool
    {
        return preg_match('/^[A-Za-z0-9]{30,}\.[A-Za-z0-9]{2,8}$/', $filename) === 1;
    }

    private function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '\\')) {
            return false;
        }

        return str_starts_with($path, $this->directory().'/');
    }
}
