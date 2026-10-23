<?php

namespace App\Filament\Clusters\Reporting\Pages;

use App\Enums\FinancialFixedExpenseCategory;
use App\Enums\PettyCashCurrency;
use App\Filament\Clusters\Reporting\ReportingCluster;
use App\Models\Branch;
use App\Models\User;
use App\Services\Finance\FinancialFixedExpenseRegistrar;
use App\Services\Reports\FinancialReportBuilder;
use App\Services\Reports\FinancialReportDeliverer;
use App\Support\Filament\BranchAuthScope;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\URL;

class FinancialReportPage extends Page
{
    protected static ?string $cluster = ReportingCluster::class;

    protected static ?string $navigationLabel = 'Reporte financiero';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected static ?string $slug = 'reporte-financiero';

    protected string $view = 'filament.clusters.reporting.financial-report';

    public ?string $dateFrom = null;

    public ?string $dateUntil = null;

    public $branchId = null;

    /** @var array<string, mixed>|null */
    public ?array $report = null;

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateUntil = now()->toDateString();
        $this->refreshReport();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Reporte financiero';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Ingresos, egresos y servicios fijos consolidados según el rango seleccionado.';
    }

    public static function canAccess(): bool
    {
        return ReportingCluster::canAccess();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('registerFixedExpense')
                ->label('Registrar gasto fijo')
                ->icon(Heroicon::PlusCircle)
                ->color('primary')
                ->modalHeading('Registrar pago de servicio fijo')
                ->modalDescription('Alquiler, servicios, seguros u otros gastos fijos recurrentes.')
                ->modalSubmitActionLabel('Guardar')
                ->modalWidth(Width::Medium)
                ->extraModalWindowAttributes([
                    'class' => 'farmadoc-financial-fixed-expense-modal fi-fixed-positioning-context',
                ])
                ->schema([
                    Grid::make(1)
                        ->schema([
                            Select::make('branch_id')
                                ->label('Sucursal')
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->nullable()
                                ->placeholder('Todas / corporativo')
                                ->helperText('Opcional. Deje vacío si aplica a toda la empresa.')
                                ->options(fn (): array => BranchAuthScope::applyToBranchFormSelect(
                                    Branch::query()->where('is_active', true)->orderBy('name'),
                                )->pluck('name', 'id')->toArray()),
                            Select::make('category')
                                ->label('Categoría')
                                ->options(FinancialFixedExpenseCategory::options())
                                ->required()
                                ->native(false)
                                ->searchable(),
                            TextInput::make('description')
                                ->label('Descripción')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Ej.: Alquiler local septiembre'),
                            DateTimePicker::make('paid_at')
                                ->label('Fecha de pago')
                                ->default(now())
                                ->required()
                                ->native(false)
                                ->displayFormat('d/m/Y H:i'),
                            Select::make('currency')
                                ->label('Moneda del pago')
                                ->options(PettyCashCurrency::options())
                                ->default(PettyCashCurrency::Usd->value)
                                ->required()
                                ->native(false),
                            TextInput::make('amount')
                                ->label('Monto')
                                ->numeric()
                                ->required()
                                ->minValue(0.01)
                                ->step(0.01),
                            FileUpload::make('receipt_path')
                                ->label('Comprobante')
                                ->directory('financial-fixed-expenses')
                                ->visibility('private')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                                ->maxSize(5120)
                                ->helperText('Opcional. JPG, PNG, WebP o PDF (máx. 5 MB).')
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(function (array $data): void {
                    $receipt = $data['receipt_path'] ?? null;
                    $receiptPath = is_array($receipt) ? ($receipt[0] ?? null) : $receipt;

                    app(FinancialFixedExpenseRegistrar::class)->register(
                        $this->resolveUser(),
                        [
                            'branch_id' => $data['branch_id'] ?? null,
                            'paid_at' => $data['paid_at'],
                            'category' => $data['category'],
                            'description' => $data['description'],
                            'currency' => $data['currency'],
                            'amount' => $data['amount'],
                            'receipt_path' => $receiptPath,
                        ],
                    );

                    Notification::make()
                        ->title('Gasto fijo registrado')
                        ->body('El pago se incluirá en el reporte si cae dentro del rango de fechas.')
                        ->success()
                        ->send();

                    $this->refreshReport();
                }),
            Action::make('downloadReport')
                ->label('Descargar reporte')
                ->icon(Heroicon::ArrowDownTray)
                ->color('gray')
                ->action(function (): void {
                    $user = $this->resolveUser();
                    $report = $this->report ?? [];

                    if (! is_array($report) || $report === []) {
                        Notification::make()
                            ->title('Sin datos')
                            ->body('Seleccione un rango de fechas válido antes de descargar.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $result = app(FinancialReportDeliverer::class)->deliver($user, $report);

                    if ($result['whatsapp']) {
                        Notification::make()
                            ->title('Reporte enviado')
                            ->body('Se descargará el Excel y se envió el detalle a su WhatsApp.')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Descargando Excel')
                            ->body($result['whatsapp_error'] ?? 'No se pudo enviar por WhatsApp.')
                            ->warning()
                            ->send();
                    }

                    $downloadParams = array_filter([
                        'from' => $this->dateFrom,
                        'until' => $this->dateUntil,
                        'branch_id' => filled($this->branchId) ? (int) $this->branchId : null,
                        'token' => $result['token'],
                    ]);

                    $this->redirect(URL::signedRoute('reports.financial.download', $downloadParams), navigate: false);
                })
                ->visible(fn (): bool => is_array($this->report) && $this->report !== []),
        ];
    }

    public function refreshReport(): void
    {
        $user = $this->resolveUser();
        $from = $this->dateFrom ?? now()->startOfMonth()->toDateString();
        $until = $this->dateUntil ?? now()->toDateString();

        if ($from > $until) {
            [$from, $until] = [$until, $from];
            $this->dateFrom = $from;
            $this->dateUntil = $until;
        }

        $branchId = filled($this->branchId) ? (int) $this->branchId : null;

        $this->report = app(FinancialReportBuilder::class)->build(
            $user,
            $from,
            $until,
            $branchId,
        );
    }

    /**
     * @return array<int, string>
     */
    public function branchOptions(): array
    {
        return BranchAuthScope::applyToBranchFormSelect(
            Branch::query()->where('is_active', true)->orderBy('name'),
        )->pluck('name', 'id')->toArray();
    }

    public function updatedBranchId(): void
    {
        $this->refreshReport();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'report' => $this->report,
            'datePresets' => $this->datePresets(),
            'branchOptions' => $this->branchOptions(),
        ];
    }

    /**
     * @return list<array{id: string, label: string, desde: string, hasta: string}>
     */
    public function datePresets(): array
    {
        $today = now();
        $monthStart = $today->copy()->startOfMonth();
        $prevMonthStart = $monthStart->copy()->subMonth();
        $prevMonthEnd = $monthStart->copy()->subDay();

        return [
            [
                'id' => '7d',
                'label' => '7 días',
                'desde' => $today->copy()->subDays(6)->toDateString(),
                'hasta' => $today->toDateString(),
            ],
            [
                'id' => '30d',
                'label' => '30 días',
                'desde' => $today->copy()->subDays(29)->toDateString(),
                'hasta' => $today->toDateString(),
            ],
            [
                'id' => 'month',
                'label' => 'Mes actual',
                'desde' => $monthStart->toDateString(),
                'hasta' => $today->toDateString(),
            ],
            [
                'id' => 'prev_month',
                'label' => 'Mes anterior',
                'desde' => $prevMonthStart->toDateString(),
                'hasta' => $prevMonthEnd->toDateString(),
            ],
        ];
    }

    public function applyPreset(string $desde, string $hasta): void
    {
        $this->dateFrom = $desde;
        $this->dateUntil = $hasta;
        $this->refreshReport();
    }

    private function resolveUser(): User
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
