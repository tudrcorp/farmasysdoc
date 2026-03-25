<?php

namespace App\Filament\Resources\ApiClients\Schemas;

use App\Filament\Resources\ApiClients\Pages\ViewApiClient;
use App\Models\ApiClient;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Livewire\Component as LivewireComponent;

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
                    ->description('El secreto Bearer empieza por fd_ (~67 caracteres) y solo se muestra al crear o regenerar. Lo de abajo es solo una huella de referencia: no sirve en Authorization.')
                    ->icon(Heroicon::Key)
                    ->schema([
                        TextEntry::make('plain_token_for_integrator')
                            ->label('Token para enviar al consumidor de la API')
                            ->helperText('Este es el valor exacto para el header Authorization: Bearer … Solo aparece en esta vista justo después de crear el cliente o de usar «Regenerar token». Si no ves el token, regenera uno; el anterior dejará de funcionar.')
                            ->fontFamily(FontFamily::Mono)
                            ->columnSpanFull()
                            ->getStateUsing(function (LivewireComponent $livewire): ?string {
                                if (! $livewire instanceof ViewApiClient) {
                                    return null;
                                }

                                return $livewire->revealedPlainToken;
                            })
                            ->placeholder('No hay secreto visible ahora. El servidor no guarda el token en texto plano: usa «Regenerar token» en la parte superior para generar uno y copiarlo aquí.')
                            ->copyable(fn (LivewireComponent $livewire): bool => $livewire instanceof ViewApiClient && filled($livewire->revealedPlainToken))
                            ->copyableState(fn (LivewireComponent $livewire): string => $livewire instanceof ViewApiClient && filled($livewire->revealedPlainToken)
                                ? (string) $livewire->revealedPlainToken
                                : '')
                            ->icon(Heroicon::Key),
                        TextEntry::make('token_hash')
                            ->label('Huella del token (no usar como Bearer)')
                            ->placeholder('—')
                            ->formatStateUsing(fn (?string $state): string => filled($state)
                                ? substr($state, 0, 12).'…'.substr($state, -8)
                                : '—')
                            ->helperText('Resumen visual del hash SHA-256 guardado en servidor. Nunca lo pongas en Authorization: Bearer; el aliado debe usar el token fd_… entregado por separado.')
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
3. **Autenticación:** header `Authorization: Bearer <SECRETO>`. El secreto **siempre empieza por** `fd_` (unos 67 caracteres). **Solo se muestra al crear este cliente o al regenerar el token**; entrégalo por un canal seguro. **No** uses la *huella* del panel (texto truncado tipo `abc123…ff00`): es solo referencia visual del hash en base de datos. **Tampoco** envíes los 64 caracteres hexadecimales completos del hash: el servidor los rechaza (401).
4. **Código de compañía aliada (`partner_company`):** obligatorio en **inventario, pedidos y órdenes de servicio** (no aplica a `GET /status`). Debe ser **exactamente** el **código** (`code`) del registro en **Filament → Compañías aliadas**.
5. **Lista blanca de IPs:** si hay IPs configuradas, las llamadas autenticadas deben originarse solo desde esas direcciones; si el campo indica “Cualquier IP”, basta con el token válido.
6. **Documentación pública para desarrolladores:** [Abrir guía de API]({$docsUrl}) (mismos nombres de parámetros que valida el backend).

---

**Cliente API en panel:** {$name}

| Dato | Qué enviar al aliado |
|------|----------------------|
| Base URL | `{$baseUrl}` |
| Estado (sin auth) | `GET {$baseUrl}/status` → `status: active` |
| Header | `Authorization: Bearer fd_…` (secreto plano, **no** la huella ni el hash SHA-256) |
| Código aliado | `partner_company` = `partner_companies.code` (no en `/status`) |
| Errores habituales | **401** token ausente o inválido · **403** IP no permitida · **422** validación (p. ej. código de aliado inexistente) |
MD;
    }
}
