<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Models\WhatsappAgentSetting;
use App\Support\WhatsappAgent\IsabellaDefaultPrompt;
use App\Support\WhatsappAgent\WhatsappGateway;
use App\Support\WhatsappAgent\WhatsappPhoneNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Configuración del asistente de pedidos por WhatsApp: instancia de Ultramsg, conexión con n8n,
 * modo prueba, horario de analistas e instrucciones del asistente.
 *
 * @property-read Schema $form
 */
class ManageWhatsappAgentSettings extends Page
{
    public const MENU_KEY = 'whatsapp_agent_settings';

    protected static ?string $slug = 'asistente-whatsapp';

    protected static ?string $navigationLabel = 'Asistente WhatsApp';

    protected static ?string $title = 'Asistente de pedidos por WhatsApp';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 42;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChatBubbleLeftRight;

    protected string $view = 'filament-panels::pages.page';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $setting = WhatsappAgentSetting::current();

        $this->form->fill([
            'is_enabled' => $setting->is_enabled,
            'test_mode' => $setting->test_mode,
            'test_phone_numbers' => $setting->normalizedTestPhoneNumbers(),
            'agent_name' => $setting->agentDisplayName(),
            'ultramsg_instance_url' => $setting->ultramsg_instance_url,
            'ultramsg_token' => null,
            'webhook_url' => $setting->webhookUrl(),
            'n8n_webhook_url' => $setting->n8n_webhook_url,
            'agent_api_token' => $setting->agent_api_token,
            'analyst_days' => array_map('strval', $setting->analystDays()),
            'analyst_start' => $setting->analystStartTime(),
            'analyst_end' => $setting->analystEndTime(),
            'message_grouping_seconds' => $setting->message_grouping_seconds,
            'analyst_idle_minutes' => $setting->analyst_idle_minutes,
            'prescription_confidence_threshold' => (float) $setting->prescription_confidence_threshold,
            'suggested_products_notes' => $setting->suggested_products_notes,
            'system_prompt' => $setting->system_prompt,
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'El asistente atiende los pedidos por WhatsApp 24/7 y pasa a los analistas lo que no puede resolver.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Estado del asistente')
                    ->icon(Heroicon::Power)
                    ->description('En modo prueba el asistente solo responde a los teléfonos de la lista. Los mensajes de otros números se guardan, pero no reciben respuesta.')
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('is_enabled')
                                ->label('Asistente encendido')
                                ->helperText('Apagado: los mensajes se guardan en la bandeja, pero el asistente no responde.'),
                            Toggle::make('test_mode')
                                ->label('Modo prueba')
                                ->live(),
                            TextInput::make('agent_name')
                                ->label('Nombre del asistente')
                                ->required()
                                ->maxLength(80),
                        ]),
                        TagsInput::make('test_phone_numbers')
                            ->label('Teléfonos autorizados en modo prueba')
                            ->placeholder('04121234567')
                            ->helperText('Escriba cada número y presione Enter. Acepta 0412…, +58 412… o 58412…')
                            ->trim()
                            ->visible(fn (Get $get): bool => (bool) $get('test_mode')),
                    ])
                    ->columnSpanFull(),

                Section::make('Instancia de Ultramsg')
                    ->icon(Heroicon::DevicePhoneMobile)
                    ->description('La instancia de WhatsApp que usa el asistente. Es independiente de la de avisos de caja, aunque para las pruebas puede ser la misma.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('ultramsg_instance_url')
                                ->label('URL de la instancia')
                                ->placeholder('https://api.ultramsg.com/instance12345')
                                ->url()
                                ->maxLength(255),
                            TextInput::make('ultramsg_token')
                                ->label('Token de la instancia')
                                ->password()
                                ->revealable()
                                ->placeholder(fn (): string => WhatsappAgentSetting::current()->hasUltramsgCredentials() ? '•••••••• (guardado)' : '')
                                ->helperText('Déjelo vacío para conservar el token guardado.')
                                ->maxLength(255),
                        ]),
                        TextInput::make('webhook_url')
                            ->label('URL del webhook (péguela en Ultramsg → Settings → Webhook URL)')
                            ->helperText('En Ultramsg active «webhook_message_received» y «webhook_message_download_media».')
                            ->readOnly()
                            ->copyable(copyMessage: 'Copiado')
                            ->dehydrated(false),
                        Actions::make([
                            Action::make('sendTestMessage')
                                ->label('Enviar mensaje de prueba')
                                ->icon(Heroicon::PaperAirplane)
                                ->color('gray')
                                ->schema([
                                    TextInput::make('phone')
                                        ->label('Teléfono')
                                        ->placeholder('04121234567')
                                        ->required(),
                                ])
                                ->action(fn (array $data): mixed => $this->sendTestMessage((string) $data['phone'])),
                            Action::make('regenerateWebhookSecret')
                                ->label('Generar nueva URL del webhook')
                                ->icon(Heroicon::ArrowPath)
                                ->color('warning')
                                ->requiresConfirmation()
                                ->modalDescription('La URL actual dejará de funcionar. Deberá pegar la nueva en Ultramsg.')
                                ->action(function (Set $set): void {
                                    $setting = WhatsappAgentSetting::current();
                                    $setting->forceFill(['webhook_secret' => WhatsappAgentSetting::newWebhookSecret()])->save();
                                    $set('webhook_url', $setting->webhookUrl());

                                    Notification::make()->title('Nueva URL generada')->body('Péguela en la configuración de Ultramsg.')->success()->send();
                                }),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Conexión con n8n')
                    ->icon(Heroicon::Bolt)
                    ->description('Farmasysdoc le envía cada turno a n8n y n8n responde usando la API del asistente con el mismo token.')
                    ->schema([
                        TextInput::make('n8n_webhook_url')
                            ->label('URL del webhook de n8n (Production URL)')
                            ->placeholder('https://n8n.su-dominio.com/webhook/isabella')
                            ->url()
                            ->maxLength(500),
                        TextInput::make('agent_api_token')
                            ->label('Token del asistente')
                            ->helperText('En n8n: credencial «Header Auth» con nombre Authorization y valor «Bearer » + este token.')
                            ->password()
                            ->revealable()
                            ->readOnly()
                            ->copyable(copyMessage: 'Copiado')
                            ->dehydrated(false),
                        Actions::make([
                            Action::make('regenerateAgentToken')
                                ->label('Generar nuevo token')
                                ->icon(Heroicon::ArrowPath)
                                ->color('warning')
                                ->requiresConfirmation()
                                ->modalDescription('El token actual dejará de funcionar. Deberá actualizarlo en n8n.')
                                ->action(function (Set $set): void {
                                    $token = WhatsappAgentSetting::newAgentApiToken();
                                    WhatsappAgentSetting::current()->forceFill(['agent_api_token' => $token])->save();
                                    $set('agent_api_token', $token);

                                    Notification::make()->title('Nuevo token generado')->body('Actualícelo en la credencial de n8n.')->success()->send();
                                }),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Horario de analistas')
                    ->icon(Heroicon::Clock)
                    ->description('Fuera de este horario el asistente sigue atendiendo y deja el pedido con su resumen en «Pendientes de la jornada».')
                    ->schema([
                        CheckboxList::make('analyst_days')
                            ->label('Días')
                            ->options([
                                '1' => 'Lunes',
                                '2' => 'Martes',
                                '3' => 'Miércoles',
                                '4' => 'Jueves',
                                '5' => 'Viernes',
                                '6' => 'Sábado',
                                '7' => 'Domingo',
                            ])
                            ->columns(7)
                            ->required(),
                        Grid::make(2)->schema([
                            TimePicker::make('analyst_start')
                                ->label('Desde')
                                ->seconds(false)
                                ->required(),
                            TimePicker::make('analyst_end')
                                ->label('Hasta')
                                ->seconds(false)
                                ->after('analyst_start')
                                ->required(),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Comportamiento')
                    ->icon(Heroicon::Sparkles)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('message_grouping_seconds')
                                ->label('Segundos de espera para agrupar mensajes')
                                ->helperText('Si el cliente escribe varios mensajes seguidos, el asistente espera este tiempo y responde una sola vez.')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(60)
                                ->required(),
                            TextInput::make('analyst_idle_minutes')
                                ->label('Minutos sin respuesta del analista')
                                ->helperText('Si el cliente escribe y el analista no ha respondido en este tiempo, el asistente retoma la conversación. 0 = nunca.')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(240)
                                ->required(),
                            TextInput::make('prescription_confidence_threshold')
                                ->label('Confianza mínima al leer un récipe')
                                ->helperText('Entre 0 y 1. Si un medicamento se lee con menos seguridad, se relee con el modelo avanzado y, si sigue dudoso, el asistente le pide al cliente que lo confirme.')
                                ->numeric()
                                ->step(0.05)
                                ->minValue(0)
                                ->maxValue(1)
                                ->required(),
                        ]),
                        Textarea::make('suggested_products_notes')
                            ->label('Productos que el asistente puede sugerir')
                            ->helperText('Ej.: vitaminas, protector solar, cuidado personal. El asistente sugiere uno como máximo por pedido.')
                            ->rows(3)
                            ->maxLength(2000),
                        Textarea::make('system_prompt')
                            ->label('Instrucciones del asistente')
                            ->helperText('Déjelo vacío para usar las instrucciones por defecto.')
                            ->rows(22)
                            ->maxLength(30000)
                            ->hintAction(
                                Action::make('loadDefaultPrompt')
                                    ->label('Cargar instrucciones por defecto')
                                    ->icon(Heroicon::DocumentText)
                                    ->action(fn (Set $set, Get $get): mixed => $set('system_prompt', IsabellaDefaultPrompt::text((string) ($get('agent_name') ?: 'Isabella')))),
                            ),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Guardar configuración')
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $setting = WhatsappAgentSetting::current();

        $phones = [];
        foreach ((array) ($data['test_phone_numbers'] ?? $setting->normalizedTestPhoneNumbers()) as $phone) {
            $normalized = WhatsappPhoneNumber::normalize((string) $phone);

            if ($normalized !== null) {
                $phones[] = $normalized;
            }
        }

        $attributes = [
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'test_mode' => (bool) ($data['test_mode'] ?? true),
            'test_phone_numbers' => array_values(array_unique($phones)),
            'agent_name' => trim((string) $data['agent_name']),
            'ultramsg_instance_url' => filled($data['ultramsg_instance_url'] ?? null) ? rtrim(trim((string) $data['ultramsg_instance_url']), '/') : null,
            'n8n_webhook_url' => filled($data['n8n_webhook_url'] ?? null) ? trim((string) $data['n8n_webhook_url']) : null,
            'analyst_schedule' => [
                'days' => array_values(array_map('intval', (array) $data['analyst_days'])),
                'start' => mb_substr((string) $data['analyst_start'], 0, 5),
                'end' => mb_substr((string) $data['analyst_end'], 0, 5),
            ],
            'message_grouping_seconds' => (int) $data['message_grouping_seconds'],
            'analyst_idle_minutes' => (int) $data['analyst_idle_minutes'],
            'prescription_confidence_threshold' => round((float) $data['prescription_confidence_threshold'], 2),
            'suggested_products_notes' => filled($data['suggested_products_notes'] ?? null) ? trim((string) $data['suggested_products_notes']) : null,
            'system_prompt' => filled($data['system_prompt'] ?? null) ? trim((string) $data['system_prompt']) : null,
        ];

        if (filled($data['ultramsg_token'] ?? null)) {
            $attributes['ultramsg_token'] = trim((string) $data['ultramsg_token']);
        }

        $setting->fill($attributes)->save();

        $this->data['ultramsg_token'] = null;
        $this->data['test_phone_numbers'] = $attributes['test_phone_numbers'];

        $warnings = [];
        if ($setting->is_enabled && ! $setting->agentCanRespond()) {
            $warnings[] = 'El asistente está encendido, pero falta la instancia de Ultramsg o la URL de n8n: no responderá hasta completarlas.';
        }
        if ($setting->test_mode && $setting->normalizedTestPhoneNumbers() === []) {
            $warnings[] = 'El modo prueba está activo sin teléfonos autorizados: el asistente no responderá a nadie.';
        }

        Notification::make()
            ->title('Configuración guardada')
            ->body($warnings === [] ? null : implode(' ', $warnings))
            ->status($warnings === [] ? 'success' : 'warning')
            ->send();
    }

    public function sendTestMessage(string $phone): void
    {
        $result = app(WhatsappGateway::class)->sendText(
            $phone,
            'Mensaje de prueba de '.WhatsappAgentSetting::current()->agentDisplayName().' (Farmadoc). Si lo recibió, la instancia está bien configurada.',
        );

        Notification::make()
            ->title($result->sent ? 'Mensaje enviado' : 'No se pudo enviar')
            ->body($result->sent ? 'Revise el WhatsApp de '.$phone.'.' : $result->error)
            ->status($result->sent ? 'success' : 'danger')
            ->send();
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessFarmaadminMenuKey(self::MENU_KEY);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
