<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Livewire\LivewireRequestPayload;
use Closure;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Http\Request;

final class ConditionalConvertEmptyStringsToNull
{
    public function __construct(
        private readonly ConvertEmptyStringsToNull $convertEmptyStringsToNull,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (LivewireRequestPayload::shouldSkipNormalization($request)) {
            return $next($request);
        }

        return $this->convertEmptyStringsToNull->handle($request, $next);
    }
}
