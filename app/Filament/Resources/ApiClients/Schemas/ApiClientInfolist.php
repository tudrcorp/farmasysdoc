<?php

namespace App\Filament\Resources\ApiClients\Schemas;

use App\Models\ApiClient;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ApiClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Qué entregar al aliado (integración)')
                    ->description('Resumen operativo para analistas: qué datos debe recibir la compañía aliada para conectar sin idas y vueltas.')
                    ->icon(Heroicon::DocumentText)
                    ->schema([
                        TextEntry::make('integration_handoff')
                            ->hiddenLabel()
                            ->markdown()
                            ->columnSpanFull()
                            ->getStateUsing(fn (ApiClient $record): string => self::integrationHandoffMarkdown($record)),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Cliente API')
                    ->description('Identificación y estado del acceso a la API externa.')
                    ->icon(Heroicon::UserGroup)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nombre')
                                    ->icon(Heroicon::Tag),
                                IconEntry::make('is_active')
                                    ->label('Activo')
                                    ->boolean(),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Credenciales')
                    ->description('El token en texto plano no se almacena; solo se muestra al crear o al regenerar.')
                    ->icon(Heroicon::Key)
                    ->schema([
                        TextEntry::make('token_hash')
                            ->label('Huella del token')
                            ->placeholder('—')
                            ->formatStateUsing(fn (?string $state): string => filled($state)
                                ? substr($state, 0, 12).'…'.substr($state, -8)
                                : '—')
                            ->copyable()
                            ->copyableState(fn ($record): string => $record->token_hash ?? '')
                            ->helperText('Hash SHA-256 del token Bearer (referencia). No es el secreto de autenticación.')
                            ->icon(Heroicon::FingerPrint),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Red y uso')
                    ->description('Restricciones opcionales y última actividad.')
                    ->icon(Heroicon::GlobeAlt)
                    ->schema([
                        TextEntry::make('allowed_ips')
                            ->label('IPs permitidas')
                            ->placeholder('Cualquier IP')
                            ->formatStateUsing(function (?array $state): string {
                                if (! is_array($state) || $state === []) {
                                    return 'Cualquier IP';
                                }

                                return implode(', ', $state);
                            })
                            ->icon(Heroicon::ShieldCheck),
                        TextEntry::make('last_used_at')
                            ->label('Último uso')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Sin uso registrado')
                            ->icon(Heroicon::Clock),
                        TextEntry::make('created_at')
                            ->label('Creado')
                            ->dateTime('d/m/Y H:i')
                            ->icon(Heroicon::CalendarDays),
                        TextEntry::make('updated_at')
                            ->label('Actualizado')
                            ->dateTime('d/m/Y H:i')
                            ->icon(Heroicon::ArrowPathRoundedSquare),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    private static function integrationHandoffMarkdown(ApiClient $record): string
    {
        $baseUrl = rtrim(url('/'), '/').'/api/external';
        $docsUrl = route('public.api-docs');
        $name = e($record->name);

        return <<<MD
### Checklist para el integrador

1. **Comprobar disponibilidad (opcional):** `GET {$baseUrl}/status` — responde si la API está **activa**; **no** requiere token ni `partner_company`. Úsalo para evitar reintentos masivos cuando el servicio no está disponible.
2. **URL base:** `{$baseUrl}` (el resto de rutas usan este prefijo).
3. **Autenticación:** en las operaciones con datos (inventario, pedidos, órdenes de servicio), header `Authorization: Bearer <TOKEN>`. El valor en texto plano **solo se muestra al crear este cliente o al regenerar el token**; cópialo y entrégalo por un canal seguro. No confundir con la *huella* (hash) que ves abajo: esa es solo referencia interna.
4. **Código de compañía aliada (`partner_company`):** obligatorio en **inventario, pedidos y órdenes de servicio** (no aplica a `GET /status`). Debe ser **exactamente** el **código** (`code`) del registro en **Filament → Compañías aliadas**.
5. **Lista blanca de IPs:** si hay IPs configuradas, las llamadas autenticadas deben originarse solo desde esas direcciones; si el campo indica “Cualquier IP”, basta con el token válido.
6. **Documentación pública para desarrolladores:** [Abrir guía de API]({$docsUrl}) (mismos nombres de parámetros que valida el backend).

---

**Cliente API en panel:** {$name}

| Dato | Qué enviar al aliado |
|------|----------------------|
| Base URL | `{$baseUrl}` |
| Estado (sin auth) | `GET {$baseUrl}/status` → `status: active` |
| Header | `Authorization: Bearer <token plano>` (operaciones con datos) |
| Código aliado | `partner_company` = `partner_companies.code` (no en `/status`) |
| Errores habituales | **401** token ausente o inválido · **403** IP no permitida · **422** validación (p. ej. código de aliado inexistente) |
MD;
    }
}
