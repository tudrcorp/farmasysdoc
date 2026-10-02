<?php

namespace App\Filament\Resources\FiscalPrinters\RelationManagers;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Filament\Resources\FiscalDocuments\FiscalDocumentResource;
use App\Filament\Resources\FiscalPrinters\Pages\ViewFiscalPrinter;
use App\Models\FiscalDocument;
use App\Models\FiscalPrinter;
use App\Models\User;
use App\Services\Fiscal\FiscalTestLab;
use App\Support\Fiscal\FiscalPaymentCodes;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Laboratorio fiscal: pruebas sobre esta máquina sin crear ventas ni mover inventario, caja o cuentas por cobrar.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $relatedResource = FiscalDocumentResource::class;

    protected static ?string $title = 'Laboratorio fiscal';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $pageClass === ViewFiscalPrinter::class;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_test', true))
            ->description(new HtmlString(
                'Pruebas sin ventas, sin inventario y sin caja. Niveles 1, 2 y 4 usan el puerto: <strong>cierre el sistema actual (Valery) mientras se ejecutan</strong>. '
                .'Requiere el agente 1.2 o superior.'
            ))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s'),
                TextColumn::make('type')
                    ->label('Prueba')
                    ->formatStateUsing(fn (mixed $state, FiscalDocument $record): string => match (true) {
                        $record->type === FiscalDocumentType::StatusRead => '1 · Lectura de estado',
                        $record->type === FiscalDocumentType::NonFiscalTicket => '2 · Ticket no fiscal',
                        $record->simulation => '3 · Simulación de factura',
                        $record->type === FiscalDocumentType::CreditNote => '4 · Nota de crédito automática',
                        default => '4 · Factura real de prueba',
                    }),
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (mixed $state): string => FiscalDocumentStatus::tryLabel($state))
                    ->badge()
                    ->color(fn (FiscalDocument $record): string => $record->status->color()),
                TextColumn::make('fiscal_number')
                    ->label('Nº fiscal')
                    ->placeholder('—'),
                TextColumn::make('error_message')
                    ->label('Detalle')
                    ->limit(70)
                    ->tooltip(fn (FiscalDocument $record): ?string => $record->error_message)
                    ->placeholder('—'),
                TextColumn::make('requested_by')
                    ->label('Por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->poll('3s')
            ->paginated([10, 25])
            ->emptyStateHeading('Sin pruebas todavía')
            ->emptyStateDescription('Empiece por «1 · Leer estado» para confirmar que el agente ve esta máquina.')
            ->headerActions([
                ActionGroup::make([
                    $this->statusReadAction(),
                    $this->nonFiscalTicketAction(),
                    $this->simulationAction(),
                    $this->testInvoiceAction(),
                ])
                    ->label('Ejecutar prueba')
                    ->icon(Heroicon::Beaker)
                    ->button()
                    ->color('primary'),
            ])
            ->recordActions([
                Action::make('viewResult')
                    ->label('Ver resultado')
                    ->icon(Heroicon::Eye)
                    ->url(fn (FiscalDocument $record): string => FiscalDocumentResource::getUrl('view', ['record' => $record])),
            ]);
    }

    private function statusReadAction(): Action
    {
        return Action::make('labStatusRead')
            ->label('1 · Leer estado (no imprime)')
            ->icon(Heroicon::Signal)
            ->requiresConfirmation()
            ->modalHeading('Leer estado de la máquina')
            ->modalDescription('El agente lee contadores, alícuotas, IGTF y reloj. No imprime nada ni afecta la memoria fiscal. Cierre Valery un momento.')
            ->action(function (): void {
                $this->runLab(fn (FiscalTestLab $lab, FiscalPrinter $printer, string $actor) => $lab->requestStatusRead($printer, $actor));
            });
    }

    private function nonFiscalTicketAction(): Action
    {
        return Action::make('labNonFiscalTicket')
            ->label('2 · Imprimir ticket no fiscal')
            ->icon(Heroicon::DocumentText)
            ->visible(fn (): bool => $this->isAdministrator())
            ->modalHeading('Ticket no fiscal de prueba')
            ->modalDescription('Imprime un ticket «PRUEBA – NO FISCAL». No queda en la memoria fiscal (solo suma al contador de documentos no fiscales). Cierre Valery un momento.')
            ->schema([
                Textarea::make('text')
                    ->label('Texto adicional (opcional)')
                    ->rows(3)
                    ->maxLength(400)
                    ->helperText('Una línea por renglón; máximo 40 caracteres por línea.'),
            ])
            ->action(function (array $data): void {
                $lines = preg_split('/\r\n|\r|\n/', (string) ($data['text'] ?? '')) ?: [];
                $this->runLab(fn (FiscalTestLab $lab, FiscalPrinter $printer, string $actor) => $lab->requestNonFiscalTicket($printer, $lines, $actor));
            });
    }

    private function simulationAction(): Action
    {
        return Action::make('labSimulation')
            ->label('3 · Simular factura (no imprime)')
            ->icon(Heroicon::CommandLine)
            ->modalHeading('Simular factura con una venta ficticia')
            ->modalDescription('El agente arma los comandos que enviaría, con los medios de pago de esta máquina, sin imprimir ni abrir el puerto. Valery puede seguir abierto.')
            ->modalWidth('4xl')
            ->schema($this->fakeSaleSchema(maxTotalHint: false))
            ->action(function (array $data): void {
                $this->runLab(fn (FiscalTestLab $lab, FiscalPrinter $printer, string $actor) => $lab->requestSimulation($printer, $this->saleData($data), $actor));
            });
    }

    private function testInvoiceAction(): Action
    {
        return Action::make('labTestInvoice')
            ->label('4 · Factura REAL de prueba + nota de crédito')
            ->icon(Heroicon::ExclamationTriangle)
            ->color('danger')
            ->visible(fn (): bool => $this->isAdministrator())
            ->modalHeading('Factura REAL de prueba')
            ->modalDescription(fn (): HtmlString => new HtmlString(
                '<strong>Queda registrada en la memoria fiscal y en el reporte Z del día.</strong> Apenas se imprima, el sistema emite su nota de crédito por el mismo monto (neto cero). '
                .'Avise al contador antes. Total máximo: '.number_format((float) config('fiscal.test_lab.max_invoice_total_ves', 10), 2, ',', '.').' Bs. Cierre Valery mientras se imprime.'
            ))
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('Imprimir factura real de prueba')
            ->schema(fn (): array => array_merge($this->fakeSaleSchema(maxTotalHint: true), [
                Section::make('Confirmación')
                    ->schema([
                        Checkbox::make('accountant_notified')
                            ->label('El contador está al tanto de esta factura de prueba y su nota de crédito.')
                            ->accepted()
                            ->validationMessages(['accepted' => 'Confirme que avisó al contador.']),
                        TextInput::make('confirm_name')
                            ->label('Escriba el nombre de la máquina para confirmar: «'.$this->getOwnerRecord()->name.'»')
                            ->required()
                            ->rule(Rule::in([$this->getOwnerRecord()->name]))
                            ->validationMessages(['in' => 'El nombre no coincide.']),
                    ]),
            ]))
            ->action(function (array $data): void {
                $this->runLab(fn (FiscalTestLab $lab, FiscalPrinter $printer, string $actor) => $lab->requestTestInvoice($printer, $this->saleData($data), $actor));
            });
    }

    /**
     * @return array<int, mixed>
     */
    private function fakeSaleSchema(bool $maxTotalHint): array
    {
        return [
            Repeater::make('items')
                ->label('Productos ficticios (precios en Bs, sin IVA)')
                ->schema([
                    TextInput::make('description')
                        ->label('Descripción')
                        ->required()
                        ->maxLength(40)
                        ->default('PRODUCTO DE PRUEBA'),
                    TextInput::make('quantity')
                        ->label('Cantidad')
                        ->numeric()
                        ->minValue(0.001)
                        ->maxValue(999)
                        ->default(1)
                        ->required(),
                    TextInput::make('unit_price_ves')
                        ->label('Precio unitario Bs')
                        ->numeric()
                        ->minValue(0.01)
                        ->default(1)
                        ->required(),
                    Select::make('tax')
                        ->label('IVA')
                        ->options(['E' => 'Exento', 'G' => 'General (alícuota vigente)'])
                        ->default('E')
                        ->native(false)
                        ->required(),
                ])
                ->columns(4)
                ->minItems(1)
                ->maxItems(5)
                ->defaultItems(1)
                ->helperText($maxTotalHint ? 'Use montos mínimos: es una factura real.' : null),
            Grid::make(3)
                ->schema([
                    Select::make('payment_method')
                        ->label('Medio de pago')
                        ->options(FiscalPaymentCodes::labels())
                        ->default('cash_ves')
                        ->native(false)
                        ->required()
                        ->helperText(fn (): string => 'Número en esta máquina: '.$this->slotsSummary()),
                    TextInput::make('customer_document')
                        ->label('RIF / C.I. del cliente')
                        ->default(fn (): string => (string) config('fiscal.retention_agent.rif'))
                        ->maxLength(14),
                    TextInput::make('customer_name')
                        ->label('Nombre del cliente')
                        ->default(fn (): string => (string) config('fiscal.retention_agent.name'))
                        ->maxLength(40),
                ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{items: list<array{description: string, quantity: float|int|string, unit_price_ves: float|int|string, tax: string}>, payment_method: string, customer_document: ?string, customer_name: ?string}
     */
    private function saleData(array $data): array
    {
        return [
            'items' => array_values(array_map(fn (array $item): array => [
                'description' => (string) $item['description'],
                'quantity' => $item['quantity'],
                'unit_price_ves' => $item['unit_price_ves'],
                'tax' => (string) $item['tax'],
            ], (array) ($data['items'] ?? []))),
            'payment_method' => (string) $data['payment_method'],
            'customer_document' => $data['customer_document'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
        ];
    }

    private function slotsSummary(): string
    {
        $slots = $this->getOwnerRecord()->payment_slots;

        if (! is_array($slots) || $slots === []) {
            return 'sin medios de pago configurados.';
        }

        $labels = FiscalPaymentCodes::labels();

        return collect($slots)
            ->filter()
            ->map(fn (string $slot, string $code): string => ($labels[$code] ?? $code).' → '.$slot)
            ->implode(' · ');
    }

    /**
     * @param  callable(FiscalTestLab, FiscalPrinter, string): FiscalDocument  $callback
     */
    private function runLab(callable $callback): void
    {
        /** @var FiscalPrinter $printer */
        $printer = $this->getOwnerRecord();
        $user = Auth::user();

        try {
            $callback(app(FiscalTestLab::class), $printer, (string) ($user?->email ?? $user?->name ?? 'sistema'));
        } catch (ValidationException $e) {
            Notification::make()
                ->title('No se pudo encolar la prueba')
                ->body(collect($e->errors())->flatten()->implode(' '))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Prueba encolada')
            ->body($printer->isOnline()
                ? 'El agente la tomará en segundos; el resultado aparece en esta tabla.'
                : 'El agente de esta máquina no está en línea; se ejecutará cuando se conecte.')
            ->success()
            ->send();
    }

    private function isAdministrator(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdministrator();
    }
}
