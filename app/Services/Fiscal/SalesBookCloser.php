<?php

namespace App\Services\Fiscal;

use App\Enums\SalesBookStatus;
use App\Models\Branch;
use App\Models\SalesBook;
use App\Services\Audit\AuditLogger;
use App\Support\Fiscal\SalesBookPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cierra (congela) y reabre Libros de Ventas. Un libro cerrado guarda el snapshot exacto que se declaró,
 * con su huella SHA-256; reabrirlo no lo borra: queda como versión anulada y el siguiente cierre crea otra versión.
 */
final class SalesBookCloser
{
    public function __construct(
        private SalesBookBuilder $builder,
        private SalesBookPeriodLock $lock,
    ) {}

    public function close(Branch $branch, SalesBookPeriod $period, string $actor): SalesBook
    {
        $book = DB::transaction(function () use ($branch, $period, $actor): SalesBook {
            Branch::query()->whereKey($branch->id)->lockForUpdate()->first();

            if ($this->lock->isClosed((int) $branch->id, $period)) {
                throw ValidationException::withMessages([
                    'period' => 'El Libro de Ventas de la '.$period->label().' ya está cerrado para '.$branch->name.'.',
                ]);
            }

            $data = $this->builder->build($branch, $period, $actor);

            if (SalesBookBuilder::hasBlockingIssues($data)) {
                $errors = array_values(array_map(
                    fn (array $issue): string => $issue['message'],
                    array_filter($data['issues'], fn (array $issue): bool => $issue['level'] === SalesBookBuilder::LEVEL_ERROR),
                ));

                throw ValidationException::withMessages(['period' => $errors]);
            }

            $version = (int) SalesBook::query()
                ->where('branch_id', $branch->id)
                ->where('year', $period->year)
                ->where('month', $period->month)
                ->where('half', $period->half)
                ->max('version') + 1;

            $data['closed'] = ['version' => $version, 'closed_by' => $actor, 'closed_at' => now()->format('d/m/Y h:i A')];
            $snapshot = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);

            return SalesBook::query()->create([
                'branch_id' => $branch->id,
                'year' => $period->year,
                'month' => $period->month,
                'half' => $period->half,
                'period_start' => $period->start()->toDateString(),
                'period_end' => $period->end()->toDateString(),
                'version' => $version,
                'status' => SalesBookStatus::Closed,
                'snapshot' => $snapshot,
                'content_hash' => hash('sha256', $snapshot),
                'z_reports_count' => $data['z_reports_count'],
                'total_sales_ves' => $data['totals']['total'],
                'exempt_ves' => $data['totals']['exempt'],
                'taxable_base_ves' => $data['totals']['contributor_base'] + $data['totals']['non_contributor_base'],
                'tax_ves' => $data['totals']['contributor_tax'] + $data['totals']['non_contributor_tax'],
                'retained_ves' => $data['totals']['retained'],
                'closed_by' => $actor,
                'closed_at' => now(),
            ]);
        });

        AuditLogger::record(
            'sales_book_closed',
            'Libro de Ventas cerrado · '.$branch->name.' · '.$period->label().' · v'.$book->version,
            SalesBook::class,
            $book->id,
            $period->key().' v'.$book->version,
            ['module' => 'fiscal', 'branch_id' => $branch->id, 'content_hash' => $book->content_hash],
        );

        return $book;
    }

    public function reopen(SalesBook $book, string $reason, string $actor): SalesBook
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => 'Indique el motivo de la reapertura (mínimo 10 caracteres); queda en la auditoría.',
            ]);
        }

        $book = DB::transaction(function () use ($book, $reason, $actor): SalesBook {
            $locked = SalesBook::query()->whereKey($book->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SalesBookStatus::Closed) {
                throw ValidationException::withMessages([
                    'reason' => 'Este libro ya fue reabierto.',
                ]);
            }

            $locked->forceFill([
                'status' => SalesBookStatus::Reopened,
                'reopened_by' => $actor,
                'reopened_at' => now(),
                'reopen_reason' => $reason,
            ])->save();

            return $locked;
        });

        AuditLogger::record(
            'sales_book_reopened',
            'Libro de Ventas reabierto · '.$book->period()->label().' · v'.$book->version.' · '.$reason,
            SalesBook::class,
            $book->id,
            $book->period()->key().' v'.$book->version,
            ['module' => 'fiscal', 'branch_id' => $book->branch_id, 'reason' => $reason],
        );

        return $book;
    }
}
