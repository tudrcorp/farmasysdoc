<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Livewire\LivewireRequestPayload;
use Closure;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;

final class ConditionalTrimStrings
{
    public function __construct(
        private readonly TrimStrings $trimStrings,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (LivewireRequestPayload::shouldSkipNormalization($request)) {
            return $next($request);
        }

        return $this->trimStrings->handle($request, $next);
    }
}
