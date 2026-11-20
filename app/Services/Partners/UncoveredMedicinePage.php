<?php

namespace App\Services\Partners;

/**
 * Una página del listado de medicamentos no cubiertos.
 */
final readonly class UncoveredMedicinePage
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        public array $rows,
        public int $currentPage,
        public int $lastPage,
        public int $total,
        public ?int $rateLimitLimit,
        public ?int $rateLimitRemaining,
    ) {}
}
