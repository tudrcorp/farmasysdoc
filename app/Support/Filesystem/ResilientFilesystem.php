<?php

namespace App\Support\Filesystem;

use Illuminate\Filesystem\Filesystem;

/**
 * Evita ErrorException cuando Blade comprueba si una vista compilada expiró y el archivo
 * desaparece entre exists() y filemtime() (varios workers PHP, view:clear bajo carga, despliegues).
 */
final class ResilientFilesystem extends Filesystem
{
    /**
     * @param  string  $path
     */
    public function lastModified($path): int
    {
        if (! is_file($path)) {
            return 0;
        }

        $mtime = @filemtime($path);

        return $mtime !== false ? (int) $mtime : 0;
    }
}
