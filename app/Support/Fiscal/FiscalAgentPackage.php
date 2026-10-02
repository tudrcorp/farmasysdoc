<?php

namespace App\Support\Fiscal;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Paquete .zip del agente Windows de máquina fiscal, guardado en el disco privado para descargarlo
 * desde Farmaadmin. No se versiona en git porque incluye el SDK propietario de The Factory HKA.
 */
final class FiscalAgentPackage
{
    private const DISK = 'local';

    private const PACKAGE_PATH = 'fiscal-agent/FarmadocFiscalAgent.zip';

    private const META_PATH = 'fiscal-agent/package.json';

    /**
     * Archivos que debe traer el paquete para poder instalarse.
     *
     * @var list<string>
     */
    private const REQUIRED_FILES = ['FarmadocFiscalAgent.exe', 'Farmadoc.FiscalAgent.Core.dll', 'TfhkaNet.dll', 'install.ps1'];

    /**
     * @return array{filename: string, version: string, size: int, sha256: string, uploaded_at: string, uploaded_by: string}|null
     */
    public static function current(): ?array
    {
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists(self::PACKAGE_PATH) || ! $disk->exists(self::META_PATH)) {
            return null;
        }

        $meta = json_decode((string) $disk->get(self::META_PATH), true);

        return is_array($meta) ? $meta : null;
    }

    public static function path(): string
    {
        return self::PACKAGE_PATH;
    }

    public static function disk(): string
    {
        return self::DISK;
    }

    /**
     * Valida y publica un nuevo paquete (ruta relativa en el disco privado, p. ej. la subida temporal de Filament).
     *
     * @return array{filename: string, version: string, size: int, sha256: string, uploaded_at: string, uploaded_by: string}
     */
    public static function publish(string $uploadedPath, string $originalName, string $actor): array
    {
        $disk = Storage::disk(self::DISK);
        $absolute = $disk->path($uploadedPath);

        self::assertValidZip($absolute);

        $version = preg_match('/(\d+\.\d+\.\d+)/', $originalName, $m) === 1 ? $m[1] : 'sin versión';

        $meta = [
            'filename' => 'FarmadocFiscalAgent-'.($version === 'sin versión' ? date('Ymd') : $version).'.zip',
            'version' => $version,
            'size' => (int) filesize($absolute),
            'sha256' => (string) hash_file('sha256', $absolute),
            'uploaded_at' => now()->toIso8601String(),
            'uploaded_by' => $actor,
        ];

        $disk->delete(self::PACKAGE_PATH);
        $disk->move($uploadedPath, self::PACKAGE_PATH);
        $disk->put(self::META_PATH, (string) json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $meta;
    }

    private static function assertValidZip(string $absolutePath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($absolutePath) !== true) {
            throw ValidationException::withMessages(['package' => 'El archivo no es un .zip válido.']);
        }

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = basename((string) $zip->getNameIndex($i));
        }
        $zip->close();

        $missing = array_values(array_diff(self::REQUIRED_FILES, $names));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'package' => 'El .zip no parece el paquete del agente; falta: '.implode(', ', $missing).'.',
            ]);
        }
    }
}
