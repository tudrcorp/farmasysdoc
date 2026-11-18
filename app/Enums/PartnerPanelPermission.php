<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;
use App\Models\PartnerCompany;

/**
 * Permisos de un usuario del panel de aliados (dale · Aliados). Se guardan por usuario en
 * `users.partner_permissions` y los asigna el administrador del panel del aliado.
 *
 * «Gestionar usuarios» no se asigna: es exclusivo del administrador del panel
 * (`users.partner_is_admin`, designado por Farmadoc), que además tiene todos los permisos.
 */
enum PartnerPanelPermission: string
{
    use HasSpanishLabels;

    case Dashboard = 'dashboard';
    case PlansAndAgreements = 'plans_agreements';
    case Account = 'account';
    case ViewOrders = 'orders_view';
    case CreateOrders = 'orders_create';
    case PlacePrincipalInventoryOrders = 'principal_inventory_orders';
    case ApiDocs = 'api_docs';
    case DeactivateAffiliates = 'affiliates_deactivate';
    case ManageUsers = 'users_manage';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Tablero',
            self::PlansAndAgreements => 'Planes y convenios',
            self::Account => 'Estado de cuenta',
            self::ViewOrders => 'Ver pedidos',
            self::CreateOrders => 'Crear pedidos',
            self::PlacePrincipalInventoryOrders => 'Hacer pedidos del inventario',
            self::ApiDocs => 'Documentación API',
            self::DeactivateAffiliates => 'Dar de baja afiliados',
            self::ManageUsers => 'Gestionar usuarios',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Dashboard => 'Gráficos y métricas de la gestión.',
            self::PlansAndAgreements => 'Planes, montos, vigencia e historial de convenios.',
            self::Account => 'Crédito, saldo, consumo y movimientos.',
            self::ViewOrders => 'Listado y detalle de los pedidos.',
            self::CreateOrders => 'Hacer y modificar pedidos pendientes (incluye verlos).',
            self::PlacePrincipalInventoryOrders => 'Ver el inventario de Las Delicias, elegir productos e indicar la cantidad para crear un pedido.',
            self::ApiDocs => 'Página de integración por API.',
            self::DeactivateAffiliates => 'Retirar (y reincorporar) afiliados del convenio, indicando el motivo.',
            self::ManageUsers => 'Crear usuarios del equipo, asignar permisos y suspender accesos. Solo administradores del panel.',
        };
    }

    /**
     * Permisos que se marcan por defecto al crear un usuario nuevo.
     *
     * @return list<self>
     */
    public static function defaults(): array
    {
        return [self::Dashboard, self::PlansAndAgreements, self::Account, self::ViewOrders, self::CreateOrders];
    }

    /**
     * Permisos que el administrador puede otorgar (todos menos «Gestionar usuarios»).
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $permission): bool => $permission !== self::ManageUsers));
    }

    /**
     * Permisos que se pueden marcar para los analistas de esta compañía.
     * «Hacer pedidos del inventario» solo aparece si Farmadoc lo habilitó en el aliado.
     *
     * @return list<self>
     */
    public static function assignableForCompany(?PartnerCompany $company): array
    {
        $allowsInventoryOrders = (bool) $company?->can_place_orders;

        return array_values(array_filter(
            self::assignable(),
            fn (self $permission): bool => $allowsInventoryOrders || $permission !== self::PlacePrincipalInventoryOrders,
        ));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::assignable());
    }

    /**
     * Opciones para formularios: solo los permisos que esta compañía puede otorgar.
     *
     * @return array<string, string>
     */
    public static function assignableOptions(?PartnerCompany $company = null): array
    {
        $options = [];
        foreach (self::assignableForCompany($company) as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function assignableDescriptions(?PartnerCompany $company = null): array
    {
        $descriptions = [];
        foreach (self::assignableForCompany($company) as $case) {
            $descriptions[$case->value] = $case->description();
        }

        return $descriptions;
    }

    /**
     * Normaliza lo que llega de un formulario: solo permisos asignables, sin repetir y con
     * «Ver pedidos» implícito cuando se puede crear.
     *
     * @return list<string>
     */
    public static function normalize(mixed $values): array
    {
        $valid = collect(is_array($values) ? $values : [])
            ->map(fn (mixed $value): ?string => $value instanceof self ? $value->value : (is_string($value) ? $value : null))
            ->filter(fn (?string $value): bool => $value !== null && self::tryFrom($value) !== null && $value !== self::ManageUsers->value)
            ->unique();

        if ($valid->contains(self::CreateOrders->value) && ! $valid->contains(self::ViewOrders->value)) {
            $valid->push(self::ViewOrders->value);
        }

        return collect(self::values())->filter(fn (string $value): bool => $valid->contains($value))->values()->all();
    }

    /**
     * Igual que {@see normalize()}, pero quita el pedido del inventario si Farmadoc no lo habilitó.
     *
     * @return list<string>
     */
    public static function normalizeForCompany(mixed $values, ?PartnerCompany $company): array
    {
        $normalized = self::normalize($values);

        if ($company?->can_place_orders) {
            return $normalized;
        }

        return array_values(array_filter(
            $normalized,
            fn (string $value): bool => $value !== self::PlacePrincipalInventoryOrders->value,
        ));
    }
}
