<?php

namespace App\Filament\Pages;

use App\Enums\VenezuelanPagoMovilBank;
use App\Models\PhysicalCashBox;
use App\Models\PhysicalCashBoxMovement;
use App\Models\PosTerminal;
use App\Models\User;
use App\Services\Sales\PhysicalCashBoxCloseService;
use App\Support\Cash\CashierShiftLock;
use App\Support\Cash\NotifyOnPhysicalCashBoxOpen;
use App\Support\Cash\PhysicalCashBoxBillingGate;
use App\Support\Cash\UsdBillDenominationCalculator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Throwable;
use UnitEnum;

/**
 * Apertura y cierre de la caja física (efectivo para vueltos), solo para rol CAJERO.
 */
final class CashierPhysicalCashBoxPage extends Page implements HasActions
{
    use InteractsWithActions;

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

    /**
     * Cantidad de billetes por denominación USD al abrir caja.
     *
     * @var array<string, string>
     */
    public array $openUsdBillCounts = [];

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

    public bool $branchDailyOperationIsOpen = false;

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($this->isManagementUser($user)) {
            $this->isManagementView = true;

            return;
        }

        if (! $user->isCashier()) {
            abort(403);
        }

        $this->isCashierView = true;
        $this->resetOpenUsdBillCounts();
        $box = $this->resolveBox();
        $this->syncInputsFromBox($box);
        $this->syncPublicBoxState($box);
        $this->syncBranchDailyOperationState($user);
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

        if (! PhysicalCashBoxBillingGate::userMayOpenPhysicalCashBox($user)) {
            Notification::make()
                ->title('Sucursal no aperturada')
                ->body('Gerencia o administración debe aperturar la sucursal antes de que pueda abrir su caja física.')
                ->warning()
                ->send();

            return;
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
        $this->syncOpenUsdFromBillCounts();

        $billCountRules = [];
        $billCountMessages = [];
        foreach (UsdBillDenominationCalculator::denominations() as $denomination) {
            $billCountRules['openUsdBillCounts.'.$denomination] = ['nullable', 'integer', 'min:0', 'max:9999'];
            $billCountMessages['openUsdBillCounts.'.$denomination.'.integer'] = 'La cantidad de billetes US$'.$denomination.' debe ser un número entero.';
            $billCountMessages['openUsdBillCounts.'.$denomination.'.min'] = 'La cantidad de billetes US$'.$denomination.' no puede ser negativa.';
        }

        $this->validate(array_merge([
            'openUsd' => ['required', 'numeric', 'min:0'],
            'openVes' => ['required', 'numeric', 'min:0'],
        ], $billCountRules), array_merge([
            'openUsd.required' => 'Indique al menos una denominación en USD o confirme el total calculado.',
            'openVes.required' => 'Indique el monto inicial en VES.',
        ], $billCountMessages));

        $usd = round((float) str_replace(',', '.', $this->openUsd), 2);
        $ves = round((float) str_replace(',', '.', $this->openVes), 2);

        DB::transaction(function () use ($box, $usd, $ves): void {
            $box->forceFill([
                'amount_usd' => $usd,
                'amount_ves' => $ves,
                'is_open' => true,
                'opened_at' => now(),
                'closed_at' => null,
                'close_usd_cash_photo_path' => null,
                'close_pos_receipt_photo_path' => null,
            ])->save();
        });

        CashierShiftLock::clear($user);

        $fresh = $box->fresh() ?? $box;

        try {
            app(NotifyOnPhysicalCashBoxOpen::class)->notify($user, $fresh, $usd, $ves);
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar WhatsApp de apertura de caja física', [
                'cashier_id' => $user->getKey(),
                'physical_cash_box_id' => $fresh->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        $this->syncInputsFromBox($fresh);
        $this->syncPublicBoxState($fresh);

        Notification::make()
            ->title('Caja abierta')
            ->body('Apertura registrada. Montos en caja: USD '.number_format($usd, 2).' · VES '.number_format($ves, 2, ',', '.').'.')
            ->success()
            ->send();
    }

    public function closePhysicalCashBoxAction(): Action
    {
        return Action::make('closePhysicalCashBox')
            ->label('Cerrar caja física')
            ->color('gray')
            ->modalHeading('Confirmar cierre de caja física')
            ->modalDescription(fn (): string => 'Declare los totales de punto de venta por banco, el efectivo USD/VES y adjunte las fotos. Al confirmar, se cerrará su turno y no podrá ingresar al sistema hasta las '
                .CashierShiftLock::dailyUnlockTimeLabel()
                .' del día siguiente (salvo que un administrador habilite su acceso antes).')
            ->modalIcon(Heroicon::Camera)
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel('Confirmar cierre')
            ->closeModalByClickingAway(false)
            ->fillForm(fn (): array => [
                'close_usd' => $this->closeUsd,
                'close_ves' => $this->closeVes,
                'pos_declarations' => $this->defaultPosDeclarationsForClose(),
            ])
            ->schema([
                Repeater::make('pos_declarations')
                    ->label('Total en punto de venta')
                    ->helperText('Seleccione el banco de cada punto y cargue el total en bolívares. Si tiene varios puntos, añada una línea por banco. El sistema comparará contra las ventas de esta caja en ese banco.')
                    ->addActionLabel('Añadir punto de venta')
                    ->reorderable(false)
                    ->defaultItems(0)
                    ->schema([
                        Select::make('bank_code')
                            ->label('Banco del punto')
                            ->options(fn (): array => $this->posBankOptionsForClose())
                            ->required()
                            ->searchable()
                            ->native(false),
                        TextInput::make('amount_ves')
                            ->label('Total del punto (Bs.)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->inputMode('decimal'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                TextInput::make('close_usd')
                    ->label('Total en dólares en la caja física')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->inputMode('decimal'),
                TextInput::make('close_ves')
                    ->label('Total en bolívares en la caja física')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->inputMode('decimal'),
                FileUpload::make('close_usd_cash_photo')
                    ->label('Foto del efectivo USD en caja')
                    ->helperText('Tome una foto clara de los billetes en dólares que quedan en la caja física.')
                    ->disk('local')
                    ->directory('physical-cash-box/closures/usd-cash')
                    ->visibility('private')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(5120)
                    ->required(),
                FileUpload::make('close_pos_receipt_photo')
                    ->label('Foto del cierre del punto de venta')
                    ->helperText('Adjunte la captura o foto del reporte de cierre del POS.')
                    ->disk('local')
                    ->directory('physical-cash-box/closures/pos-receipt')
                    ->visibility('private')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(5120)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->closeUsd = (string) ($data['close_usd'] ?? $this->closeUsd);
                $this->closeVes = (string) ($data['close_ves'] ?? $this->closeVes);

                $this->finalizePhysicalCashBoxClose(
                    usdCashPhotoPath: $this->normalizeUploadedPhotoPath($data['close_usd_cash_photo'] ?? null),
                    posReceiptPhotoPath: $this->normalizeUploadedPhotoPath($data['close_pos_receipt_photo'] ?? null),
                    posDeclarations: $this->normalizePosDeclarations($data['pos_declarations'] ?? []),
                );
            });
    }

    public function viewClosePhotosAction(): Action
    {
        return Action::make('viewClosePhotos')
            ->label('Ver fotos de cierre')
            ->icon(Heroicon::Photo)
            ->modalHeading(function (array $arguments): string {
                $box = $this->administratorCashBox((int) ($arguments['box'] ?? 0));

                $cashier = (string) ($box->user?->name ?? 'Cajero');

                return 'Fotos de cierre · '.$cashier;
            })
            ->modalDescription('Foto del efectivo en dólares y foto del cierre del punto de venta que cargó el cajero al cerrar la caja.')
            ->modalIcon(Heroicon::Photo)
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cerrar')->color('gray'))
            ->modalContent(function (array $arguments): View {
                $box = $this->administratorCashBox((int) ($arguments['box'] ?? 0));

                return view('filament.pages.partials.physical-cash-box-close-photos', [
                    'usdUrl' => filled($box->close_usd_cash_photo_path)
                        ? route('physical-cash-box.close-photo', ['box' => $box, 'kind' => 'usd'])
                        : null,
                    'posUrl' => filled($box->close_pos_receipt_photo_path)
                        ? route('physical-cash-box.close-photo', ['box' => $box, 'kind' => 'pos'])
                        : null,
                ]);
            });
    }

    private function normalizeUploadedPhotoPath(mixed $value): string
    {
        if (is_array($value)) {
            $first = reset($value);

            return is_string($first) ? trim($first) : '';
        }

        return is_string($value) ? trim($value) : '';
    }

    private function finalizePhysicalCashBoxClose(
        string $usdCashPhotoPath,
        string $posReceiptPhotoPath,
        array $posDeclarations,
    ): void {
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

        if ($usdCashPhotoPath === '' || $posReceiptPhotoPath === '') {
            Notification::make()
                ->title('Faltan las fotos de cierre')
                ->body('Debe adjuntar la foto del efectivo USD y la del cierre del punto de venta.')
                ->danger()
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

        try {
            app(PhysicalCashBoxCloseService::class)->close(
                cashier: $user,
                physicalCashBox: $box,
                declaredUsd: $usd,
                declaredVes: $ves,
                usdCashPhotoPath: $usdCashPhotoPath,
                posReceiptPhotoPath: $posReceiptPhotoPath,
                posDeclarations: $posDeclarations,
            );
        } catch (Throwable $exception) {
            Log::warning('No se pudo completar el cierre de caja física', [
                'cashier_id' => $user->getKey(),
                'physical_cash_box_id' => $box->getKey(),
                'error' => $exception->getMessage(),
            ]);

            $box->refresh();
            if ($box->is_open) {
                Notification::make()
                    ->title('No se pudo cerrar la caja')
                    ->body($exception->getMessage())
                    ->danger()
                    ->send();

                return;
            }
        }

        CashierShiftLock::lockAfterShiftClose($user);

        $unlockLabel = CashierShiftLock::dailyUnlockTimeLabel();
        $successMessage = 'Caja física cerrada correctamente. Podrá ingresar nuevamente a las '.$unlockLabel.' o cuando un administrador habilite su acceso.';
        session()->flash('cashier_physical_close_success', $successMessage);

        Auth::guard(Filament::getAuthGuard())->logout();
        session()->regenerate();

        $this->redirect(Filament::getLoginUrl(), navigate: false);
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

    private function syncBranchDailyOperationState(User $user): void
    {
        $this->branchDailyOperationIsOpen = PhysicalCashBoxBillingGate::branchDailyOperationIsOpen($user);
    }

    public function refreshCashBoxSnapshot(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $this->isCashierView) {
            return;
        }

        $this->syncPublicBoxState($this->resolveBox());
        $this->syncBranchDailyOperationState($user);
    }

    public function updatedOpenUsdBillCounts(mixed $value, string $key): void
    {
        $this->openUsdBillCounts[$key] = (string) UsdBillDenominationCalculator::normalizeQuantity(
            $this->openUsdBillCounts[$key] ?? 0,
        );
        $this->syncOpenUsdFromBillCounts();
    }

    /**
     * @return list<array{denomination: int, quantity: int, subtotal: float}>
     */
    public function openUsdBillBreakdown(): array
    {
        return UsdBillDenominationCalculator::breakdownFromCounts($this->openUsdBillCounts);
    }

    public function updatedCloseUsd(mixed $value): void
    {
        $this->closeUsd = (string) $value;
    }

    public function updatedCloseVes(mixed $value): void
    {
        $this->closeVes = (string) $value;
    }

    /**
     * @return list<array{
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
    #[Computed]
    public function branchCashBoxes(): array
    {
        if (! $this->isManagementView) {
            return [];
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return [];
        }

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
                return [];
            }

            $boxesQuery->whereHas(
                'user',
                fn ($q) => $q->whereIn('branch_id', $branchIds),
            );
        }

        return $boxesQuery
            ->get()
            ->map(static function (PhysicalCashBox $box): array {
                return [
                    'id' => (int) $box->getKey(),
                    'has_close_photos' => filled($box->close_usd_cash_photo_path) || filled($box->close_pos_receipt_photo_path),
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
    }

    /**
     * @return list<array{
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
    #[Computed]
    public function recentMovements(): array
    {
        if ($this->isManagementView) {
            $user = Auth::user();
            if (! $user instanceof User) {
                return [];
            }

            return $this->queryManagementMovements($user);
        }

        if (! $this->isCashierView) {
            return [];
        }

        return $this->queryCashierMovements($this->resolveBox());
    }

    /**
     * @return array{
     *     expected_usd: float,
     *     expected_ves: float,
     *     declared_usd: float,
     *     declared_ves: float,
     *     difference_usd: float,
     *     difference_ves: float,
     *     has_mismatch: bool
     * }
     */
    #[Computed]
    public function closeReconciliation(): array
    {
        if (! $this->isCashierView) {
            return [
                'expected_usd' => 0.0,
                'expected_ves' => 0.0,
                'declared_usd' => 0.0,
                'declared_ves' => 0.0,
                'difference_usd' => 0.0,
                'difference_ves' => 0.0,
                'has_mismatch' => false,
            ];
        }

        $expectedUsd = round(max(0.0, (float) $this->boxAmountUsd), 2);
        $expectedVes = round(max(0.0, (float) $this->boxAmountVes), 2);
        $declaredUsd = round(max(0.0, $this->parseMonetaryInput($this->closeUsd)), 2);
        $declaredVes = round(max(0.0, $this->parseMonetaryInput($this->closeVes)), 2);
        $differenceUsd = round($declaredUsd - $expectedUsd, 2);
        $differenceVes = round($declaredVes - $expectedVes, 2);

        return [
            'expected_usd' => $expectedUsd,
            'expected_ves' => $expectedVes,
            'declared_usd' => $declaredUsd,
            'declared_ves' => $declaredVes,
            'difference_usd' => $differenceUsd,
            'difference_ves' => $differenceVes,
            'has_mismatch' => abs($differenceUsd) >= 0.01 || abs($differenceVes) >= 0.01,
        ];
    }

    /**
     * @return list<array{
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
    private function queryCashierMovements(PhysicalCashBox $box): array
    {
        return PhysicalCashBoxMovement::query()
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

    /**
     * @return list<array{
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
    private function queryManagementMovements(User $user): array
    {
        $branchIds = $this->scopedBranchIdsForManagement($user);

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
            if ($branchIds === []) {
                return [];
            }

            $movementsQuery->whereHas(
                'sale',
                fn ($q) => $q->whereIn('branch_id', $branchIds),
            );
        }

        return $movementsQuery
            ->get()
            ->map(fn (PhysicalCashBoxMovement $movement): array => $this->mapMovementForView($movement))
            ->values()
            ->all();
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
            $this->resetOpenUsdBillCounts();
            $this->openVes = '0';
        } else {
            $this->resetOpenUsdBillCounts();
            $this->openVes = '0';
            $this->closeUsd = (string) $box->amount_usd;
            $this->closeVes = (string) $box->amount_ves;
        }
    }

    private function resetOpenUsdBillCounts(): void
    {
        foreach (UsdBillDenominationCalculator::denominations() as $denomination) {
            $this->openUsdBillCounts[(string) $denomination] = '0';
        }

        $this->syncOpenUsdFromBillCounts();
    }

    private function syncOpenUsdFromBillCounts(): void
    {
        $total = UsdBillDenominationCalculator::totalFromCounts($this->openUsdBillCounts);
        $this->openUsd = number_format($total, 2, '.', '');
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

    private function administratorCashBox(int $boxId): PhysicalCashBox
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isAdministrator(), 403);

        return PhysicalCashBox::query()
            ->with(['user:id,name'])
            ->findOrFail($boxId);
    }

    private function isManagementUser(User $user): bool
    {
        return $user->isAdministrator() || $user->hasGerenciaRole();
    }

    /**
     * @return list<array{bank_code: string, amount_ves: string}>
     */
    private function defaultPosDeclarationsForClose(): array
    {
        $options = $this->posBankOptionsForClose();
        if ($options === []) {
            return [];
        }

        $lines = [];
        foreach (array_keys($options) as $bankCode) {
            $lines[] = [
                'bank_code' => (string) $bankCode,
                'amount_ves' => '0',
            ];
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private function posBankOptionsForClose(): array
    {
        $user = Auth::user();
        if (! $user instanceof User || ! filled($user->branch_id)) {
            return VenezuelanPagoMovilBank::optionsForSelect();
        }

        $bankCodes = PosTerminal::query()
            ->where('branch_id', (int) $user->branch_id)
            ->where('is_active', true)
            ->whereNotNull('bank_code')
            ->orderBy('bank_code')
            ->pluck('bank_code')
            ->filter()
            ->unique()
            ->values();

        if ($bankCodes->isEmpty()) {
            return VenezuelanPagoMovilBank::optionsForSelect();
        }

        $options = [];
        foreach ($bankCodes as $bankCode) {
            $code = (string) $bankCode;
            $options[$code] = VenezuelanPagoMovilBank::tryFrom($code)?->optionLabel() ?? $code;
        }

        return $options;
    }

    /**
     * @return list<array{bank_code: string, amount_ves: float}>
     */
    private function normalizePosDeclarations(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $merged = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $bankCode = trim((string) ($row['bank_code'] ?? ''));
            if ($bankCode === '') {
                continue;
            }

            $amount = $this->parseMonetaryInput((string) ($row['amount_ves'] ?? '0'));
            $merged[$bankCode] = round(($merged[$bankCode] ?? 0.0) + max(0.0, $amount), 2);
        }

        $lines = [];
        foreach ($merged as $bankCode => $amount) {
            $lines[] = [
                'bank_code' => $bankCode,
                'amount_ves' => $amount,
            ];
        }

        return $lines;
    }

    private function parseMonetaryInput(string $value): float
    {
        $normalized = trim(str_replace(',', '.', $value));

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }
}
