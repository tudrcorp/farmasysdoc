<?php

namespace App\Filament\Resources\Marketing\Broadcasts\Schemas;

use App\Enums\MarketingBroadcastSendMode;
use App\Enums\MarketingBroadcastStatus;
use App\Enums\MarketingBroadcastType;
use App\Models\MarketingEmailTemplate;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class MarketingBroadcastForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Difusión masiva')
                    ->icon(Heroicon::PaperAirplane)
                    ->description('Correo y/o WhatsApp (WhatsApp requiere integración; por ahora queda registrado en log).')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre interno')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('type')
                            ->label('Tipo')
                            ->options(MarketingBroadcastType::options())
                            ->native(false)
                            ->required()
                            ->default(MarketingBroadcastType::Promotion->value),
                        Select::make('send_mode')
                            ->label('Audiencia')
                            ->options(MarketingBroadcastSendMode::options())
                            ->native(false)
                            ->required()
                            ->live()
                            ->default(MarketingBroadcastSendMode::All->value),
                        Select::make('marketing_segment_id')
                            ->label('Segmento')
                            ->relationship(
                                name: 'segment',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->visible(fn (callable $get): bool => $get('send_mode') === MarketingBroadcastSendMode::Segment->value),
                        Select::make('selectedClients')
                            ->label('Clientes')
                            ->relationship(
                                name: 'selectedClients',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->orderBy('name'),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->visible(fn (callable $get): bool => $get('send_mode') === MarketingBroadcastSendMode::Selected->value)
                            ->columnSpanFull(),
                        Select::make('marketing_campaign_id')
                            ->label('Campaña (opcional)')
                            ->relationship(
                                name: 'campaign',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->orderByDesc('updated_at'),
                            )
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('marketing_email_template_id')
                            ->label('Plantilla de correo (opcional)')
                            ->relationship(
                                name: 'emailTemplate',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                if (blank($state)) {
                                    return;
                                }
                                $tpl = MarketingEmailTemplate::query()->find($state);
                                if ($tpl) {
                                    $set('subject', $tpl->subject);
                                    $set('email_html', $tpl->body_html);
                                }
                            }),
                        CheckboxList::make('channels')
                            ->label('Canales')
                            ->options([
                                'email' => 'Correo electrónico',
                                'whatsapp' => 'WhatsApp',
                            ])
                            ->default(['email'])
                            ->columns(2)
                            ->required(),
                        TextInput::make('subject')
                            ->label('Asunto (email)')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('email_html')
                            ->label('Cuerpo HTML (email)')
                            ->rows(12)
                            ->helperText('Variables: {{nombre}}, {{email}}')
                            ->columnSpanFull(),
                        Textarea::make('whatsapp_body')
                            ->label('Mensaje WhatsApp')
                            ->rows(4)
                            ->columnSpanFull(),
                        Select::make('status')
                            ->label('Estado')
                            ->options(MarketingBroadcastStatus::options())
                            ->native(false)
                            ->default(MarketingBroadcastStatus::Draft->value)
                            ->visibleOn('create'),
                        DateTimePicker::make('scheduled_at')
                            ->label('Programar envío (opcional)')
                            ->native(false)
                            ->seconds(false)
                            ->helperText('Si lo define, puede enlazar con un job programado más adelante.'),
                    ])
                    ->columns(2),
            ]);
    }
}
