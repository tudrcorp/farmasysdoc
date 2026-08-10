<?php

namespace App\Filament\Resources\InventoryAudits\Support;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Inventory\InventoryAuditOtpService;
use App\Support\Inventory\InventoryFinancialPricingBreakdown;
use App\Support\Inventory\InventoryQuantityFormat;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\OneTimeCodeInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

final class InventoryAuditApplyUpdateForm
{
    /**
     * Campos del formulario «Aplicar actualización» (cantidad, categoría, costo, OTP y desglose).
     *
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('counted_quantity')
                ->label('Cantidad contada')
                ->numeric()
                ->required()
                ->minValue(0)
                ->step(0.001),
            Select::make('product_category_id')
                ->label('Categoría')
                ->options(fn (): array => ProductCategory::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->native(false)
                ->required()
                ->live()
                ->helperText('Al cambiar la categoría se recalculan los precios con el margen de esa categoría en esta sucursal.')
                ->prefixIcon(Heroicon::Tag),
            TextInput::make('new_cost_price')
                ->label('Nuevo costo (opcional)')
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->live(debounce: 400)
                ->helperText('Déjelo vacío para no modificar el costo. Puede bajar el costo si corresponde.')
                ->prefixIcon(Heroicon::CurrencyDollar),
            Hidden::make('_branch_id')->dehydrated(false),
            Hidden::make('_current_cost_price')->dehydrated(false),
            Hidden::make('_applies_vat')->dehydrated(false),
            Hidden::make('_original_product_category_id')->dehydrated(false),
            Hidden::make('_original_quantity')->dehydrated(false),
            Hidden::make('_product_name')->dehydrated(false),
            Hidden::make('_branch_name')->dehydrated(false),
            Placeholder::make('pricing_breakdown')
                ->label('Detalle del cálculo')
                ->content(function (Get $get): HtmlString {
                    $state = [
                        'new_cost_price' => $get('new_cost_price'),
                        'product_category_id' => $get('product_category_id'),
                        '_branch_id' => $get('_branch_id'),
                        '_current_cost_price' => $get('_current_cost_price'),
                        '_applies_vat' => $get('_applies_vat'),
                        '_original_product_category_id' => $get('_original_product_category_id'),
                    ];

                    $breakdown = InventoryFinancialPricingBreakdown::fromFormState($state);
                    if ($breakdown === null) {
                        return new HtmlString('');
                    }

                    return InventoryFinancialPricingBreakdown::toHtml($breakdown);
                })
                ->visible(function (Get $get): bool {
                    return InventoryFinancialPricingBreakdown::shouldDisplay([
                        'new_cost_price' => $get('new_cost_price'),
                        'product_category_id' => $get('product_category_id'),
                        '_original_product_category_id' => $get('_original_product_category_id'),
                    ]);
                }),
            ...self::otpFields(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function otpFields(): array
    {
        return [
            Placeholder::make('otp_help')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<div class="rounded-xl border border-warning-500/40 bg-warning-500/10 p-3 text-sm text-warning-700 dark:text-warning-200">'
                    .'<p class="font-medium">Autorización requerida</p>'
                    .'<p class="mt-1">Para guardar cambios de existencia, costo o categoría solicite un código OTP. Se enviará por email y WhatsApp a los administradores. Válido 3 minutos y de un solo uso.</p>'
                    .'</div>'
                ))
                ->visible(fn (): bool => self::actorRequiresOtp()),
            SchemaActions::make([
                Action::make('requestInventoryAuditOtp')
                    ->label('Solicitar código OTP')
                    ->icon(Heroicon::Key)
                    ->color('warning')
                    ->action(function (Get $get): void {
                        $user = Auth::user();
                        if (! $user instanceof User) {
                            return;
                        }

                        try {
                            $context = self::buildOtpIssueContext($get);

                            if ($context['changes'] === []) {
                                Notification::make()
                                    ->title('Sin cambios para autorizar')
                                    ->body('Indique una cantidad, costo o categoría distinta antes de solicitar el OTP.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            app(InventoryAuditOtpService::class)->issue($user, $context);

                            Notification::make()
                                ->title('Código OTP enviado')
                                ->body('Se envió un código de 6 dígitos por email y WhatsApp a los administradores, con el detalle del cambio. Caduca en 3 minutos.')
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('No se pudo solicitar el OTP')
                                ->body(collect($e->errors())->flatten()->first() ?: 'Error de validación.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
                ->visible(fn (): bool => self::actorRequiresOtp()),
            OneTimeCodeInput::make('otp_code')
                ->label('Código OTP')
                ->length(6)
                ->helperText('Ingrese el código de 6 dígitos. Es de un solo uso: al aplicar el cambio deberá solicitar otra clave.')
                ->visible(fn (): bool => self::actorRequiresOtp())
                ->dehydrated(fn (): bool => self::actorRequiresOtp()),
        ];
    }

    /**
     * Contexto del OTP: producto, sucursal y líneas de cambio solicitadas.
     *
     * @return array{
     *     product_name: string|null,
     *     branch_name: string|null,
     *     changes: list<string>
     * }
     */
    public static function buildOtpIssueContext(Get $get): array
    {
        $changes = [];

        $originalQuantity = round((float) ($get('_original_quantity') ?? 0), 3);
        $countedRaw = $get('counted_quantity');
        if ($countedRaw !== null && $countedRaw !== '') {
            $countedQuantity = round((float) $countedRaw, 3);
            if (abs($countedQuantity - $originalQuantity) > 0.0001) {
                $changes[] = 'Existencia: '
                    .InventoryQuantityFormat::display($originalQuantity)
                    .' → '
                    .InventoryQuantityFormat::display($countedQuantity);
            }
        }

        $originalCost = round((float) ($get('_current_cost_price') ?? 0), 2);
        $newCostRaw = $get('new_cost_price');
        if ($newCostRaw !== null && $newCostRaw !== '') {
            $newCost = round(max(0.0, (float) $newCostRaw), 2);
            if (abs($newCost - $originalCost) > 0.00001) {
                $changes[] = 'Costo: '
                    .number_format($originalCost, 2, ',', '.')
                    .' → '
                    .number_format($newCost, 2, ',', '.')
                    .' USD';
            }
        }

        $originalCategoryId = filled($get('_original_product_category_id'))
            ? (int) $get('_original_product_category_id')
            : null;
        $requestedCategoryId = filled($get('product_category_id'))
            ? (int) $get('product_category_id')
            : null;

        if (
            $requestedCategoryId !== null
            && $requestedCategoryId !== $originalCategoryId
        ) {
            $categoryNames = ProductCategory::query()
                ->whereIn('id', array_values(array_filter([$originalCategoryId, $requestedCategoryId])))
                ->pluck('name', 'id');

            $from = $originalCategoryId !== null
                ? (string) ($categoryNames[$originalCategoryId] ?? '#'.$originalCategoryId)
                : 'Sin categoría';
            $to = (string) ($categoryNames[$requestedCategoryId] ?? '#'.$requestedCategoryId);

            $changes[] = 'Categoría: '.$from.' → '.$to;
        }

        return [
            'product_name' => filled($get('_product_name')) ? (string) $get('_product_name') : null,
            'branch_name' => filled($get('_branch_name')) ? (string) $get('_branch_name') : null,
            'changes' => $changes,
        ];
    }

    public static function actorRequiresOtp(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && app(InventoryAuditOtpService::class)->actorRequiresOtp($user);
    }

    /**
     * El botón de guardar del modal siempre visible; el OTP se valida al guardar.
     */
    public static function configureOtpGatedModalSubmit(Action $action, ?string $submitLabel = null): Action
    {
        $label = $submitLabel ?? 'Guardar cambios';

        return $action
            ->modalSubmitActionLabel($label)
            ->modalSubmitAction(
                fn (Action $action): Action => $action
                    ->label($label)
                    ->icon(null)
            );
    }

    /**
     * @return array{
     *     counted_quantity: float,
     *     new_cost_price: null,
     *     product_category_id: int|null,
     *     _branch_id: int,
     *     _current_cost_price: float,
     *     _applies_vat: bool,
     *     _original_product_category_id: int|null,
     *     _original_quantity: float,
     *     _product_name: string,
     *     _branch_name: string,
     *     otp_code: null
     * }
     */
    public static function fillFromProductAndBranch(
        Product $product,
        int $branchId,
        float $systemQuantity,
        float $systemCostPrice,
    ): array {
        $categoryId = $product->product_category_id !== null
            ? (int) $product->product_category_id
            : null;

        $branchName = (string) (Branch::query()->whereKey($branchId)->value('name') ?? '');

        return [
            'counted_quantity' => $systemQuantity,
            'new_cost_price' => null,
            'product_category_id' => $categoryId,
            '_branch_id' => $branchId,
            '_current_cost_price' => round($systemCostPrice, 2),
            '_applies_vat' => (bool) ($product->applies_vat ?? false),
            '_original_product_category_id' => $categoryId,
            '_original_quantity' => round($systemQuantity, 3),
            '_product_name' => (string) ($product->name ?? ''),
            '_branch_name' => $branchName,
            'otp_code' => null,
        ];
    }
}
