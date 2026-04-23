<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Enums\PurchaseEntryCurrency;
use App\Filament\Resources\Purchases\Actions\QuickCreatePurchaseProductAction;
use App\Filament\Resources\Purchases\Actions\QuickCreateSupplierAction;
use App\Filament\Resources\Purchases\Pages\Concerns\InteractsWithPurchaseLines;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Purchases\Schemas\PurchaseForm;
use App\Models\Product;
use App\Models\Purchase;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\AccountsPayableFromPurchaseSynchronizer;
use App\Services\Finance\PurchaseHistoryFromPurchaseSynchronizer;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Purchases\PurchaseCreateSummaryPresenter;
use App\Support\Purchases\PurchaseDeclaredInvoiceTotalTolerance;
use App\Support\Purchases\PurchaseDocumentTotals;
use App\Support\Purchases\PurchasePaymentStatus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class CreatePurchase extends CreateRecord
{
    use InteractsWithPurchaseLines;

    protected static string $resource = PurchaseResource::class;

    protected static bool $canCreateAnother = false;

    protected bool $pendingCreateAnother = false;

    /**
     * RIF/NIT normalizado para precargar la modal «Nuevo proveedor» al pulsar Intro sin coincidencias en el select.
     */
    public string $supplierRifPrefillForQuickCreate = '';

    /**
     * true: la modal se abrió desde Intro en el buscador del proveedor (no limpiar el prefill en before()).
     */
    public bool $supplierQuickCreateOpenedFromSelectSearch = false;

    public function openQuickCreateSupplierModalFromSelectSearch(string $search = ''): void
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', trim($search)));
        $this->supplierRifPrefillForQuickCreate = $normalized;
        $this->supplierQuickCreateOpenedFromSelectSearch = true;
        $this->mountAction('quickCreateSupplier');
    }

    public function create(bool $another = false): void
    {
        $this->pendingCreateAnother = $another;

        $this->form->validate();

        if (($this->data['entry_currency'] ?? PurchaseEntryCurrency::USD->value) === PurchaseEntryCurrency::VES->value) {
            $rate = app(VenezuelaOfficialUsdVesRateClient::class)
                ->rateForDate($this->data['supplier_invoice_date'] ?? null);
            if ($rate === null || $rate <= 0) {
                Notification::make()
                    ->title('Sin tasa oficial Bs/USD')
                    ->body('No se encontró el promedio oficial para la fecha de la factura. Corrija la fecha o intente más tarde.')
                    ->danger()
                    ->send();

                return;
            }
        }

        AuditLogger::record(
            event: 'purchase_create_summary_opened',
            description: 'Compras: formulario validado; se muestra resumen previo al guardado.',
            properties: $this->purchaseCreateAuditSnapshot(),
        );

        $this->mountAction('confirmPurchaseSave');
    }

    /**
     * Estado para el resumen y auditoría leído del almacén Livewire del formulario (`$this->data`).
     *
     * Las líneas se cargan en memoria ahí mismo (p. ej. {@see InteractsWithPurchaseLines::appendPurchaseLineForProduct}).
     * Tanto {@see Schema::getState()} como {@see Schema::getStateSnapshot()}
     * pasan por `dehydrateState()`, que con un Repeater `->relationship()` en **alta** suele dejar `items` vacío
     * al no haber registros relacionados persistidos aún.
     *
     * @return array<string, mixed>
     */
    private function purchaseSummaryFormState(): array
    {
        $state = is_array($this->data) ? $this->data : [];

        $items = $state['items'] ?? [];
        if (is_array($items) && $items !== []) {
            $docDisc = (float) ($state['document_discount_percent'] ?? 0);
            $state = array_merge(
                $state,
                PurchaseDocumentTotals::documentHeaderWithDocumentDiscount($items, $docDisc),
            );
        }

        return $state;
    }

    protected function confirmPurchaseSaveAction(): Action
    {
        return Action::make('confirmPurchaseSave')
            ->modalHeading('Resumen de la compra')
            ->modalDescription('Revise los productos cargados y los importes. Si necesita corregir, pulse Cerrar y continúe editando el formulario.')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalContent(fn (): HtmlString => new HtmlString(view('filament.purchases.purchase-create-summary', [
                'summary' => PurchaseCreateSummaryPresenter::fromFormState($this->purchaseSummaryFormState()),
            ])->render()))
            ->modalContentFooter(fn (): HtmlString => new HtmlString(view('filament.purchases.purchase-create-summary-footer', [
                'footer' => PurchaseCreateSummaryPresenter::footerTotalsPayload($this->purchaseSummaryFormState()),
            ])->render()))
            ->modalSubmitAction(
                fn (Action $action): Action => $action->disabled(fn (): bool => ! $this->purchaseDeclaredTotalMatchesCalculated()),
            )
            ->modalSubmitActionLabel('Confirmar carga y Guardar')
            ->modalCancelActionLabel('Cerrar')
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->label('Cerrar')
                    ->action(function (): void {
                        AuditLogger::record(
                            event: 'purchase_create_summary_closed',
                            description: 'Compras: el usuario cerró el resumen sin persistir la compra.',
                            properties: $this->purchaseCreateAuditSnapshot(),
                        );
                    })
                    ->close(),
            )
            ->action(function (): void {
                AuditLogger::record(
                    event: 'purchase_create_save_confirmed',
                    description: 'Compras: el usuario confirmó el resumen y solicitó guardar la compra.',
                    properties: $this->purchaseCreateAuditSnapshot(),
                );

                $this->persistPurchaseRecordAfterSummary();
            });
    }

    private function purchaseDeclaredTotalMatchesCalculated(): bool
    {
        $state = $this->purchaseSummaryFormState();

        return PurchaseDeclaredInvoiceTotalTolerance::matches(
            (float) ($state['declared_invoice_total'] ?? 0),
            (float) ($state['total'] ?? 0),
        );
    }

    protected function persistPurchaseRecordAfterSummary(): void
    {
        if (! $this->purchaseDeclaredTotalMatchesCalculated()) {
            return;
        }

        parent::create($this->pendingCreateAnother);
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseCreateAuditSnapshot(): array
    {
        $state = $this->purchaseSummaryFormState();

        $items = $state['items'] ?? [];
        $lineSummaries = [];
        if (is_array($items)) {
            foreach ($items as $idx => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $lineSummaries[] = [
                    'line_index' => $idx,
                    'product_id' => $row['product_id'] ?? null,
                    'qty' => $row['quantity_ordered'] ?? null,
                    'unit_cost' => $row['unit_cost'] ?? null,
                    'line_subtotal' => $row['line_subtotal'] ?? null,
                    'line_total' => $row['line_total'] ?? null,
                    'line_vat_percent' => $row['line_vat_percent'] ?? null,
                    'tax_amount' => $row['tax_amount'] ?? null,
                    'lot_expiration_month_year' => $row['lot_expiration_month_year'] ?? null,
                ];
            }
        }

        return [
            'context' => 'filament.purchases.create',
            'intended_create_another' => $this->pendingCreateAnother,
            'supplier_id' => $state['supplier_id'] ?? null,
            'supplier_display' => PurchaseForm::supplierDisplayNameForSupplierId($state['supplier_id'] ?? null),
            'branch_id' => $state['branch_id'] ?? null,
            'supplier_invoice_number' => $state['supplier_invoice_number'] ?? null,
            'supplier_control_number' => $state['supplier_control_number'] ?? null,
            'supplier_invoice_date' => $state['supplier_invoice_date'] ?? null,
            'payment_due_date' => $state['payment_due_date'] ?? null,
            'registered_in_system_date' => $state['registered_in_system_date'] ?? null,
            'payment_status' => $state['payment_status'] ?? null,
            'payment_status_label' => PurchasePaymentStatus::label(isset($state['payment_status']) ? (string) $state['payment_status'] : null),
            'document_discount_percent' => $state['document_discount_percent'] ?? null,
            'entry_currency' => $state['entry_currency'] ?? null,
            'declared_invoice_total' => $state['declared_invoice_total'] ?? null,
            'subtotal' => $state['subtotal'] ?? null,
            'tax_total' => $state['tax_total'] ?? null,
            'total' => $state['total'] ?? null,
            'lines_count' => is_countable($items) ? count($items) : 0,
            'lines_detail' => $lineSummaries,
        ];
    }

    protected function afterCreate(): void
    {
        $this->record->refresh();
        $this->record->load('items');
        $this->record->syncDocumentHeaderTotalsFromItemsQuietly();
        $this->record->syncProductLotsFromItems();

        try {
            app(AccountsPayableFromPurchaseSynchronizer::class)->syncFromPurchase($this->record);
        } catch (\Throwable $e) {
            report($e);
            AuditLogger::record(
                event: 'accounts_payable_sync_from_purchase_failed',
                description: 'Cuentas por pagar: error al generar registro desde la compra (la compra sí quedó guardada).',
                auditableType: Purchase::class,
                auditableId: (string) $this->record->getKey(),
                auditableLabel: $this->record->purchase_number,
                properties: [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ],
            );
        }

        try {
            app(PurchaseHistoryFromPurchaseSynchronizer::class)->syncFromPurchase($this->record);
        } catch (\Throwable $e) {
            report($e);
            AuditLogger::record(
                event: 'purchase_history_sync_from_purchase_failed',
                description: 'Histórico de compras: error al generar registro desde la compra (la compra sí quedó guardada).',
                auditableType: Purchase::class,
                auditableId: (string) $this->record->getKey(),
                auditableLabel: $this->record->purchase_number,
                properties: [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ],
            );
        }
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            QuickCreateSupplierAction::make(function (int $supplierId): void {
                $this->data['supplier_id'] = (string) $supplierId;
                $this->data['supplier_display_name'] = PurchaseForm::supplierDisplayNameForSupplierId($supplierId);
                $this->supplierRifPrefillForQuickCreate = '';
            })
                ->before(function (): void {
                    if (! $this->supplierQuickCreateOpenedFromSelectSearch) {
                        $this->supplierRifPrefillForQuickCreate = '';
                    }
                    $this->supplierQuickCreateOpenedFromSelectSearch = false;
                })
                ->fillForm(fn (): array => [
                    'tax_id' => $this->supplierRifPrefillForQuickCreate,
                    'legal_name' => '',
                    'trade_name' => '',
                    'mobile_phone' => '',
                ]),
            QuickCreatePurchaseProductAction::make(function (Product $product, ?float $unitCostFromModal = null): void {
                $this->appendPurchaseLineForProduct($product, $unitCostFromModal);
                $this->data['purchase_line_product_search'] = '';
            }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user()?->email
            ?? auth()->user()?->name
            ?? 'sistema';

        $data['created_by'] = $actor;
        $data['updated_by'] = $actor;

        if (blank($data['supplier_invoice_date'] ?? null)) {
            $data['supplier_invoice_date'] = now()->toDateString();
        }
        if (blank($data['registered_in_system_date'] ?? null)) {
            $data['registered_in_system_date'] = now()->toDateString();
        }
        if (blank($data['payment_due_date'] ?? null)) {
            $invoiceDate = (string) ($data['supplier_invoice_date'] ?? now()->toDateString());
            $data['payment_due_date'] = Carbon::parse($invoiceDate)->addDays(30)->toDateString();
        }

        if (($data['entry_currency'] ?? PurchaseEntryCurrency::USD->value) === PurchaseEntryCurrency::VES->value) {
            $rate = app(VenezuelaOfficialUsdVesRateClient::class)
                ->rateForDate($data['supplier_invoice_date'] ?? null);
            if ($rate === null || $rate <= 0) {
                throw ValidationException::withMessages([
                    'supplier_invoice_date' => 'No se pudo obtener la tasa oficial Bs/USD (promedio) para la fecha de la factura.',
                ]);
            }
            $data['official_usd_ves_rate'] = round($rate, 2);
        } else {
            $data['official_usd_ves_rate'] = null;
        }

        if (isset($data['supplier_invoice_number'])) {
            $data['supplier_invoice_number'] = trim((string) $data['supplier_invoice_number']);
        }
        if (array_key_exists('supplier_control_number', $data)) {
            $control = trim((string) ($data['supplier_control_number'] ?? ''));
            $data['supplier_control_number'] = $control === '' ? null : $control;
        }

        $items = $data['items'] ?? [];
        $docDisc = (float) ($data['document_discount_percent'] ?? 0);
        $header = PurchaseDocumentTotals::documentHeaderWithDocumentDiscount(is_array($items) ? $items : [], $docDisc);
        $data = array_merge($data, $header);

        return collect($data)->except(['items', 'supplier_display_name', 'entry_currency_selection'])->all();
    }
}
