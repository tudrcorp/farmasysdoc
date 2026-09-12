<?php

namespace App\Filament\Resources\InventoryAudits\Actions;

use App\Enums\InventoryAuditStatus;
use App\Models\Branch;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use App\Support\Inventory\InventoryAuditLetterRange;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;
use Livewire\Component as LivewireComponent;

final class InventoryAuditDetailedReportAction
{
    public const NAME = 'detailedAuditReport';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('Reporte detallado PDF')
            ->icon(Heroicon::DocumentArrowDown)
            ->color('gray')
            ->visible(fn (): bool => Auth::user() instanceof User && Auth::user()->isAdministrator())
            ->modalHeading('Reporte detallado de auditorías')
            ->modalDescription('PDF con cada auditoría y todas sus líneas (existencia, costo, estado y quién procesó). Combine período, sucursal y rango de letras según lo que necesite.')
            ->modalSubmitActionLabel('Generar PDF')
            ->modalWidth(Width::Medium)
            ->form([
                Grid::make(2)
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->requiredWith('date_until')
                            ->helperText('Opcional. Fecha de inicio o cierre de la auditoría.'),
                        DatePicker::make('date_until')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->requiredWith('date_from')
                            ->helperText('Opcional. Inclusive.'),
                    ]),
                Select::make('branch_id')
                    ->label('Sucursal')
                    ->placeholder('Todas las sucursales')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->options(fn (): array => BranchAuthScope::applyToBranchFormSelect(
                        Branch::query()->where('is_active', true)->orderBy('name'),
                    )->pluck('name', 'id')->all())
                    ->helperText('Opcional. Deje vacío para incluir todas.'),
                Grid::make(2)
                    ->schema([
                        Select::make('letter_from')
                            ->label('Desde letra')
                            ->options(InventoryAuditLetterRange::options())
                            ->placeholder('Todas')
                            ->native(false)
                            ->requiredWith('letter_to')
                            ->helperText('Opcional. Primera letra del nombre del producto.'),
                        Select::make('letter_to')
                            ->label('Hasta letra')
                            ->options(InventoryAuditLetterRange::options())
                            ->placeholder('Todas')
                            ->native(false)
                            ->requiredWith('letter_from')
                            ->gte('letter_from')
                            ->helperText('Opcional. Última letra inclusive.'),
                    ]),
                Select::make('status')
                    ->label('Estado')
                    ->placeholder('Abiertas y cerradas')
                    ->native(false)
                    ->options(InventoryAuditStatus::options())
                    ->helperText('Opcional.'),
            ])
            ->action(function (array $data, LivewireComponent $livewire): void {
                $from = self::dateQueryValue($data['date_from'] ?? null);
                $until = self::dateQueryValue($data['date_until'] ?? null);
                $branchId = filled($data['branch_id'] ?? null) ? (int) $data['branch_id'] : null;
                $letterFrom = filled($data['letter_from'] ?? null) ? (string) $data['letter_from'] : null;
                $letterTo = filled($data['letter_to'] ?? null) ? (string) $data['letter_to'] : null;
                $status = filled($data['status'] ?? null) ? (string) $data['status'] : null;

                if ($from === null && $until === null && ($branchId === null || $branchId <= 0) && $letterFrom === null && $letterTo === null && $status === null) {
                    Notification::make()
                        ->title('Indique un filtro')
                        ->body('Seleccione un período, una sucursal, un rango de letras o un estado para generar el reporte.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    InventoryAuditLetterRange::resolve($letterFrom, $letterTo);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Rango de letras inválido')
                        ->body(collect($exception->errors())->flatten()->first() ?: 'Revise las letras inicial y final.')
                        ->danger()
                        ->send();

                    return;
                }

                $routeParams = array_filter([
                    'from' => $from,
                    'until' => $until,
                    'branch' => ($branchId !== null && $branchId > 0) ? $branchId : null,
                    'letter_from' => $letterFrom,
                    'letter_to' => $letterTo,
                    'status' => $status,
                ], static fn (mixed $value): bool => $value !== null && $value !== '');

                $url = URL::temporarySignedRoute(
                    'inventory-audits.detailed-report-pdf',
                    now()->addMinutes(10),
                    $routeParams,
                );

                $livewire->js('window.open('.Js::from($url).', "_blank")');

                Notification::make()
                    ->title('Descarga iniciada')
                    ->body('Se abrió una pestaña con el PDF. Si no aparece, permita ventanas emergentes para este sitio.')
                    ->success()
                    ->send();
            });
    }

    private static function dateQueryValue(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        return Carbon::parse((string) $value)->toDateString();
    }
}
