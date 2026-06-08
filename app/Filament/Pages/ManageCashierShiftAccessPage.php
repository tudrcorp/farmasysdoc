<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\Cash\CashierShiftLock;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Administración: habilitar ingreso de cajeros fuera del horario automático (6:00 AM).
 */
final class ManageCashierShiftAccessPage extends Page
{
    protected static bool $isDiscovered = false;

    protected static ?string $title = 'Acceso de cajeros (turno)';

    protected static ?string $navigationLabel = 'Acceso cajeros (turno)';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 42;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Key;

    protected static ?string $slug = 'acceso-cajeros-turno';

    protected string $view = 'filament.pages.manage-cashier-shift-access';

    /**
     * @var list<array{
     *     user_id: int,
     *     name: string,
     *     email: string,
     *     branch_name: string,
     *     box_is_open: bool,
     *     is_locked: bool,
     *     locked_until: string,
     *     last_closed_at: string
     * }>
     */
    public array $cashiers = [];

    public string $dailyUnlockTimeLabel = '06:00';

    public function mount(): void
    {
        $this->dailyUnlockTimeLabel = CashierShiftLock::dailyUnlockTimeLabel();
        $this->syncCashiers();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Acceso de cajeros (turno)';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Habilite el ingreso de un cajero antes del desbloqueo automático diario a las '
            .$this->dailyUnlockTimeLabel
            .' (tras cierre de caja física).';
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessFarmaadminMenuKey('cashier_shift_access');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    public function refreshCashiers(): void
    {
        $this->syncCashiers();
    }

    public function grantCashierAccess(int $userId): void
    {
        $administrator = Auth::user();
        if (! $administrator instanceof User || ! $administrator->canAccessFarmaadminMenuKey('cashier_shift_access')) {
            abort(403);
        }

        $cashier = User::query()
            ->whereKey($userId)
            ->whereJsonContains('roles', 'CAJERO')
            ->first();

        if (! $cashier instanceof User) {
            Notification::make()
                ->title('Cajero no encontrado')
                ->body('No se encontró un usuario cajero con ese identificador.')
                ->warning()
                ->send();

            return;
        }

        if (! CashierShiftLock::isLocked($cashier)) {
            Notification::make()
                ->title('Sin bloqueo activo')
                ->body($cashier->name.' ya puede ingresar al sistema.')
                ->info()
                ->send();

            return;
        }

        CashierShiftLock::grantManualAccess($cashier, $administrator);

        $this->syncCashiers();

        Notification::make()
            ->title('Acceso habilitado')
            ->body($cashier->name.' puede ingresar al sistema de inmediato.')
            ->success()
            ->send();
    }

    private function syncCashiers(): void
    {
        $timezone = (string) config('app.timezone', 'UTC');

        $this->cashiers = User::query()
            ->whereJsonContains('roles', 'CAJERO')
            ->with([
                'branch:id,name',
                'physicalCashBox:id,user_id,is_open,closed_at',
            ])
            ->orderBy('name')
            ->get()
            ->map(function (User $cashier) use ($timezone): array {
                $box = $cashier->physicalCashBox;
                $lockedUntil = $cashier->cashier_shift_locked_until;

                return [
                    'user_id' => (int) $cashier->id,
                    'name' => (string) $cashier->name,
                    'email' => (string) $cashier->email,
                    'branch_name' => (string) ($cashier->branch?->name ?? 'Sin sucursal'),
                    'box_is_open' => (bool) ($box?->is_open ?? false),
                    'is_locked' => CashierShiftLock::isLocked($cashier),
                    'locked_until' => $lockedUntil !== null
                        ? $lockedUntil->timezone($timezone)->format('d/m/Y H:i')
                        : '—',
                    'last_closed_at' => $box?->closed_at !== null
                        ? $box->closed_at->timezone($timezone)->format('d/m/Y H:i')
                        : '—',
                ];
            })
            ->values()
            ->all();
    }
}
