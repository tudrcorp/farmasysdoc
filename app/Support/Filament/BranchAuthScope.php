<?php

namespace App\Support\Filament;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;

/**
 * Alcance por sucursal en paneles Filament: ADMINISTRADOR y rol DELIVERY ven todo; el resto solo su `branch_id`.
 * En ventas ({@see self::applyToSalesQuery()}), usuarios con rol CAJERO solo ven ventas cuyo `created_by` coincide con su usuario.
 */
final class BranchAuthScope
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query): Builder
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return $query;
        }

        if ($user->isAdministrator() || $user->isDeliveryUser()) {
            return $query;
        }

        if ($user->branch_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('branch_id', $user->branch_id);
    }

    /**
     * Tabla de pedidos: el alcance por sucursal no aplica a usuarios de compañía aliada
     * (sus filas ya vienen acotadas por `partner_company_id` en {@see OrderResource::getEloquentQuery()}).
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public static function applyToOrdersTableQuery(Builder $query): Builder
    {
        $user = Auth::user();
        if ($user instanceof User && ! $user->isAdministrator() && $user->isPartnerCompanyUser()) {
            return $query;
        }

        return self::apply($query);
    }

    /**
     * Alcance de ventas: sucursal del usuario, más ventas creadas al completar un traslado donde el usuario
     * pertenece a la sucursal receptora (esas ventas guardan `branch_id` de la sucursal emisora).
     *
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    public static function applyToSalesQuery(Builder $query): Builder
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return $query;
        }

        if ($user->isAdministrator() || $user->isDeliveryUser()) {
            return $query;
        }

        if ($user->branch_id === null) {
            return $query->whereRaw('1 = 0');
        }

        $branchId = (int) $user->branch_id;
        $salesTable = $query->getModel()->getTable();

        $query->where(function (Builder $inner) use ($branchId, $salesTable): void {
            $inner->where($salesTable.'.branch_id', $branchId)
                ->orWhereExists(function (QueryBuilder $sub) use ($branchId, $salesTable): void {
                    $sub->from('product_transfers')
                        ->whereColumn('product_transfers.sale_id', $salesTable.'.id')
                        ->where('product_transfers.to_branch_id', $branchId);
                });
        });

        if ($user->isCashier()) {
            $identifiers = self::saleCreatorMatchValuesForUser($user);
            if ($identifiers === []) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereIn($salesTable.'.created_by', $identifiers);
        }

        return $query;
    }

    /**
     * Valores posibles de {@see Sale::$created_by} para el usuario actual (email, nombre o id como string),
     * según cómo se guarda al registrar desde caja o formulario.
     *
     * @return list<string>
     */
    public static function saleCreatorMatchValuesForUser(User $user): array
    {
        $candidates = [
            (string) $user->getKey(),
            filled($user->email) ? (string) $user->email : null,
            filled($user->name) ? (string) $user->name : null,
        ];

        return array_values(array_unique(array_filter(
            $candidates,
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        )));
    }

    /**
     * Selects y filtros de sucursal en Filament: solo administradores listan todas las sucursales;
     * el resto solo ve la sucursal asignada en el usuario (`branch_id`).
     *
     * @param  Builder<Branch>  $query  Consulta sobre `branches` (p. ej. ya filtrada por `is_active` y orden).
     * @return Builder<Branch>
     */
    public static function applyToBranchFormSelect(Builder $query): Builder
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return $query;
        }

        if ($user->isAdministrator()) {
            return $query;
        }

        if (filled($user->branch_id)) {
            return $query->whereKey((int) $user->branch_id);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Valor por defecto de sucursal en formularios operativos (compras, ventas, etc.).
     */
    public static function suggestedBranchIdForOperationalForm(): ?int
    {
        $user = Auth::user();
        if (! $user instanceof User || $user->isAdministrator()) {
            return null;
        }

        return filled($user->branch_id) ? (int) $user->branch_id : null;
    }
}
