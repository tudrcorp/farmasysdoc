<?php

namespace App\Filament\Resources\ApiClients\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class ApiClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cliente API')
                    ->description('Identifica al aliado o sistema que consumirá los endpoints externos (pedidos, inventario, etc.).')
                    ->icon(Heroicon::UserGroup)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre del cliente')
                                    ->placeholder('Ej. Droguería Central — integración ERP')
                                    ->helperText('Nombre interno para reconocer al aliado en listados y auditoría.')
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::Tag)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Token de acceso')
                    ->description('El token Bearer se genera de forma segura al guardar. Solo se muestra una vez; cópialo y guárdalo en un gestor de secretos.')
                    ->icon(Heroicon::Key)
                    ->schema([
                        Placeholder::make('token_flow_hint')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="fi-api-token-hint rounded-2xl border border-zinc-200/80 bg-zinc-50/90 p-4 text-sm leading-relaxed text-zinc-700 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-zinc-200">'
                                .'<p class="font-semibold text-zinc-900 dark:text-white">Flujo rápido</p>'
                                .'<ol class="mt-2 list-decimal space-y-1.5 ps-5">'
                                .'<li>Completa el nombre y opciones de abajo.</li>'
                                .'<li>Pulsa <strong>Crear</strong>: se generará un token aleatorio.</li>'
                                .'<li>En la siguiente pantalla podrás <strong>copiar</strong> el token (no volverá a mostrarse).</li>'
                                .'</ol></div>'
                            ))
                            ->visibleOn('create'),
                        Placeholder::make('token_edit_hint')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="fi-api-token-hint rounded-2xl border border-amber-200/80 bg-amber-50/90 p-4 text-sm text-amber-950 shadow-sm dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">'
                                .'<p class="font-semibold">El token no se puede ver de nuevo</p>'
                                .'<p class="mt-1">Para obtener credenciales nuevas, usa <strong>Regenerar token</strong> en la vista del registro.</p>'
                                .'</div>'
                            ))
                            ->visibleOn('edit'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Seguridad de red')
                    ->description('Restringe el uso del token a direcciones IP conocidas (opcional).')
                    ->icon(Heroicon::ShieldCheck)
                    ->schema([
                        TagsInput::make('allowed_ips')
                            ->label('IPs permitidas')
                            ->placeholder('Escribe una IPv4 o IPv6 y pulsa Enter')
                            ->helperText('Vacío: se acepta cualquier IP. Con etiquetas: solo esas IPs podrán autenticarse.')
                            ->splitKeys(['Tab', ','])
                            ->rules(['nullable', 'array'])
                            ->nullable(),
                        Toggle::make('is_active')
                            ->label('Cliente activo')
                            ->helperText('Desactiva para bloquear el acceso sin borrar el registro.')
                            ->default(true),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
