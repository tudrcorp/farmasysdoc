<?php

namespace App\Services\Audit;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        string $event,
        ?string $description = null,
        ?string $auditableType = null,
        null|string|int $auditableId = null,
        ?string $auditableLabel = null,
        array $properties = [],
        ?Request $request = null,
        ?User $user = null,
    ): void {
        try {
            $request ??= request();
            $user ??= Auth::user();
            $user = $user instanceof User ? $user : null;

            $roles = $user?->getAttributeValue('roles');
            if (! is_array($roles)) {
                $roles = null;
            }

            if ($properties !== []) {
                try {
                    $encoded = json_encode($properties, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                } catch (\JsonException) {
                    $properties = [
                        '_json_encode_failed' => true,
                        '_keys' => array_keys(is_array($properties) ? $properties : []),
                    ];
                    $encoded = json_encode($properties, JSON_UNESCAPED_UNICODE);
                }
                if (strlen($encoded) > 62_000) {
                    $properties = [
                        '_truncated' => true,
                        '_original_bytes' => strlen($encoded),
                    ];
                }
            }

            DB::table('audit_logs')->insert([
                'uid' => (string) Str::ulid(),
                'user_id' => $user?->getKey(),
                'user_email' => $user?->email,
                'roles_snapshot' => $roles !== null ? json_encode($roles) : null,
                'event' => Str::limit($event, 64, ''),
                'auditable_type' => $auditableType !== null ? Str::limit($auditableType, 255, '') : null,
                'auditable_id' => $auditableId !== null ? Str::limit((string) $auditableId, 64, '') : null,
                'auditable_label' => $auditableLabel !== null ? Str::limit($auditableLabel, 512, '') : null,
                'description' => $description,
                'properties' => $properties !== [] ? json_encode($properties, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => $request?->ip(),
                'user_agent' => self::truncate($request?->userAgent(), 2000),
                'http_method' => $request ? Str::limit($request->method(), 12, '') : null,
                'url' => self::truncate($request?->fullUrl(), 2000),
                'route_name' => $request?->route()?->getName(),
                'panel_id' => self::detectPanelId($request),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $extraProperties
     */
    public static function forModel(
        Model $model,
        string $event,
        array $extraProperties = [],
        ?Request $request = null,
    ): void {
        $type = $model::class;
        $key = $model->getKey();
        $label = self::resolveModelLabel($model);

        $properties = array_merge(
            ['model' => class_basename($type)],
            $extraProperties,
        );

        self::record(
            event: $event,
            description: class_basename($type).' · '.$event.($label !== null ? ' · '.$label : ''),
            auditableType: $type,
            auditableId: $key !== null ? (string) $key : null,
            auditableLabel: $label,
            properties: $properties,
            request: $request,
        );
    }

    public static function forAuthentication(
        string $event,
        ?User $user,
        ?string $description = null,
        array $properties = [],
        ?Request $request = null,
    ): void {
        self::record(
            event: $event,
            description: $description,
            properties: $properties,
            request: $request,
            user: $user,
        );
    }

    private static function resolveModelLabel(Model $model): ?string
    {
        foreach (['sale_number', 'name', 'email', 'title', 'sku', 'barcode', 'supplier_invoice_number'] as $attr) {
            if (isset($model->{$attr}) && filled($model->{$attr})) {
                return (string) $model->{$attr};
            }
        }

        $key = $model->getKey();

        return $key !== null ? '#'.$key : null;
    }

    private static function truncate(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        return Str::limit($value, $max, '…');
    }

    private static function detectPanelId(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $path = ltrim($request->path(), '/');
        if (str_starts_with($path, 'farmaadmin')) {
            return 'farmaadmin';
        }

        if (str_starts_with($path, 'business-partners')) {
            return 'business-partners';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function sanitizeAttributes(string $modelClass, array $attributes): array
    {
        $hidden = [
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ];

        if ($modelClass === User::class) {
            foreach ($hidden as $key) {
                unset($attributes[$key]);
            }
        }

        foreach ($attributes as $key => $value) {
            $attributes[$key] = self::normalizeValueForAuditJson($value);
        }

        return $attributes;
    }

    /**
     * Convierte valores de Eloquent a tipos seguros para {@see json_encode} en auditoría (evita fallos silenciosos).
     */
    public static function normalizeValueForAuditJson(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => self::normalizeValueForAuditJson($item), $value);
        }

        return is_object($value) ? '['.class_basename($value).']' : $value;
    }
}
