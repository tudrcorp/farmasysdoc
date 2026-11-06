<?php

namespace App\Services\Finance;

use App\Jobs\SendAccountsPayableDueNoticeJob;
use App\Models\AccountsPayable;
use App\Models\AccountsPayableDueNotice;
use App\Models\Supplier;
use App\Models\SupplierBankAccount;
use App\Models\User;
use App\Support\Finance\AccountsPayableStatus;
use App\Support\Notifications\WhatsAppLink;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Aviso diario a los administradores de las facturas de proveedores por vencer (5 y 3 días calendario antes).
 *
 * Cada cuenta por pagar se avisa una sola vez por umbral y fecha de vencimiento (tabla
 * `accounts_payable_due_notices`); si un día no corre el proceso o la compra se carga con poco plazo,
 * el aviso pendiente sale en la siguiente corrida. Solo se envía cuando hay facturas nuevas que avisar;
 * el PDF incluye además las vencidas sin pagar. Correo y WhatsApp van por cola (un job por destinatario y canal).
 */
final class AccountsPayableDueNoticeService
{
    /**
     * @return array{sent: bool, notices: int, recipients: int, pdf_path: ?string, buckets: array<int, int>, overdue: int}
     */
    public function run(?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $empty = ['sent' => false, 'notices' => 0, 'recipients' => 0, 'pdf_path' => null, 'buckets' => [], 'overdue' => 0];

        $lock = Cache::lock('accounts-payable-due-notices', 600);

        if (! $lock->get()) {
            Log::warning('CxP avisos de vencimiento: ya hay una corrida en curso.');

            return $empty;
        }

        try {
            $thresholds = $this->thresholds();

            if ($thresholds === []) {
                return $empty;
            }

            [$buckets, $toRecord] = $this->pendingBuckets($today, $thresholds);

            if ($toRecord === []) {
                return $empty;
            }

            $overdue = $this->overdue($today);
            $data = $this->reportData($today, $buckets, $overdue);
            $pdfPath = $this->storePdf($today, $data);

            $recipients = DB::transaction(function () use ($toRecord, $today, $data, $pdfPath): int {
                $now = now();
                AccountsPayableDueNotice::query()->insertOrIgnore(array_map(fn (array $row): array => [...$row, 'sent_at' => $now], $toRecord));

                return $this->notifyAdministrators($today, $data, $pdfPath);
            });

            return [
                'sent' => true,
                'notices' => count($toRecord),
                'recipients' => $recipients,
                'pdf_path' => $pdfPath,
                'buckets' => array_map(fn (Collection $rows): int => $rows->count(), $buckets),
                'overdue' => $overdue->count(),
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Arma el PDF (sin registrar avisos ni enviar), por ejemplo para descargarlo a mano.
     */
    public function previewPdf(?CarbonImmutable $today = null): string
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $thresholds = $this->thresholds();
        $max = $thresholds === [] ? 0 : max($thresholds);

        $upcoming = $this->openPayables()
            ->whereDate('due_at', '>=', $today->toDateString())
            ->whereDate('due_at', '<=', $today->addDays($max)->toDateString())
            ->get();

        $buckets = [];
        foreach ($upcoming as $payable) {
            $bucket = $this->closestThreshold($this->daysLeft($payable, $today), $thresholds);
            if ($bucket !== null) {
                $buckets[$bucket] ??= collect();
                $buckets[$bucket]->push($payable);
            }
        }
        krsort($buckets);

        return Pdf::loadView('pdf.accounts-payable-due-notice', $this->reportData($today, $buckets, $this->overdue($today)))
            ->setPaper('a4', 'portrait')
            ->output();
    }

    /**
     * @param  list<int>  $thresholds  ordenados de mayor a menor
     * @return array{0: array<int, Collection<int, AccountsPayable>>, 1: list<array{accounts_payable_id: int, threshold_days: int, due_at: string}>}
     */
    private function pendingBuckets(CarbonImmutable $today, array $thresholds): array
    {
        $candidates = $this->openPayables()
            ->whereDate('due_at', '>=', $today->toDateString())
            ->whereDate('due_at', '<=', $today->addDays(max($thresholds))->toDateString())
            ->get();

        $sent = AccountsPayableDueNotice::query()
            ->whereIn('accounts_payable_id', $candidates->modelKeys())
            ->get(['accounts_payable_id', 'threshold_days', 'due_at'])
            ->map(fn (AccountsPayableDueNotice $notice): string => $notice->accounts_payable_id.'|'.$notice->threshold_days.'|'.$notice->due_at->toDateString())
            ->flip();

        $buckets = [];
        $toRecord = [];

        foreach ($candidates as $payable) {
            $daysLeft = $this->daysLeft($payable, $today);
            $dueAt = CarbonImmutable::parse($payable->due_at)->toDateString();
            $reached = array_values(array_filter($thresholds, fn (int $threshold): bool => $daysLeft <= $threshold));
            $pending = array_values(array_filter($reached, fn (int $threshold): bool => ! $sent->has($payable->getKey().'|'.$threshold.'|'.$dueAt)));

            if ($pending === []) {
                continue;
            }

            $bucket = min($reached);
            $buckets[$bucket] ??= collect();
            $buckets[$bucket]->push($payable);

            foreach ($pending as $threshold) {
                $toRecord[] = ['accounts_payable_id' => (int) $payable->getKey(), 'threshold_days' => $threshold, 'due_at' => $dueAt];
            }
        }

        krsort($buckets);

        return [$buckets, $toRecord];
    }

    /**
     * @return Builder<AccountsPayable>
     */
    private function openPayables(): Builder
    {
        return AccountsPayable::query()
            ->where('status', AccountsPayableStatus::POR_PAGAR)
            ->whereNotNull('due_at')
            ->with(['branch:id,name', 'purchase:id,supplier_id', 'purchase.supplier:id,legal_name,trade_name,tax_id', 'purchase.supplier.bankAccounts'])
            ->orderBy('due_at')
            ->orderBy('supplier_name');
    }

    /**
     * @return Collection<int, AccountsPayable>
     */
    private function overdue(CarbonImmutable $today): Collection
    {
        return $this->openPayables()->whereDate('due_at', '<', $today->toDateString())->get();
    }

    /**
     * @param  array<int, Collection<int, AccountsPayable>>  $buckets
     * @param  Collection<int, AccountsPayable>  $overdue
     * @return array<string, mixed>
     */
    private function reportData(CarbonImmutable $today, array $buckets, Collection $overdue): array
    {
        $sections = [];

        foreach ($buckets as $threshold => $payables) {
            $sections[] = [
                'title' => 'Por vencer en '.$threshold.' días',
                'subtitle' => 'Vencen entre el '.$today->format('d/m/Y').' y el '.$today->addDays($threshold)->format('d/m/Y'),
                'tone' => $threshold <= 3 ? 'danger' : 'warning',
                'suppliers' => $this->groupBySupplier($payables, $today),
                'count' => $payables->count(),
                'total_usd' => $this->sum($payables, 'remaining_principal_usd'),
                'total_ves' => $this->sum($payables, 'current_balance_ves'),
            ];
        }

        $upcoming = collect($buckets)->flatten(1);
        $logoPath = public_path('images/logos/farmadoc-ligth.png');

        return [
            'today' => $today,
            'generated_at' => now(),
            'sections' => $sections,
            'upcoming_count' => $upcoming->count(),
            'upcoming_total_usd' => $this->sum($upcoming, 'remaining_principal_usd'),
            'upcoming_total_ves' => $this->sum($upcoming, 'current_balance_ves'),
            'overdue_rows' => $overdue->map(fn (AccountsPayable $payable): array => $this->row($payable, $today))->all(),
            'overdue_count' => $overdue->count(),
            'overdue_total_usd' => $this->sum($overdue, 'remaining_principal_usd'),
            'overdue_total_ves' => $this->sum($overdue, 'current_balance_ves'),
            'logo' => is_readable($logoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath)) : null,
        ];
    }

    /**
     * @param  Collection<int, AccountsPayable>  $payables
     * @return list<array<string, mixed>>
     */
    private function groupBySupplier(Collection $payables, CarbonImmutable $today): array
    {
        return $payables
            ->groupBy(fn (AccountsPayable $payable): string => (string) ($payable->purchase?->supplier_id ?? 'x-'.$payable->supplier_tax_id))
            ->map(function (Collection $rows) use ($today): array {
                /** @var AccountsPayable $first */
                $first = $rows->first();
                $supplier = $first->purchase?->supplier;

                return [
                    'name' => $supplier instanceof Supplier ? ($supplier->trade_name ?: $supplier->legal_name) : ($first->supplier_name ?: '—'),
                    'tax_id' => $supplier?->tax_id ?? $first->supplier_tax_id,
                    'bank_accounts' => $supplier instanceof Supplier
                        ? $supplier->bankAccounts->map(fn (SupplierBankAccount $account): string => $account->displayLabel().(filled($account->phone) ? ' · Pago Móvil '.$account->phone : ''))->all()
                        : [],
                    'rows' => $rows->map(fn (AccountsPayable $payable): array => $this->row($payable, $today))->all(),
                    'total_usd' => $this->sum($rows, 'remaining_principal_usd'),
                    'total_ves' => $this->sum($rows, 'current_balance_ves'),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(AccountsPayable $payable, CarbonImmutable $today): array
    {
        $daysLeft = $this->daysLeft($payable, $today);

        return [
            'supplier' => $payable->purchase?->supplier?->trade_name ?: ($payable->purchase?->supplier?->legal_name ?: ($payable->supplier_name ?: '—')),
            'invoice' => $payable->supplier_invoice_number ?: '—',
            'control' => $payable->supplier_control_number ?: '—',
            'branch' => $payable->branch?->name ?? '—',
            'issued_at' => $payable->issued_at ? CarbonImmutable::parse($payable->issued_at)->format('d/m/Y') : '—',
            'due_at' => CarbonImmutable::parse($payable->due_at)->format('d/m/Y'),
            'days' => $daysLeft,
            'days_label' => match (true) {
                $daysLeft < 0 => 'Hace '.abs($daysLeft).' d',
                $daysLeft === 0 => 'Vence hoy',
                $daysLeft === 1 => 'Mañana',
                default => 'En '.$daysLeft.' d',
            },
            'usd' => round((float) $payable->remaining_principal_usd, 2),
            'ves' => round((float) $payable->current_balance_ves, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storePdf(CarbonImmutable $today, array $data): string
    {
        $path = trim((string) config('accounts_payable.due_notice_directory'), '/')
            .'/facturas-por-pagar-'.$today->format('Y-m-d').'-'.now()->format('His').'.pdf';

        Storage::disk($this->disk())->put($path, Pdf::loadView('pdf.accounts-payable-due-notice', $data)->setPaper('a4', 'portrait')->output());

        return $path;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notifyAdministrators(CarbonImmutable $today, array $data, string $pdfPath): int
    {
        $administrators = User::query()->get()->filter(fn (User $user): bool => $user->isAdministrator())->values();
        $headline = self::headline($today, (int) $data['upcoming_count']);
        $lines = self::summaryLines($data);

        foreach ($administrators as $user) {
            try {
                Notification::make()
                    ->title($headline)
                    ->body(implode(' · ', $lines))
                    ->warning()
                    ->actions([
                        Action::make('open')
                            ->label('Ver cuentas por pagar')
                            ->url(route('filament.farmaadmin.resources.accounts-payables.index'))
                            ->markAsRead(),
                    ])
                    ->sendToDatabase($user);
            } catch (Throwable $exception) {
                Log::warning('CxP avisos de vencimiento: no se pudo guardar el aviso de panel', ['user_id' => $user->getKey(), 'error' => $exception->getMessage()]);
            }

            if (filled($user->email)) {
                SendAccountsPayableDueNoticeJob::dispatch((int) $user->getKey(), SendAccountsPayableDueNoticeJob::CHANNEL_MAIL, $pdfPath, $headline, $lines);
            }

            if (WhatsAppLink::normalizePhoneDigits($user->whatsapp_phone) !== null) {
                SendAccountsPayableDueNoticeJob::dispatch((int) $user->getKey(), SendAccountsPayableDueNoticeJob::CHANNEL_WHATSAPP, $pdfPath, $headline, $lines);
            }
        }

        return $administrators->count();
    }

    public static function headline(CarbonImmutable $today, int $count): string
    {
        return $count === 1
            ? 'Facturas de proveedores: 1 por vencer'
            : "Facturas de proveedores: {$count} por vencer";
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function summaryLines(array $data): array
    {
        $lines = [];

        foreach ($data['sections'] as $section) {
            $lines[] = sprintf('%s: %d factura(s) · %s · %s', $section['title'], $section['count'], self::usd($section['total_usd']), self::ves($section['total_ves']));
        }

        if ((int) $data['overdue_count'] > 0) {
            $lines[] = sprintf('Vencidas sin pagar: %d factura(s) · %s · %s', $data['overdue_count'], self::usd($data['overdue_total_usd']), self::ves($data['overdue_total_ves']));
        }

        return $lines;
    }

    public static function usd(float $amount): string
    {
        return '$'.number_format($amount, 2, ',', '.');
    }

    public static function ves(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }

    public function disk(): string
    {
        return (string) config('accounts_payable.due_notice_disk', 'local');
    }

    /**
     * @return list<int>
     */
    private function thresholds(): array
    {
        $values = array_values(array_unique(array_filter(
            array_map('intval', (array) config('accounts_payable.due_notice_days', [])),
            fn (int $days): bool => $days >= 0,
        )));
        rsort($values);

        return $values;
    }

    /**
     * @param  list<int>  $thresholds
     */
    private function closestThreshold(int $daysLeft, array $thresholds): ?int
    {
        $reached = array_filter($thresholds, fn (int $threshold): bool => $daysLeft <= $threshold);

        return $reached === [] ? null : min($reached);
    }

    private function daysLeft(AccountsPayable $payable, CarbonImmutable $today): int
    {
        return (int) $today->diffInDays(CarbonImmutable::parse($payable->due_at)->startOfDay(), false);
    }

    /**
     * @param  Collection<int, AccountsPayable>  $payables
     */
    private function sum(Collection $payables, string $column): float
    {
        return round((float) $payables->sum(fn (AccountsPayable $payable): float => (float) $payable->getAttribute($column)), 2);
    }
}
