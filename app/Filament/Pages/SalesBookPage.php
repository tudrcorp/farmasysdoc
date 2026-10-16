<?php

namespace App\Filament\Pages;

use App\Enums\FiscalPrinterMode;
use App\Enums\SalesBookStatus;
use App\Models\Branch;
use App\Models\FiscalPrinter;
use App\Models\FiscalZReport;
use App\Models\SalesBook;
use App\Models\User;
use App\Services\Fiscal\FiscalDocumentRegistrar;
use App\Services\Fiscal\FiscalZReportRecorder;
use App\Services\Fiscal\SalesBookBuilder;
use App\Services\Fiscal\SalesBookCloser;
use App\Services\Fiscal\SalesBookPeriodLock;
use App\Support\Fiscal\SalesBookAccess;
use App\Support\Fiscal\SalesBookPeriod;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use UnitEnum;

/**
 * Libro de Ventas por sucursal y quincena, generado desde los reportes Z de las máquinas fiscales.
 */
final class SalesBookPage extends Page
{
    protected static ?string $slug = 'libro-de-ventas';

    protected static ?string $navigationLabel = 'Libro de Ventas';

    protected static ?int $navigationSort = 13;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BookOpen;

    protected string $view = 'filament.pages.sales-book';

    public ?int $branchId = null;

    public string $periodKey = '';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $user = auth()->user();

        return $user instanceof User ? $user->navigationOperationsGroupLabel() : 'Farmadoc®';
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return SalesBookAccess::canView($user instanceof User ? $user : null);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    public function mount(): void
    {
        $this->branchId = (int) array_key_first($this->branchOptions()) ?: null;
        $this->periodKey = SalesBookPeriod::containing(now())->previous()->key();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Libro de Ventas';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Generado desde los reportes Z leídos de la memoria fiscal de cada máquina. Una vez cerrado, el libro queda congelado con su huella SHA-256.';
    }

    /**
     * @return array<int, string>
     */
    public function branchOptions(): array
    {
        return SalesBookAccess::branchOptions($this->user());
    }

    /**
     * @return array<string, string>
     */
    public function periodOptions(): array
    {
        return SalesBookPeriod::recentOptions();
    }

    public function updatedBranchId(): void
    {
        unset($this->book, $this->versions);
    }

    public function updatedPeriodKey(): void
    {
        unset($this->book, $this->versions);
    }

    #[Computed]
    public function branch(): ?Branch
    {
        if ($this->branchId === null || ! SalesBookAccess::canAccessBranch($this->user(), $this->branchId)) {
            return null;
        }

        return Branch::query()->find($this->branchId);
    }

    #[Computed]
    public function period(): ?SalesBookPeriod
    {
        try {
            return SalesBookPeriod::fromKey($this->periodKey);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    #[Computed]
    public function closedBook(): ?SalesBook
    {
        if (! $this->branch instanceof Branch || ! $this->period instanceof SalesBookPeriod) {
            return null;
        }

        return app(SalesBookPeriodLock::class)->closedBook((int) $this->branch->id, $this->period);
    }

    /**
     * Libro a mostrar: el snapshot si está cerrado (con aviso si los datos actuales difieren), o el borrador en vivo.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function book(): ?array
    {
        if (! $this->branch instanceof Branch || ! $this->period instanceof SalesBookPeriod) {
            return null;
        }

        $live = app(SalesBookBuilder::class)->build($this->branch, $this->period, SalesBookAccess::actorLabel($this->user()));
        $closed = $this->closedBook;

        if (! $closed instanceof SalesBook) {
            return $live + ['is_closed' => false];
        }

        $snapshot = $closed->snapshotData();
        $issues = [];

        if (! $closed->hasValidContentHash()) {
            $issues[] = ['level' => SalesBookBuilder::LEVEL_ERROR, 'message' => 'El libro cerrado no supera la verificación de integridad (SHA-256). Avise a sistemas.'];
        }

        if (abs((float) $live['totals']['total'] - (float) ($snapshot['totals']['total'] ?? 0)) > 0.001
            || abs((float) $live['totals']['retained'] - (float) ($snapshot['totals']['retained'] ?? 0)) > 0.001
            || $live['z_reports_count'] !== (int) ($snapshot['z_reports_count'] ?? 0)) {
            $issues[] = ['level' => SalesBookBuilder::LEVEL_WARNING, 'message' => 'Los datos actuales (reportes Z o retenciones) ya no coinciden con el libro cerrado. Si corresponde, un administrador debe reabrirlo y volver a cerrarlo.'];
        }

        return array_merge($snapshot, [
            'is_closed' => true,
            'issues' => $issues,
        ]);
    }

    /**
     * @return list<SalesBook>
     */
    #[Computed]
    public function versions(): array
    {
        if (! $this->branch instanceof Branch || ! $this->period instanceof SalesBookPeriod) {
            return [];
        }

        return SalesBook::query()
            ->where('branch_id', $this->branch->id)
            ->where('year', $this->period->year)
            ->where('month', $this->period->month)
            ->where('half', $this->period->half)
            ->orderByDesc('version')
            ->get()
            ->all();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->readZReportsAction(),
            $this->downloadAction('pdf', 'PDF', Heroicon::DocumentArrowDown),
            $this->downloadAction('xlsx', 'Excel', Heroicon::TableCells),
            $this->closeAction(),
            $this->reopenAction(),
        ];
    }

    private function downloadAction(string $format, string $label, Heroicon $icon): Action
    {
        return Action::make('download'.ucfirst($format))
            ->label($label)
            ->icon($icon)
            ->color('gray')
            ->disabled(fn (): bool => ! $this->branch instanceof Branch || ! $this->period instanceof SalesBookPeriod)
            ->action(function () use ($format): void {
                $url = URL::temporarySignedRoute('sales-books.report', now()->addMinutes(10), [
                    'branch' => $this->branch?->id,
                    'period' => $this->periodKey,
                    'format' => $format,
                ]);

                $this->js('window.open('.Js::from($url).', "_blank")');
            });
    }

    private function readZReportsAction(): Action
    {
        return Action::make('readZReports')
            ->label('Leer reportes Z')
            ->icon(Heroicon::ArrowPath)
            ->color('gray')
            ->visible(fn (): bool => SalesBookAccess::canClose($this->user()))
            ->modalHeading('Leer reportes Z de la memoria fiscal')
            ->modalDescription('El agente de la caja lee los Z indicados sin imprimir nada. En máquinas que no están en modo «Activa» el puerto lo usa el sistema actual de la caja: hágalo cuando la caja no esté facturando.')
            ->modalSubmitActionLabel('Enviar a la cola')
            ->schema([
                Select::make('fiscal_printer_id')
                    ->label('Máquina fiscal')
                    ->options(fn (): array => FiscalPrinter::query()
                        ->where('branch_id', $this->branchId ?? 0)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (FiscalPrinter $printer): array => [
                            $printer->id => $printer->name.' · '.$printer->fiscal_registry.' · '.$printer->currentMode()->label(),
                        ])
                        ->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (mixed $state, Set $set) => $set('from_z', $this->suggestedFromZ((int) $state)))
                    ->native(false),
                TextInput::make('from_z')
                    ->label('Desde el Z')
                    ->helperText('Se sugiere el siguiente al último Z guardado de la máquina.')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                TextInput::make('to_z')
                    ->label('Hasta el Z (vacío = hasta el último)')
                    ->numeric()
                    ->minValue(1),
            ])
            ->action(function (array $data): void {
                $printer = FiscalPrinter::query()
                    ->where('branch_id', $this->branchId ?? 0)
                    ->findOrFail((int) $data['fiscal_printer_id']);

                try {
                    $document = app(FiscalDocumentRegistrar::class)->requestZReading(
                        $printer,
                        (int) $data['from_z'],
                        filled($data['to_z'] ?? null) ? (int) $data['to_z'] : null,
                        SalesBookAccess::actorLabel($this->user()),
                    );
                } catch (ValidationException $exception) {
                    Notification::make()->title('No se pudo pedir la lectura')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();

                    return;
                }

                Notification::make()
                    ->title('Lectura de reportes Z en cola')
                    ->body(($printer->isOnline() ? 'El agente la procesará en unos segundos.' : 'El agente de esta máquina no está en línea; se leerá cuando se conecte.')
                        .($printer->currentMode() !== FiscalPrinterMode::Active ? ' La máquina no está en modo «Activa»: si el puerto está ocupado la lectura fallará y podrá repetirla.' : '')
                        .' Documento '.$document?->uuid)
                    ->success()
                    ->send();
            });
    }

    private function closeAction(): Action
    {
        return Action::make('closeBook')
            ->label('Cerrar libro')
            ->icon(Heroicon::LockClosed)
            ->color('danger')
            ->visible(fn (): bool => SalesBookAccess::canClose($this->user()) && ! $this->closedBook instanceof SalesBook)
            ->disabled(fn (): bool => $this->book === null || SalesBookBuilder::hasBlockingIssues($this->book))
            ->requiresConfirmation()
            ->modalHeading(fn (): string => '¿Cerrar el Libro de Ventas de la '.$this->period?->label().'?')
            ->modalDescription('El libro quedará congelado tal como se ve, con su huella SHA-256, y no se podrán registrar ni modificar retenciones de la quincena. Solo un administrador puede reabrirlo, dejando constancia del motivo.')
            ->modalSubmitActionLabel('Sí, cerrar')
            ->action(function (): void {
                if (! $this->branch instanceof Branch || ! $this->period instanceof SalesBookPeriod) {
                    return;
                }

                try {
                    $book = app(SalesBookCloser::class)->close($this->branch, $this->period, SalesBookAccess::actorLabel($this->user()));
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('El libro no se puede cerrar')
                        ->body(collect($exception->errors())->flatten()->implode("\n"))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                unset($this->closedBook, $this->book, $this->versions);

                Notification::make()
                    ->title('Libro de Ventas cerrado')
                    ->body('Versión '.$book->version.' · SHA-256 '.substr($book->content_hash, 0, 16).'…')
                    ->success()
                    ->send();
            });
    }

    private function reopenAction(): Action
    {
        return Action::make('reopenBook')
            ->label('Reabrir')
            ->icon(Heroicon::LockOpen)
            ->color('warning')
            ->visible(fn (): bool => SalesBookAccess::canReopen($this->user()) && $this->closedBook instanceof SalesBook)
            ->modalHeading('Reabrir el Libro de Ventas')
            ->modalDescription('La versión cerrada no se borra: queda en el historial como anulada. Al volver a cerrar se crea una nueva versión.')
            ->modalSubmitActionLabel('Reabrir')
            ->schema([
                Textarea::make('reason')
                    ->label('Motivo')
                    ->required()
                    ->minLength(10)
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (array $data): void {
                $book = $this->closedBook;

                if (! $book instanceof SalesBook) {
                    return;
                }

                try {
                    app(SalesBookCloser::class)->reopen($book, (string) $data['reason'], SalesBookAccess::actorLabel($this->user()));
                } catch (ValidationException $exception) {
                    Notification::make()->title('No se pudo reabrir')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();

                    return;
                }

                unset($this->closedBook, $this->book, $this->versions);

                Notification::make()->title('Libro reabierto')->warning()->send();
            });
    }

    public function downloadVersionUrl(int $bookId, string $format): string
    {
        return URL::temporarySignedRoute('sales-books.report', now()->addMinutes(10), [
            'book' => $bookId,
            'format' => $format === 'xlsx' ? 'xlsx' : 'pdf',
        ]);
    }

    public function statusLabel(SalesBook $book): string
    {
        return $book->status instanceof SalesBookStatus ? $book->status->label() : (string) $book->status;
    }

    private function suggestedFromZ(int $printerId): ?int
    {
        $printer = FiscalPrinter::query()->find($printerId);

        if (! $printer instanceof FiscalPrinter) {
            return null;
        }

        $stored = FiscalZReport::query()
            ->where('printer_serial', FiscalZReportRecorder::normalizeSerial($printer->fiscal_registry))
            ->max('z_number');

        if ($stored !== null) {
            return (int) $stored + 1;
        }

        $last = filter_var($printer->last_z_number, FILTER_VALIDATE_INT);

        return $last !== false ? max(1, $last) : null;
    }

    private function user(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }
}
