<?php

namespace App\Filament\Resources\Branches\Pages;

use App\Filament\Resources\Branches\BranchResource;
use App\Models\Branch;
use App\Models\BranchCategoryProfitMargin;
use App\Models\ProductCategory;
use App\Services\Pricing\BranchCategoryProfitMarginProvisioner;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ManageBranchProfitMargins extends Page
{
    use InteractsWithRecord;

    protected static string $resource = BranchResource::class;

    protected static ?string $title = 'Márgenes por categoría';

    protected static ?string $navigationLabel = 'Márgenes por categoría';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.resources.branches.pages.manage-branch-profit-margins';

    /**
     * @var list<array{product_category_id: int, category_name: string, default_profit_percentage: float, profit_percentage: float|int|string, is_active: bool}>
     */
    public array $margins = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        app(BranchCategoryProfitMarginProvisioner::class)->provisionForBranch($this->getRecord());

        $this->loadMargins();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Márgenes por categoría';
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Branch $branch */
        $branch = $this->getRecord();

        return 'Configure el porcentaje de ganancia por categoría para '.$branch->name.'. Los precios de inventario se recalculan al guardar.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copyFromBranch')
                ->label('Copiar de otra sucursal')
                ->icon(Heroicon::DocumentDuplicate)
                ->color('gray')
                ->modalHeading('Copiar márgenes de otra sucursal')
                ->modalDescription('Reemplazará todos los márgenes de esta sucursal con los de la sucursal seleccionada y recalculará los precios de inventario.')
                ->modalSubmitActionLabel('Copiar y aplicar')
                ->requiresConfirmation()
                ->form([
                    Select::make('source_branch_id')
                        ->label('Sucursal origen')
                        ->options(fn (): array => $this->otherBranchOptions())
                        ->required()
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->prefixIcon(Heroicon::BuildingStorefront)
                        ->helperText('Se copiarán los porcentajes de todas las categorías configuradas en la sucursal origen.'),
                ])
                ->action(function (array $data): void {
                    $sourceBranchId = (int) ($data['source_branch_id'] ?? 0);

                    if ($sourceBranchId <= 0) {
                        throw ValidationException::withMessages([
                            'source_branch_id' => 'Seleccione una sucursal origen válida.',
                        ]);
                    }

                    /** @var Branch $targetBranch */
                    $targetBranch = $this->getRecord();

                    $sourceBranch = Branch::query()->whereKey($sourceBranchId)->first();

                    if (! $sourceBranch instanceof Branch) {
                        throw ValidationException::withMessages([
                            'source_branch_id' => 'La sucursal origen no existe.',
                        ]);
                    }

                    app(BranchCategoryProfitMarginProvisioner::class)->copyMarginsFromBranch(
                        $targetBranch,
                        $sourceBranch,
                        $this->actorLabel(),
                    );

                    $this->loadMargins();

                    Notification::make()
                        ->title('Márgenes copiados')
                        ->body('Se aplicaron los márgenes de '.$sourceBranch->name.' y se recalcularon los inventarios de '.$targetBranch->name.'.')
                        ->success()
                        ->send();
                }),
            Action::make('bulkAdjust')
                ->label('Ajuste masivo')
                ->icon(Heroicon::AdjustmentsHorizontal)
                ->color('warning')
                ->modalHeading('Ajuste masivo de márgenes')
                ->modalDescription('Suma o resta puntos porcentuales a todas las categorías mostradas en la tabla. Los valores no se guardan hasta pulsar «Guardar márgenes».')
                ->modalSubmitActionLabel('Aplicar en pantalla')
                ->form([
                    TextInput::make('adjustment')
                        ->label('Ajuste (puntos porcentuales)')
                        ->numeric()
                        ->required()
                        ->step(0.0001)
                        ->helperText('Use valores positivos para aumentar (ej. 5) o negativos para reducir (ej. -3). El resultado nunca baja de 0 %.')
                        ->prefixIcon(Heroicon::PlusCircle),
                ])
                ->action(function (array $data): void {
                    $adjustment = (float) ($data['adjustment'] ?? 0);

                    if ($adjustment === 0.0) {
                        Notification::make()
                            ->title('Sin cambios')
                            ->body('El ajuste indicado es 0 %; no se modificó ningún margen.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $this->applyBulkAdjustment($adjustment);

                    $sign = $adjustment > 0 ? '+' : '';

                    Notification::make()
                        ->title('Ajuste aplicado en pantalla')
                        ->body('Se aplicó '.$sign.number_format($adjustment, 4, '.', ',').' % a todas las categorías. Revise la tabla y guarde para persistir.')
                        ->info()
                        ->send();
                }),
            Action::make('back')
                ->label('Volver a sucursal')
                ->icon(Heroicon::ArrowLeft)
                ->url(fn (): string => BranchResource::getUrl('view', ['record' => $this->getRecord()])),
        ];
    }

    public function save(): void
    {
        $this->validate([
            'margins' => ['required', 'array', 'min:1'],
            'margins.*.product_category_id' => ['required', 'integer', 'min:1'],
            'margins.*.profit_percentage' => ['required', 'numeric', 'min:0', 'max:9999.9999'],
        ]);

        /** @var Branch $branch */
        $branch = $this->getRecord();

        app(BranchCategoryProfitMarginProvisioner::class)->syncMarginsForBranch(
            $branch,
            collect($this->margins)
                ->map(static fn (array $row): array => [
                    'product_category_id' => (int) ($row['product_category_id'] ?? 0),
                    'profit_percentage' => (float) ($row['profit_percentage'] ?? 0),
                ])
                ->all(),
            $this->actorLabel(),
        );

        $this->loadMargins();

        Notification::make()
            ->title('Márgenes actualizados')
            ->body('Los precios de inventario de esta sucursal se recalcularon con los nuevos porcentajes.')
            ->success()
            ->send();
    }

    public function applyBulkAdjustment(float $adjustment): void
    {
        foreach ($this->margins as $index => $margin) {
            $current = max(0.0, (float) ($margin['profit_percentage'] ?? 0));
            $adjusted = max(0.0, min(9999.9999, round($current + $adjustment, 4)));

            $this->margins[$index]['profit_percentage'] = $adjusted;
        }
    }

    /**
     * @return array<int, string>
     */
    private function otherBranchOptions(): array
    {
        /** @var Branch $currentBranch */
        $currentBranch = $this->getRecord();

        return Branch::query()
            ->whereKeyNot($currentBranch->id)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function actorLabel(): string
    {
        $user = Auth::user();

        return $user?->email
            ?? $user?->name
            ?? 'sistema';
    }

    private function loadMargins(): void
    {
        /** @var Branch $branch */
        $branch = $this->getRecord();

        $defaults = ProductCategory::query()
            ->orderBy('name')
            ->get(['id', 'name', 'profit_percentage', 'is_active']);

        $configured = BranchCategoryProfitMargin::query()
            ->where('branch_id', $branch->id)
            ->get(['product_category_id', 'profit_percentage'])
            ->keyBy('product_category_id');

        $this->margins = $defaults
            ->map(function (ProductCategory $category) use ($configured): array {
                $margin = $configured->get($category->id);

                return [
                    'product_category_id' => (int) $category->id,
                    'category_name' => (string) $category->name,
                    'default_profit_percentage' => max(0.0, (float) ($category->profit_percentage ?? 0)),
                    'profit_percentage' => max(0.0, (float) ($margin?->profit_percentage ?? $category->profit_percentage ?? 0)),
                    'is_active' => (bool) $category->is_active,
                ];
            })
            ->values()
            ->all();
    }
}
