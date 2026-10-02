<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Livewire\LivewireTemporaryUploadMirror;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Copia el temporal de Livewire al disco que usará la vista previa o el siguiente guardado.
 */
final class RestoreLivewireTemporaryUploads
{
    public function __construct(
        private readonly LivewireTemporaryUploadMirror $mirror,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $filename = $request->route('filename');

        if (is_string($filename) && str_contains($request->path(), 'preview-file')) {
            $this->mirror->ensureFilename($filename);
        }

        $response = $next($request);

        if (str_contains($request->path(), 'upload-file')) {
            $this->mirror->mirrorUploadResponse($response);
        }

        return $response;
    }
}
