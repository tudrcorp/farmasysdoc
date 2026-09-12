<?php

namespace App\Services\Inventory;

use App\Enums\InventoryAuditLineStatus;
use App\Enums\InventoryAuditStatus;
use App\Models\Branch;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditLine;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use App\Support\Inventory\InventoryAuditLetterRange;
use App\Support\Inventory\InventoryQuantityFormat;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class InventoryAuditDetailedReportBuilder
{
    /**
     * @param  array{
     *     from?: string|null,
     *     until?: string|null,
     *     branch_id?: int|null,
     *     letter_from?: string|null,
     *     letter_to?: string|null,
     *     status?: string|null
     * }  $filters
     * @return array{
     *     generated_at: string,
     *     generated_by: string,
     *     filter_labels: list<string>,
     *     period_from: string|null,
     *     period_until: string|null,
     *     branch_name: string|null,
     *     letter_range: string|null,
     *     status_label: string|null,
     *     summary: array{
     *         audits_count: int,
     *         lines_total: int,
     *         pending: int,
     *         verified: int,
     *         updated: int,
     *         quantity_delta_sum: float,
     *         quantity_delta_sum_label: string,
     *         cost_changes: int
     *     },
     *     audits: list<array<string, mixed>>
     * }
     */
    public function build(array $filters, User $actor): array
    {
        $normalized = $this->normalizeFilters($filters);
        $letterRange = $normalized['letter_range'];

        $query = BranchAuthScope::apply(
            InventoryAudit::query()
                ->with([
                    'branch:id,name',
                    'productCategory:id,name',
                    'startedBy:id,name',
                    'closedBy:id,name',
                    'lines.product:id,name,barcode',
                    'lines.processedBy:id,name',
                ])
        );

        $this->applyFilters($query, $normalized);

        $audits = [];
        $summary = [
            'audits_count' => 0,
            'lines_total' => 0,
            'pending' => 0,
            'verified' => 0,
            'updated' => 0,
            'quantity_delta_sum' => 0.0,
            'cost_changes' => 0,
        ];

        $query
            ->orderBy('started_at')
            ->orderBy('id')
            ->get()
            ->each(function (InventoryAudit $audit) use ($letterRange, &$audits, &$summary): void {
                $mapped = $this->mapAudit($audit, $letterRange);
                if ($mapped === null) {
                    return;
                }

                $audits[] = $mapped;
                $summary['audits_count']++;
                $summary['lines_total'] += $mapped['progress']['total'];
                $summary['pending'] += $mapped['progress']['pending'];
                $summary['verified'] += $mapped['progress']['verified'];
                $summary['updated'] += $mapped['progress']['updated'];
                $summary['quantity_delta_sum'] += $mapped['quantity_delta_sum'];
                $summary['cost_changes'] += $mapped['cost_changes'];
            });

        $summary['quantity_delta_sum'] = round($summary['quantity_delta_sum'], 3);
        $summary['quantity_delta_sum_label'] = InventoryQuantityFormat::display($summary['quantity_delta_sum']);

        $branchName = null;
        if ($normalized['branch_id'] !== null) {
            $branchName = $audits[0]['branch_name'] ?? null;
            if ($branchName === null) {
                $branchName = (string) (Branch::query()
                    ->whereKey($normalized['branch_id'])
                    ->value('name') ?? '#'.$normalized['branch_id']);
            }
        }

        return [
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'generated_by' => $this->safeText(filled($actor->name) ? (string) $actor->name : (string) ($actor->email ?? 'administrador')),
            'filter_labels' => $this->filterLabels($normalized, $branchName),
            'period_from' => $normalized['from']?->format('d/m/Y'),
            'period_until' => $normalized['until']?->format('d/m/Y'),
            'branch_name' => $branchName,
            'letter_range' => $letterRange !== null
                ? InventoryAuditLetterRange::label($letterRange[0], $letterRange[1])
                : null,
            'status_label' => $normalized['status']?->label(),
            'summary' => $summary,
            'audits' => $audits,
        ];
    }

    /**
     * @param  array{
     *     from?: string|null,
     *     until?: string|null,
     *     branch_id?: int|null,
     *     letter_from?: string|null,
     *     letter_to?: string|null,
     *     status?: string|null
     * }  $filters
     * @return array{
     *     from: Carbon|null,
     *     until: Carbon|null,
     *     branch_id: int|null,
     *     letter_range: array{0: string, 1: string}|null,
     *     status: InventoryAuditStatus|null
     * }
     */
    public function normalizeFilters(array $filters): array
    {
        $fromRaw = filled($filters['from'] ?? null) ? (string) $filters['from'] : null;
        $untilRaw = filled($filters['until'] ?? null) ? (string) $filters['until'] : null;

        $from = null;
        $until = null;
        if ($fromRaw !== null || $untilRaw !== null) {
            if ($fromRaw === null || $untilRaw === null) {
                throw ValidationException::withMessages([
                    'from' => 'Indique fecha inicial y final del período.',
                    'until' => 'Indique fecha inicial y final del período.',
                ]);
            }

            $from = Carbon::parse($fromRaw)->startOfDay();
            $until = Carbon::parse($untilRaw)->endOfDay();
            if ($until->lt($from)) {
                throw ValidationException::withMessages([
                    'until' => '«Hasta» no puede ser anterior a «Desde».',
                ]);
            }
        }

        $branchId = isset($filters['branch_id']) && is_numeric($filters['branch_id'])
            ? (int) $filters['branch_id']
            : null;
        if ($branchId !== null && $branchId <= 0) {
            $branchId = null;
        }

        $letterRange = InventoryAuditLetterRange::resolve(
            isset($filters['letter_from']) ? (string) $filters['letter_from'] : null,
            isset($filters['letter_to']) ? (string) $filters['letter_to'] : null,
        );

        $status = null;
        if (filled($filters['status'] ?? null)) {
            $status = InventoryAuditStatus::tryFrom((string) $filters['status']);
        }

        if ($from === null && $branchId === null && $letterRange === null && $status === null) {
            throw ValidationException::withMessages([
                'from' => 'Indique al menos un filtro: período, sucursal, rango de letras o estado.',
            ]);
        }

        return [
            'from' => $from,
            'until' => $until,
            'branch_id' => $branchId,
            'letter_range' => $letterRange,
            'status' => $status,
        ];
    }

    /**
     * @param  Builder<InventoryAudit>  $query
     * @param  array{
     *     from: Carbon|null,
     *     until: Carbon|null,
     *     branch_id: int|null,
     *     letter_range: array{0: string, 1: string}|null,
     *     status: InventoryAuditStatus|null
     * }  $normalized
     */
    private function applyFilters(Builder $query, array $normalized): void
    {
        if ($normalized['from'] instanceof Carbon && $normalized['until'] instanceof Carbon) {
            $from = $normalized['from'];
            $until = $normalized['until'];
            $query->where(function (Builder $inner) use ($from, $until): void {
                $inner->whereBetween('started_at', [$from, $until])
                    ->orWhereBetween('closed_at', [$from, $until]);
            });
        }

        if ($normalized['branch_id'] !== null) {
            $query->where('branch_id', $normalized['branch_id']);
        }

        if ($normalized['status'] instanceof InventoryAuditStatus) {
            $query->where('status', $normalized['status']);
        }

        if ($normalized['letter_range'] !== null) {
            [$letterFrom, $letterTo] = $normalized['letter_range'];
            $query->where(function (Builder $inner) use ($letterFrom, $letterTo): void {
                $inner->where(function (Builder $allLetters): void {
                    $allLetters->whereNull('letter_from')->whereNull('letter_to');
                })->orWhere(function (Builder $overlap) use ($letterFrom, $letterTo): void {
                    $overlap->whereNotNull('letter_from')
                        ->whereNotNull('letter_to')
                        ->where('letter_from', '<=', $letterTo)
                        ->where('letter_to', '>=', $letterFrom);
                });
            });
        }
    }

    /**
     * @param  array{0: string, 1: string}|null  $letterRange
     * @return array<string, mixed>|null
     */
    private function mapAudit(InventoryAudit $audit, ?array $letterRange): ?array
    {
        $lines = [];
        $pending = 0;
        $verified = 0;
        $updated = 0;
        $quantityDeltaSum = 0.0;
        $costChanges = 0;

        foreach ($audit->lines as $line) {
            if (! $line instanceof InventoryAuditLine) {
                continue;
            }

            $productName = $this->safeText((string) ($line->product?->name ?? 'Producto #'.(int) $line->product_id));
            if ($letterRange !== null && ! $this->productNameMatchesLetterRange($productName, $letterRange[0], $letterRange[1])) {
                continue;
            }

            $status = $line->status instanceof InventoryAuditLineStatus
                ? $line->status
                : InventoryAuditLineStatus::tryFrom((string) $line->status);

            if ($status === InventoryAuditLineStatus::Pending) {
                $pending++;
            } elseif ($status === InventoryAuditLineStatus::Verified) {
                $verified++;
            } elseif ($status === InventoryAuditLineStatus::Updated) {
                $updated++;
            }

            $delta = (float) ($line->quantity_delta ?? 0);
            $quantityDeltaSum += $delta;
            if ($line->cost_changed) {
                $costChanges++;
            }

            $barcode = filled($line->product?->barcode) ? (string) $line->product->barcode : '';

            $lines[] = [
                'code' => $this->safeText($barcode !== '' ? $barcode : '-'),
                'name' => $productName,
                'status' => $status?->label() ?? '-',
                'system_quantity' => InventoryQuantityFormat::display($line->system_quantity),
                'counted_quantity' => $line->counted_quantity !== null
                    ? InventoryQuantityFormat::display($line->counted_quantity)
                    : '-',
                'quantity_delta' => InventoryQuantityFormat::display($delta),
                'system_cost' => number_format((float) $line->system_cost_price, 2, ',', '.'),
                'new_cost' => $line->new_cost_price !== null
                    ? number_format((float) $line->new_cost_price, 2, ',', '.')
                    : '-',
                'cost_changed' => $line->cost_changed ? 'Si' : 'No',
                'processed_by' => $this->safeText((string) ($line->processedBy?->name ?? '-')),
                'processed_at' => $line->processed_at?->format('d/m/Y H:i') ?? '-',
            ];
        }

        if ($letterRange !== null && $lines === []) {
            return null;
        }

        $status = $audit->status instanceof InventoryAuditStatus
            ? $audit->status
            : InventoryAuditStatus::tryFrom((string) $audit->status);

        return [
            'id' => (int) $audit->getKey(),
            'branch_name' => $this->safeText((string) ($audit->branch?->name ?? '-')),
            'category_name' => $this->safeText((string) ($audit->productCategory?->name ?? 'Todas')),
            'letter_range' => InventoryAuditLetterRange::label($audit->letter_from, $audit->letter_to) ?? 'A - Z',
            'status' => $status?->label() ?? '-',
            'notes' => filled($audit->notes) ? $this->safeText((string) $audit->notes) : '-',
            'started_by' => $this->safeText((string) ($audit->startedBy?->name ?? '-')),
            'started_at' => $audit->started_at?->format('d/m/Y H:i') ?? '-',
            'closed_by' => $this->safeText((string) ($audit->closedBy?->name ?? '-')),
            'closed_at' => $audit->closed_at?->format('d/m/Y H:i') ?? '-',
            'progress' => [
                'total' => count($lines),
                'pending' => $pending,
                'verified' => $verified,
                'updated' => $updated,
            ],
            'quantity_delta_sum' => round($quantityDeltaSum, 3),
            'cost_changes' => $costChanges,
            'line_chunks' => array_values(array_chunk($lines, 80)),
        ];
    }

    /**
     * @param  array{
     *     from: Carbon|null,
     *     until: Carbon|null,
     *     branch_id: int|null,
     *     letter_range: array{0: string, 1: string}|null,
     *     status: InventoryAuditStatus|null
     * }  $normalized
     * @return list<string>
     */
    private function filterLabels(array $normalized, ?string $branchName): array
    {
        $labels = [];

        if ($normalized['from'] instanceof Carbon && $normalized['until'] instanceof Carbon) {
            $labels[] = 'Período: '.$normalized['from']->format('d/m/Y').' – '.$normalized['until']->format('d/m/Y');
        }

        if ($normalized['branch_id'] !== null) {
            $labels[] = 'Sucursal: '.($branchName ?? '#'.$normalized['branch_id']);
        }

        if ($normalized['letter_range'] !== null) {
            $labels[] = 'Rango de letras: '.$normalized['letter_range'][0].' – '.$normalized['letter_range'][1];
        }

        if ($normalized['status'] instanceof InventoryAuditStatus) {
            $labels[] = 'Estado: '.$normalized['status']->label();
        }

        return $labels;
    }

    private function productNameMatchesLetterRange(string $name, string $from, string $to): bool
    {
        $trimmed = ltrim($name);
        if ($trimmed === '') {
            return false;
        }

        $letter = strtoupper(mb_substr($trimmed, 0, 1));
        if (! ctype_alpha($letter)) {
            return false;
        }

        return $letter >= $from && $letter <= $to;
    }

    private function safeText(string $value): string
    {
        $value = str_replace(["\x00", "\r"], '', $value);

        if ($value !== '' && ! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252') ?: $value;
        }

        return $value;
    }
}
