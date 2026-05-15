<?php

namespace App\Filament\Pages;

use App\Models\PhysicalCashBox;
use App\Models\PhysicalCashBoxMovement;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Apertura y cierre de la caja física (efectivo para vueltos), solo para rol CAJERO.
 */
final class CashierPhysicalCashBoxPage extends Page
{
    /**
     * Registrada solo vía `pages()` del panel Farmaadmin (no por descubrimiento automático), para evitar duplicados en el menú.
     */
    protected static bool $isDiscovered = false;

    protected static ?string $slug = 'caja-fisica';

    protected static ?string $navigationLabel = 'Caja física';

    protected static ?int $navigationSort = 8;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected string $view = 'filament.pages.cashier-physical-cash-box';

    public string $openUsd = '0';

    public string $openVes = '0';

    public string $closeUsd = '0';

    public string $closeVes = '0';

    public bool $boxIsOpen = false;

    public string $boxAmountUsd = '0';

    public string $boxAmountVes = '0';

    public ?string $boxOpenedAtForView = null;

    public ?string $boxClosedAtForView = null;

    public bool $isCashierView = false;

    public bool $isManagementView = false;

    /**
     * @var list<array{
     *     branch_name: string,
     *     cashier_name: string,
     *     is_open: bool,
     *     amount_usd: float,
     *     amount_ves: float,
     *     opened_at: string,
     *     closed_at: string,
     *     movements_count: int
     * }>
     */
    public array $branchCashBoxes = [];

    /**
     * @var array{
     *     expected_usd: float,
     *     expected_ves: float,
     *     declared_usd: float,
     *     declared_ves: float,
     *     difference_usd: float,
     *     difference_ves: float,
     *     has_mismatch: bool
     * }
     */
    public array $closeReconciliation = [
        'expected_usd' => 0.0,
        'expected_ves' => 0.0,
        'declared_usd' => 0.0,
        'declared_ves' => 0.0,
        'difference_usd' => 0.0,
        'difference_ves' => 0.0,
        'has_mismatch' => false,
    ];

    /**
     * @var list<array{
     *     created_at: string,
     *     branch_name: string,
     *     cashier_name: string,
     *     sale_number: string,
     *     client_bill_usd: float,
     *     drawer_out_usd: float,
     *     final_change_ves: float,
     *     usd_delta: float,
     *     ves_delta: float
     * }>
     */
    public array $recentMovements = [];

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($this->isManagementUser($user)) {
            $this->isManagementView = true;
            $this->syncManagementSnapshot($user);

            return;
        }

        if (! $user->isCashier()) {
            abort(403);
        }

        $this->isCashierView = true;
        $box = $this->resolveBox();
        $this->syncInputsFromBox($box);
        $this->syncPublicBoxState($box);
        $this->syncCashierRecentMovements($box);
        $this->syncCloseReconciliation();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $user = Auth::user();

        return $user instanceof User ? $user->navigationOperationsGroupLabel() : 'Farmadoc®';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Caja física';
    }

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->isManagementView) {
            return 'Monitoreo en tiempo real de aperturas, saldos y movimientos de cajas físicas por sucursal.';
        }

        return 'Apertura y cierre del efectivo en caja para vueltos (USD y VES). Solo usted ve esta pantalla.';
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && ($user->isCashier() || $user->isAdministrator() || $user->hasGerenciaRole());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    public function openCashBox(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isCashier()) {
            abort(403);
        }

        $box = $this->resolveBox();
        if ($box->is_open) {
            Notification::make()
                ->title('La caja ya está abierta')
                ->body('Cierre el turno actual antes de registrar una nueva apertura.')
                ->warning()
                ->send();

            return;
        }

        $this->openUsd = str_replace(',', '.', (string) $this->openUsd);
        $this->openVes = str_replace(',', '.', (string) $this->openVes);

        $this->validate([
            'openUsd' => ['required', 'numeric', 'min:0'],
            'openVes' => ['required', 'numeric', 'min:0'],
        ], [
            'openUsd.required' => 'Indique el monto inicial en USD.',
            'openVes.required' => 'Indique el monto inicial en VES.',
        ]);

        $usd = round((float) str_replace(',', '.', $this->openUsd), 2);
        $ves = round((float) str_replace(',', '.', $this->openVes), 2);

        DB::transaction(function () use ($box, $usd, $ves): void {
            $box->forceFill([
                'amount_usd' => $usd,
                'amount_ves' => $ves,
                'is_open' => true,
                'opened_at' => now(),
                'closed_at' => null,
            ])->save();
        });

        $fresh = $box->fresh();
        $this->syncInputsFromBox($fresh);
        $this->syncPublicBoxState($fresh);
        $this->syncCashierRecentMovements($fresh);
        $this->syncCloseReconciliation();

        Notification::make()
            ->title('Caja abierta')
            ->body('Apertura registrada. Montos en caja: USD '.number_format($usd, 2).' · VES '.number_format($ves, 2, ',', '.').'.')
            ->success()
            ->send();
    }

    public function closeCashBox(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isCashier()) {
            abort(403);
        }

        $box = $this->resolveBox();
        if (! $box->is_open) {
            Notification::make()
                ->title('La caja no está abierta')
                ->body('Abra la caja primero con los montos iniciales del turno.')
                ->warning()
                ->send();

            return;
        }

        $this->closeUsd = str_replace(',', '.', (string) $this->closeUsd);
        $this->closeVes = str_replace(',', '.', (string) $this->closeVes);

        $this->validate([
            'closeUsd' => ['required', 'numeric', 'min:0'],
            'closeVes' => ['required', 'numeric', 'min:0'],
        ], [
            'closeUsd.required' => 'Indique el monto al cierre en USD.',
            'closeVes.required' => 'Indique el monto al cierre en VES.',
        ]);

        $usd = round((float) str_replace(',', '.', $this->closeUsd), 2);
        $ves = round((float) str_replace(',', '.', $this->closeVes), 2);

        DB::transaction(function () use ($box, $usd, $ves): void {
            $box->forceFill([
                'amount_usd' => $usd,
                'amount_ves' => $ves,
                'is_open' => false,
                'closed_at' => now(),
            ])->save();
        });

        $fresh = $box->fresh();
        $this->syncInputsFromBox($fresh);
        $this->syncPublicBoxState($fresh);
        $this->syncCashierRecentMovements($fresh);
        $this->syncCloseReconciliation();

        Notification::make()
            ->title('Caja cerrada')
            ->body('Cierre registrado. Montos declarados: USD '.number_format($usd, 2).' · VES '.number_format($ves, 2, ',', '.').'.')
            ->success()
            ->send();
    }

    private function syncPublicBoxState(PhysicalCashBox $box): void
    {
        $box->refresh();
        $this->boxIsOpen = (bool) $box->is_open;
        $this->boxAmountUsd = (string) $box->amount_usd;
        $this->boxAmountVes = (string) $box->amount_ves;
        $tz = config('app.timezone');
        $this->boxOpenedAtForView = $box->opened_at !== null
            ? $box->opened_at->timezone((string) $tz)->format('d/m/Y H:i')
            : null;
        $this->boxClosedAtForView = $box->closed_at !== null
            ? $box->closed_at->timezone((string) $tz)->format('d/m/Y H:i')
            : null;
    }

    public function refreshCashBoxSnapshot(): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        if ($this->isManagementView) {
            $this->syncManagementSnapshot($user);

            return;
        }

        if (! $this->isCashierView) {
            return;
        }

        $box = $this->resolveBox();
        $this->syncPublicBoxState($box);
        $this->syncCashierRecentMovements($box);
        $this->syncCloseReconciliation();
    }

    public function updatedCloseUsd(mixed $value): void
    {
        $this->closeUsd = (string) $value;
        $this->syncCloseReconciliation();
    }

    public function updatedCloseVes(mixed $value): void
    {
        $this->closeVes = (string) $value;
        $this->syncCloseReconciliation();
    }

    private function resolveBox(): PhysicalCashBox
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        return PhysicalCashBox::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'amount_usd' => 0,
                'amount_ves' => 0,
                'is_open' => false,
            ],
        );
    }

    private function syncInputsFromBox(PhysicalCashBox $box): void
    {
        if ($box->is_open) {
            $this->closeUsd = (string) $box->amount_usd;
            $this->closeVes = (string) $box->amount_ves;
            $this->openUsd = '0';
            $this->openVes = '0';
        } else {
            $this->openUsd = '0';
            $this->openVes = '0';
            $this->closeUsd = (string) $box->amount_usd;
            $this->closeVes = (string) $box->amount_ves;
        }
    }

    private function syncCashierRecentMovements(PhysicalCashBox $box): void
    {
        $this->recentMovements = PhysicalCashBoxMovement::query()
            ->where('physical_cash_box_id', $box->id)
            ->with([
                'sale:id,sale_number,branch_id',
                'sale.branch:id,name',
                'physicalCashBox.user:id,name,branch_id',
                'physicalCashBox.user.branch:id,name',
            ])
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->map(fn (PhysicalCashBoxMovement $movement): array => $this->mapMovementForView($movement))
            ->values()
            ->all();
    }

    private function syncManagementSnapshot(User $user): void
    {
        $branchIds = $this->scopedBranchIdsForManagement($user);

        $boxesQuery = PhysicalCashBox::query()
            ->with([
                'user:id,name,branch_id',
                'user.branch:id,name',
            ])
            ->withCount('movements')
            ->latest('updated_at');

        if ($branchIds !== null) {
            if ($branchIds === []) {
                $this->branchCashBoxes = [];
                $this->recentMovements = [];

                return;
            }

            $boxesQuery->whereHas(
                'user',
                fn ($q) => $q->whereIn('branch_id', $branchIds),
            );
        }

        $this->branchCashBoxes = $boxesQuery
            ->get()
            ->map(static function (PhysicalCashBox $box): array {
                return [
                    'branch_name' => (string) ($box->user?->branch?->name ?? 'Sin sucursal'),
                    'cashier_name' => (string) ($box->user?->name ?? 'Cajero'),
                    'is_open' => (bool) $box->is_open,
                    'amount_usd' => round((float) $box->amount_usd, 2),
                    'amount_ves' => round((float) $box->amount_ves, 2),
                    'opened_at' => $box->opened_at !== null
                        ? $box->opened_at->timezone((string) config('app.timezone'))->format('d/m/Y H:i')
                        : '—',
                    'closed_at' => $box->closed_at !== null
                        ? $box->closed_at->timezone((string) config('app.timezone'))->format('d/m/Y H:i')
                        : '—',
                    'movements_count' => (int) ($box->movements_count ?? 0),
                ];
            })
            ->sortBy(['branch_name', 'cashier_name'])
            ->values()
            ->all();

        $movementsQuery = PhysicalCashBoxMovement::query()
            ->with([
                'sale:id,sale_number,branch_id',
                'sale.branch:id,name',
                'physicalCashBox.user:id,name,branch_id',
                'physicalCashBox.user.branch:id,name',
            ])
            ->latest('created_at')
            ->limit(100);

        if ($branchIds !== null) {
            $movementsQuery->whereHas(
                'sale',
                fn ($q) => $q->whereIn('branch_id', $branchIds),
            );
        }

        $this->recentMovements = $movementsQuery
            ->get()
            ->map(fn (PhysicalCashBoxMovement $movement): array => $this->mapMovementForView($movement))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     created_at: string,
     *     branch_name: string,
     *     cashier_name: string,
     *     sale_number: string,
     *     client_bill_usd: float,
     *     drawer_out_usd: float,
     *     final_change_ves: float,
     *     usd_delta: float,
     *     ves_delta: float
     * }
     */
    private function mapMovementForView(PhysicalCashBoxMovement $movement): array
    {
        $clientBillUsd = (float) $movement->client_bill_usd;
        $drawerOutUsd = (float) $movement->drawer_out_usd;
        $finalChangeVes = (float) ($movement->final_change_ves ?? 0);
        $branchName = $movement->sale?->branch?->name
            ?? $movement->physicalCashBox?->user?->branch?->name
            ?? 'Sin sucursal';

        return [
            'created_at' => $movement->created_at !== null
                ? $movement->created_at->timezone((string) config('app.timezone'))->format('d/m/Y H:i')
                : '—',
            'branch_name' => (string) $branchName,
            'cashier_name' => (string) ($movement->physicalCashBox?->user?->name ?? 'Cajero'),
            'sale_number' => (string) ($movement->sale?->sale_number ?? '—'),
            'client_bill_usd' => round($clientBillUsd, 2),
            'drawer_out_usd' => round($drawerOutUsd, 2),
            'final_change_ves' => round($finalChangeVes, 2),
            'usd_delta' => round($clientBillUsd - $drawerOutUsd, 2),
            'ves_delta' => round(-1 * abs($finalChangeVes), 2),
        ];
    }

    /**
     * @return list<int>|null
     */
    private function scopedBranchIdsForManagement(User $user): ?array
    {
        if ($user->isAdministrator()) {
            return null;
        }

        if (! $user->hasGerenciaRole()) {
            return [];
        }

        return $user->restrictedBranchIdsForQueries();
    }

    private function isManagementUser(User $user): bool
    {
        return $user->isAdministrator() || $user->hasGerenciaRole();
    }

    private function syncCloseReconciliation(): void
    {
        $expectedUsd = round(max(0.0, (float) $this->boxAmountUsd), 2);
        $expectedVes = round(max(0.0, (float) $this->boxAmountVes), 2);
        $declaredUsd = round(max(0.0, $this->parseMonetaryInput($this->closeUsd)), 2);
        $declaredVes = round(max(0.0, $this->parseMonetaryInput($this->closeVes)), 2);
        $differenceUsd = round($declaredUsd - $expectedUsd, 2);
        $differenceVes = round($declaredVes - $expectedVes, 2);

        $this->closeReconciliation = [
            'expected_usd' => $expectedUsd,
            'expected_ves' => $expectedVes,
            'declared_usd' => $declaredUsd,
            'declared_ves' => $declaredVes,
            'difference_usd' => $differenceUsd,
            'difference_ves' => $differenceVes,
            'has_mismatch' => abs($differenceUsd) >= 0.01 || abs($differenceVes) >= 0.01,
        ];
    }

    private function parseMonetaryInput(string $value): float
    {
        $normalized = trim(str_replace(',', '.', $value));

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }
}
