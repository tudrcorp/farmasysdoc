<?php

namespace App\Support\Users;

final class UserRoleLabels
{
    public static function label(mixed $role): string
    {
        return match (strtoupper((string) $role)) {
            'ADMINISTRADOR' => 'Administrador',
            'MARKETING' => 'Marketing',
            'DELIVERY' => 'Entregas',
            default => (string) $role,
        };
    }
}
