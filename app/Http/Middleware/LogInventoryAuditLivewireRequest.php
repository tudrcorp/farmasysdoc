<?php

namespace App\Http\Middleware;

use App\Support\Inventory\InventoryAuditTrace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class LogInventoryAuditLivewireRequest
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $content = $request->getContent();
        if ($content === '' || (! str_contains($content, 'work-inventory-audit') && ! str_contains($content, 'WorkInventoryAudit'))) {
            return $next($request);
        }

        $started = microtime(true);
        InventoryAuditTrace::info('http.livewire_in', [
            'path' => $request->path(),
            'content_kb' => round(strlen($content) / 1024, 1),
        ]);

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            InventoryAuditTrace::error('http.livewire_exception', [
                'path' => $request->path(),
                'ms' => (int) round((microtime(true) - $started) * 1000),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        InventoryAuditTrace::info('http.livewire_out', [
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'ms' => (int) round((microtime(true) - $started) * 1000),
        ]);

        return $response;
    }
}
