<?php

namespace App\Filament\Resources\Hr\PayrollPeriods\Pages;

use App\Enums\PayrollPeriodStatus;
use App\Filament\Resources\Hr\Employees\EmployeeResource;
use App\Filament\Resources\Hr\PayrollPeriods\PayrollPeriodResource;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Services\Hr\HrBcvRateResolver;
use App\Services\Hr\PayrollCalculator;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

class ViewPayrollPeriodDetail extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = PayrollPeriodResource::class;

    protected static ?string $title = 'Detalle de nómina';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.resources.hr.payroll-periods.pages.view-payroll-period-detail';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(
            PayrollPeriodResource::canView($this->getRecord()),
            403,
        );
    }

    public function getTitle(): string|Htmlable
    {
        /** @var PayrollPeriod $period */
        $period = $this->getRecord();

        return 'Periodo '.$period->period_number.' · '.$period->period_date->format('d/m/Y');
    }

    public function getHeading(): string|Htmlable
    {
        /** @var PayrollPeriod $period */
        $period = $this->getRecord();

        return 'Periodo '.$period->period_number;
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var PayrollPeriod $period */
        $period = $this->getRecord();

        return $period->halfLabel().' · '.$period->monthLabel().' · '.$period->period_date->format('d/m/Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver a nómina')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->url(PayrollPeriodResource::getUrl('index')),
            Action::make('calculate')
                ->label('Calcular / recalcular')
                ->icon(Heroicon::Calculator)
                ->color('primary')
                ->visible(fn (): bool => $this->getRecord() instanceof PayrollPeriod
                    && $this->getRecord()->status !== PayrollPeriodStatus::Closed)
                ->form([
                    TextInput::make('manual_rate')
                        ->label('Tasa BCV manual (opcional)')
                        ->numeric()
                        ->minValue(0.000001)
                        ->step(0.000001)
                        ->helperText(function (): string {
                            /** @var PayrollPeriod $period */
                            $period = $this->getRecord();
                            $rate = app(HrBcvRateResolver::class)->resolveForDate($period->period_date);

                            return $rate !== null
                                ? 'Tasa sugerida: '.number_format($rate, 6, ',', '.')
                                : 'No hay tasa automática; indique una tasa manual.';
                        }),
                ])
                ->modalHeading(fn (): string => 'Calcular '.$this->getRecord()->label())
                ->action(function (array $data): void {
                    try {
                        /** @var PayrollPeriod $period */
                        $period = $this->getRecord();
                        $manual = isset($data['manual_rate']) && is_numeric($data['manual_rate'])
                            ? (float) $data['manual_rate']
                            : null;
                        app(PayrollCalculator::class)->calculate($period, $manual);
                        $this->record = $period->refresh();
                        Notification::make()->title('Nómina calculada')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('No se pudo calcular')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('close')
                ->label('Cerrar periodo')
                ->icon(Heroicon::LockClosed)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Cerrar periodo de nómina')
                ->modalDescription('Al cerrar el periodo no podrá recalcularse. Confirme solo si ya validó los totales y el detalle.')
                ->visible(fn (): bool => $this->getRecord() instanceof PayrollPeriod
                    && $this->getRecord()->status === PayrollPeriodStatus::Calculated)
                ->action(function (): void {
                    try {
                        /** @var PayrollPeriod $period */
                        $period = $this->getRecord();
                        app(PayrollCalculator::class)->close($period);
                        $this->record = $period->refresh();
                        Notification::make()->title('Periodo cerrado')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('No se pudo cerrar')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    public function table(Table $table): Table
    {
        /** @var PayrollPeriod $period */
        $period = $this->getRecord();

        return $table
            ->query(
                PayrollLine::query()
                    ->where('payroll_period_id', $period->getKey())
                    ->with(['employee.branch', 'items'])
            )
            ->defaultSort('id')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->searchPlaceholder('Buscar empleado o cédula…')
            ->emptyStateHeading('Sin líneas de nómina')
            ->emptyStateDescription('Calcula el periodo para generar el detalle por empleado.')
            ->emptyStateIcon(Heroicon::Users)
            ->columns([
                TextColumn::make('employee_name')
                    ->label('Empleado')
                    ->state(fn (PayrollLine $record): string => $record->employee?->fullName() ?? '—')
                    ->description(fn (PayrollLine $record): ?string => $record->employee
                        ? 'C.I. '.$record->employee->national_id
                        : null)
                    ->weight(FontWeight::SemiBold)
                    ->icon(Heroicon::UserCircle)
                    ->iconColor('primary')
                    ->url(fn (PayrollLine $record): ?string => $record->employee_id
                        ? EmployeeResource::getUrl('view', ['record' => $record->employee_id])
                        : null)
                    ->color('primary')
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHas('employee', function ($q) use ($search): void {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('national_id', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('employee.branch.name')
                    ->label('Sucursal')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('base_salary_usd')
                    ->label('Base')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => self::usd((float) $state))
                    ->description(fn (PayrollLine $record): string => self::ves((float) $record->base_salary_ves)),

                TextColumn::make('assignments_usd')
                    ->label('Asignaciones')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => self::usd((float) $state))
                    ->description(fn (PayrollLine $record): string => self::ves((float) $record->assignments_ves))
                    ->color(fn (PayrollLine $record): string => (float) $record->assignments_usd > 0 ? 'success' : 'gray'),

                TextColumn::make('deductions_usd')
                    ->label('Deducciones')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => self::usd((float) $state))
                    ->description(fn (PayrollLine $record): string => self::ves((float) $record->deductions_ves))
                    ->color(fn (PayrollLine $record): string => (float) $record->deductions_usd > 0 ? 'warning' : 'gray'),

                TextColumn::make('loans_usd')
                    ->label('Préstamos')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => self::usd((float) $state))
                    ->description(fn (PayrollLine $record): string => self::ves((float) $record->loans_ves))
                    ->color(fn (PayrollLine $record): string => (float) $record->loans_usd > 0 ? 'danger' : 'gray'),

                TextColumn::make('net_usd')
                    ->label('Neto a pagar')
                    ->alignEnd()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(fn ($state): string => self::usd((float) $state))
                    ->description(fn (PayrollLine $record): string => self::ves((float) $record->net_ves))
                    ->color('info')
                    ->icon(Heroicon::Banknotes)
                    ->iconColor('info'),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->options(fn () => PayrollLine::query()
                        ->where('payroll_period_id', $period->getKey())
                        ->with('employee.branch')
                        ->get()
                        ->pluck('employee.branch.name', 'employee.branch_id')
                        ->filter()
                        ->unique()
                        ->sort()
                        ->all())
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;
                        if (blank($value)) {
                            return $query;
                        }

                        return $query->whereHas('employee', fn ($q) => $q->where('branch_id', $value));
                    }),
            ])
            ->recordActions([
                Action::make('items')
                    ->label('Conceptos')
                    ->icon(Heroicon::ListBullet)
                    ->color('warning')
                    ->modalHeading('Conceptos del pago')
                    ->modalWidth('lg')
                    ->modalContent(fn (PayrollLine $record) => view(
                        'filament.resources.hr.payroll-periods.partials.line-items',
                        [
                            'items' => $record->items,
                            'employeeName' => $record->employee?->fullName() ?? 'Empleado',
                        ],
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                ActionGroup::make([
                    Action::make('viewEmployee')
                        ->label('Ver empleado')
                        ->icon(Heroicon::User)
                        ->url(fn (PayrollLine $record): ?string => $record->employee_id
                            ? EmployeeResource::getUrl('view', ['record' => $record->employee_id])
                            : null)
                        ->visible(fn (PayrollLine $record): bool => filled($record->employee_id)),
                ])
                    ->icon(Heroicon::EllipsisVertical)
                    ->color('gray'),
            ]);
    }

    private static function usd(float $amount): string
    {
        return 'US$ '.number_format($amount, 2, ',', '.');
    }

    private static function ves(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }
}
