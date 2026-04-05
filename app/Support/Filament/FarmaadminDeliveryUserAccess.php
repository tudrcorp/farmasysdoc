<?php

namespace App\Support\Filament;

use App\Filament\Resources\Deliveries\DeliveryResource;
use App\Filament\Resources\OrderServices\OrderServiceResource;
use App\Filament\Resources\ProductTransfers\ProductTransferResource;
use App\Models\User;
use Filament\Resources\Resource;

/**
 * Usuarios con rol DELIVERY (y sin ADMINISTRADOR) solo usan un subconjunto del panel Farmaadmin.
 */
final class FarmaadminDeliveryUserAccess
{
    /**
     * @var list<class-string<resource>>
     */
    public const ALLOWED_RESOURCE_CLASSES = [
        DeliveryResource::class,
        OrderServiceResource::class,
        ProductTransferResource::class,
    ];

    public static function isRestrictedDeliveryUser(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->isDeliveryUser()
            && ! $user->isAdministrator();
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public static function isAllowedResource(string $resourceClass): bool
    {
        return in_array($resourceClass, self::ALLOWED_RESOURCE_CLASSES, true);
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public static function denies(string $resourceClass): bool
    {
        return self::isRestrictedDeliveryUser() && ! self::isAllowedResource($resourceClass);
    }
}
