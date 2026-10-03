<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Livewire\Exceptions\LivewireReleaseTokenMismatchException;
use Livewire\Features\SupportReleaseTokens\ReleaseToken;
use Livewire\Mechanisms\HandleComponents\Checksum;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Anota en laravel.log cada guardado Livewire que el servidor rechaza.
 * Un 419 vacío no pasa por el logger normal y el botón parece no hacer nada.
 */
final class LogLivewireSaveFailures
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isLivewireUpdate($request)) {
            return $next($request);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->report($request, $this->statusFromException($exception), $exception);

            throw $exception;
        }

        $status = $response->getStatusCode();

        if ($this->shouldReport($status)) {
            $this->report($request, $status, null, $response);
        }

        return $response;
    }

    private function isLivewireUpdate(Request $request): bool
    {
        return $request->isMethod('POST')
            && preg_match('/(^|\/)livewire(?:-[^\/]+)?\/update$/', $request->path()) === 1;
    }

    private function shouldReport(int $status): bool
    {
        return in_array($status, [404, 419, 429], true) || $status >= 500;
    }

    private function report(Request $request, int $status, ?Throwable $exception, ?Response $response = null): void
    {
        try {
            $diagnosis = $this->diagnose($request);
        } catch (Throwable $diagnosisError) {
            $diagnosis = [
                'reason' => 'undetermined',
                'diagnosis_error' => $diagnosisError::class,
            ];
        }

        $reason = $this->reasonFromException($exception) ?? $diagnosis['reason'] ?? 'http_'.$status;

        $context = [
            'reason' => $reason,
            'status' => $status,
            'path' => $request->path(),
            'referer' => $request->headers->get('referer'),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'ip' => $request->ip(),
            'host' => gethostname(),
            'serialize_precision' => ini_get('serialize_precision'),
            ...$diagnosis,
        ];

        if ($exception !== null) {
            $context['exception'] = $exception::class;
            $context['message'] = mb_substr($exception->getMessage(), 0, 500);
        }

        if ($response !== null) {
            $context['response_bytes'] = strlen((string) $response->getContent());
        }

        if ($status >= 500) {
            logger()->error('livewire.save_failed', $context);

            return;
        }

        logger()->warning('livewire.save_failed', $context);
    }

    private function statusFromException(Throwable $exception): int
    {
        if ($exception instanceof TokenMismatchException) {
            return 419;
        }

        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        return 500;
    }

    private function reasonFromException(?Throwable $exception): ?string
    {
        if ($exception === null) {
            return null;
        }

        if ($exception instanceof TokenMismatchException) {
            return 'csrf';
        }

        if ($exception instanceof LivewireReleaseTokenMismatchException) {
            return 'release_token';
        }

        if ($exception instanceof CorruptComponentPayloadException) {
            return 'checksum';
        }

        if ($exception instanceof TooManyRequestsHttpException) {
            return 'rate_limited';
        }

        $message = $exception->getMessage();

        if (str_contains($message, 'Allowed memory size')) {
            return 'memory_exhausted';
        }

        if (str_contains($message, 'storage/framework/views')) {
            return 'compiled_view_missing';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnose(Request $request): array
    {
        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return [
                'reason' => $this->csrfMismatch($request) ? 'csrf' : 'invalid_payload',
                'calls' => [],
            ];
        }

        $calls = [];
        $first = null;

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            foreach ($component['calls'] ?? [] as $call) {
                if (is_array($call) && is_string($call['method'] ?? null)) {
                    $calls[] = $call['method'];
                }
            }

            $first ??= $this->diagnoseComponent($component);
        }

        $diagnosis = $first ?? ['reason' => 'invalid_payload'];
        $diagnosis['calls'] = $calls;

        if (($diagnosis['reason'] ?? null) === 'ok' && $this->csrfMismatch($request)) {
            $diagnosis['reason'] = 'csrf';
        }

        if (($diagnosis['reason'] ?? null) === 'ok') {
            $diagnosis['reason'] = 'http_status';
        }

        return $diagnosis;
    }

    /**
     * @param  array<string, mixed>  $component
     * @return array<string, mixed>
     */
    private function diagnoseComponent(array $component): array
    {
        $snapshot = json_decode((string) ($component['snapshot'] ?? ''), true);

        if (! is_array($snapshot)) {
            return ['reason' => 'invalid_snapshot'];
        }

        $memo = is_array($snapshot['memo'] ?? null) ? $snapshot['memo'] : [];
        $name = is_string($memo['name'] ?? null) ? $memo['name'] : null;
        $receivedRelease = is_string($memo['release'] ?? null) ? $memo['release'] : null;
        $expectedRelease = $this->expectedRelease($name);

        $receivedChecksum = is_string($snapshot['checksum'] ?? null) ? $snapshot['checksum'] : null;
        $computedChecksum = $this->computedChecksum($snapshot);

        $reason = 'ok';

        if ($expectedRelease !== null && $receivedRelease !== $expectedRelease) {
            $reason = 'release_token';
        } elseif ($computedChecksum !== null && $receivedChecksum !== $computedChecksum) {
            $reason = 'checksum';
        }

        return [
            'reason' => $reason,
            'component' => $name,
            'component_id' => is_string($memo['id'] ?? null) ? $memo['id'] : null,
            'release_received' => $receivedRelease,
            'release_expected' => $expectedRelease,
            'checksum_received_prefix' => $receivedChecksum !== null ? substr($receivedChecksum, 0, 12) : null,
            'checksum_computed_prefix' => $computedChecksum !== null ? substr($computedChecksum, 0, 12) : null,
        ];
    }

    private function expectedRelease(?string $componentName): ?string
    {
        if ($componentName === null) {
            return null;
        }

        try {
            $componentClass = app('livewire.factory')->resolveComponentClass($componentName);
        } catch (Throwable) {
            return null;
        }

        return ReleaseToken::generate($componentClass);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function computedChecksum(array $snapshot): ?string
    {
        try {
            unset($snapshot['checksum']);

            return Checksum::generate($snapshot);
        } catch (Throwable) {
            return null;
        }
    }

    private function csrfMismatch(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $sent = $request->header('X-CSRF-TOKEN', $request->input('_token'));

        if (! is_string($sent) || $sent === '') {
            return false;
        }

        return ! hash_equals($request->session()->token(), $sent);
    }
}
