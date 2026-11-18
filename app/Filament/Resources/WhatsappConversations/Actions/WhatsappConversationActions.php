<?php

namespace App\Filament\Resources\WhatsappConversations\Actions;

use App\Enums\WhatsappConversationHandler;
use App\Enums\WhatsappFulfillmentType;
use App\Enums\WhatsappMessageAuthor;
use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappConversationItem;
use App\Services\WhatsappAgent\WhatsappAnalystNotifier;
use App\Services\WhatsappAgent\WhatsappConversationConverter;
use App\Services\WhatsappAgent\WhatsappConversationWorkflow;
use App\Services\WhatsappAgent\WhatsappCustomerRegistrar;
use App\Services\WhatsappAgent\WhatsappOutboundMessenger;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Acciones del analista en la bandeja. La lógica vive en {@see WhatsappConversationWorkflow},
 * {@see WhatsappCustomerRegistrar} y {@see WhatsappConversationConverter}.
 */
final class WhatsappConversationActions
{
    /**
     * @return list<Action>
     */
    public static function header(): array
    {
        return [
            self::reply(),
            self::take(),
            self::returnToAgent(),
            self::convertToPickup(),
            self::convertToDelivery(),
            self::close(),
        ];
    }

    public static function take(): Action
    {
        return Action::make('take')
            ->label('Tomar conversación')
            ->icon(Heroicon::HandRaised)
            ->color('primary')
            ->visible(fn (WhatsappConversation $record): bool => $record->isOpen()
                && ($record->isHandledByAgent() || (int) $record->assigned_user_id !== (int) Auth::id()))
            ->requiresConfirmation()
            ->modalDescription('Desde ahora usted responde al cliente y el asistente deja de escribir en esta conversación.')
            ->action(self::run(fn (WhatsappConversation $record, User $user) => app(WhatsappConversationWorkflow::class)->take($record, $user), 'Conversación tomada'));
    }

    public static function assign(): Action
    {
        return Action::make('assign')
            ->label('Asignar')
            ->icon(Heroicon::UserPlus)
            ->color('gray')
            ->visible(fn (WhatsappConversation $record): bool => $record->isOpen())
            ->modalHeading('Asignar a un analista')
            ->modalDescription('La conversación queda «En gestión» a nombre del analista elegido, que recibe un aviso.')
            ->schema([
                Select::make('user_id')
                    ->label('Analista')
                    ->options(fn (): array => app(WhatsappAnalystNotifier::class)->inboxUsers()
                        ->merge(User::query()->get()->filter(fn (User $user): bool => $user->isAdministrator()))
                        ->unique('id')
                        ->sortBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->default(fn (WhatsappConversation $record): ?int => $record->assigned_user_id)
                    ->searchable()
                    ->required(),
            ])
            ->action(self::run(function (WhatsappConversation $record, User $user, array $data): void {
                $assignee = User::query()->findOrFail((int) $data['user_id']);
                app(WhatsappConversationWorkflow::class)->assignTo($record, $assignee, $user);
            }, 'Conversación asignada'));
    }

    public static function reply(): Action
    {
        return Action::make('reply')
            ->label('Responder')
            ->icon(Heroicon::PaperAirplane)
            ->color('success')
            ->visible(fn (WhatsappConversation $record): bool => $record->isOpen())
            ->modalHeading('Responder al cliente')
            ->modalDescription(fn (WhatsappConversation $record): ?string => $record->isHandledByAgent()
                ? 'Al responder, usted toma la conversación y el asistente deja de escribir.'
                : null)
            ->modalSubmitActionLabel('Enviar por WhatsApp')
            ->schema([
                Textarea::make('body')
                    ->label('Mensaje')
                    ->rows(5)
                    ->required()
                    ->maxLength(4000),
            ])
            ->action(function (WhatsappConversation $record, array $data, Action $action): void {
                $user = self::user();

                if (! $user instanceof User) {
                    $action->halt();
                }

                try {
                    app(WhatsappConversationWorkflow::class)->reply($record, $user, (string) $data['body']);
                } catch (ValidationException $exception) {
                    self::fail($exception, $action);
                }

                Notification::make()->title('Mensaje en camino')->body('Lo verá con ✓ en el chat cuando salga.')->success()->send();

                $record->refresh();
            });
    }

    public static function returnToAgent(): Action
    {
        return Action::make('returnToAgent')
            ->label('Devolver al asistente')
            ->icon(Heroicon::Sparkles)
            ->color('gray')
            ->visible(fn (WhatsappConversation $record): bool => $record->isOpen() && $record->handled_by === WhatsappConversationHandler::Analyst)
            ->modalDescription('El asistente vuelve a atender al cliente. Deje una nota para que retome sin repetir preguntas.')
            ->schema([
                Textarea::make('note')
                    ->label('Nota para el asistente (opcional)')
                    ->placeholder('Ej.: ya confirmé el récipe; falta la dirección de entrega.')
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(self::run(fn (WhatsappConversation $record, User $user, array $data) => app(WhatsappConversationWorkflow::class)->returnToAgent($record, $user, $data['note'] ?? null), 'Conversación devuelta al asistente'));
    }

    public static function close(): Action
    {
        return Action::make('close')
            ->label('Cerrar sin pedido')
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->visible(fn (WhatsappConversation $record): bool => $record->isOpen())
            ->schema([
                Textarea::make('reason')
                    ->label('Motivo')
                    ->placeholder('Ej.: solo consultaba precios; no respondió.')
                    ->rows(2)
                    ->required()
                    ->maxLength(500),
            ])
            ->action(self::run(fn (WhatsappConversation $record, User $user, array $data) => app(WhatsappConversationWorkflow::class)->close($record, $user, (string) $data['reason']), 'Conversación cerrada'));
    }

    public static function convertToPickup(): Action
    {
        return Action::make('convertToPickup')
            ->label('Retiro en tienda')
            ->icon(Heroicon::BuildingStorefront)
            ->color('success')
            ->visible(fn (WhatsappConversation $record): bool => $record->isOpen())
            ->modalHeading('Preparar para retiro en tienda')
            ->modalDescription('Se crea una venta en pausa en la caja de la sucursal. Cualquier cajero de esa sucursal la retoma y la cobra cuando llegue el cliente.')
            ->modalSubmitActionLabel('Enviar a la caja')
            ->schema([
                self::branchSelect(),
                Textarea::make('customer_message')
                    ->label('Mensaje para el cliente (opcional)')
                    ->helperText('{sucursal} se reemplaza por el nombre de la sucursal. Déjelo vacío para no enviar nada.')
                    ->default(fn (WhatsappConversation $record): string => '¡Listo, '.self::customerFirstName($record).'! Su pedido ya está preparado en Farmadoc {sucursal}. Puede pasar a retirarlo cuando guste. ¡Gracias por preferirnos!')
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(function (WhatsappConversation $record, array $data, Action $action): void {
                $user = self::user();
                $branch = Branch::query()->find((int) ($data['branch_id'] ?? 0));

                if (! $user instanceof User || ! $branch instanceof Branch) {
                    $action->halt();
                }

                try {
                    app(WhatsappConversationConverter::class)->toPickupHeldSale($record, $branch, $user);
                } catch (ValidationException $exception) {
                    self::fail($exception, $action);
                }

                self::notifyCustomer($record, $user, (string) ($data['customer_message'] ?? ''), ['{sucursal}' => (string) $branch->name]);

                Notification::make()->title('Pedido enviado a la caja')->body('Quedó en «Ventas en pausa» de '.$branch->name.'.')->success()->send();
                $record->refresh();
            });
    }

    public static function convertToDelivery(): Action
    {
        return Action::make('convertToDelivery')
            ->label('Crear pedido con delivery')
            ->icon(Heroicon::Truck)
            ->color('primary')
            ->visible(fn (WhatsappConversation $record): bool => $record->isOpen())
            ->modalHeading('Crear pedido con delivery')
            ->modalDescription('Se crea un pedido en «Órdenes» con la dirección del cliente, igual que los pedidos de la tienda web.')
            ->modalSubmitActionLabel('Crear pedido')
            ->schema([
                self::branchSelect(),
                Textarea::make('customer_message')
                    ->label('Mensaje para el cliente (opcional)')
                    ->helperText('{pedido} se reemplaza por el número de pedido. Déjelo vacío para no enviar nada.')
                    ->default(fn (WhatsappConversation $record): string => '¡Listo, '.self::customerFirstName($record).'! Su pedido {pedido} ya está en proceso para enviarlo a su dirección. Le avisaremos cuando vaya en camino. ¡Gracias por preferirnos!')
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(function (WhatsappConversation $record, array $data, Action $action): void {
                $user = self::user();
                $branch = Branch::query()->find((int) ($data['branch_id'] ?? 0));

                if (! $user instanceof User || ! $branch instanceof Branch) {
                    $action->halt();
                }

                try {
                    $order = app(WhatsappConversationConverter::class)->toDeliveryOrder($record, $branch, $user);
                } catch (ValidationException $exception) {
                    self::fail($exception, $action);
                }

                self::notifyCustomer($record, $user, (string) ($data['customer_message'] ?? ''), ['{pedido}' => (string) $order->order_number]);

                Notification::make()->title('Pedido '.$order->order_number.' creado')->success()->send();
                $record->refresh();
            });
    }

    public static function registerCustomer(): Action
    {
        return Action::make('registerCustomer')
            ->label(fn (WhatsappConversation $record): string => $record->contact?->client_id !== null ? 'Corregir' : 'Registrar')
            ->icon(Heroicon::Identification)
            ->link()
            ->visible(fn (WhatsappConversation $record): bool => $record->isOpen())
            ->modalHeading('Datos del cliente')
            ->modalDescription('Se busca por cédula en los clientes de la caja; si no existe, se crea.')
            ->fillForm(fn (WhatsappConversation $record): array => [
                'full_name' => $record->contact?->full_name ?? $record->contact?->profile_name,
                'document_type' => $record->contact?->document_type ?? 'V',
                'document_number' => $record->contact?->document_number,
            ])
            ->schema([
                TextInput::make('full_name')->label('Nombre y apellido')->required()->minLength(5)->maxLength(160),
                Grid::make(3)->schema([
                    Select::make('document_type')->label('Tipo')->options(['V' => 'V', 'E' => 'E', 'J' => 'J', 'G' => 'G', 'P' => 'P'])->required()->native(false),
                    TextInput::make('document_number')->label('Cédula')->required()->regex('/^\d{6,9}$/')->validationMessages(['regex' => 'Solo números, entre 6 y 9 dígitos.'])->columnSpan(2),
                ]),
            ])
            ->action(function (WhatsappConversation $record, array $data): void {
                $result = app(WhatsappCustomerRegistrar::class)->register($record, (string) $data['full_name'], (string) $data['document_type'], (string) $data['document_number']);

                Notification::make()
                    ->title($result['status'] === 'mismatch' ? 'La cédula ya existe con otro nombre' : 'Cliente listo en la caja')
                    ->body($result['message'])
                    ->status($result['status'] === 'mismatch' ? 'warning' : 'success')
                    ->send();

                $record->refresh();
            });
    }

    public static function editItems(): Action
    {
        return Action::make('editItems')
            ->label('Editar')
            ->icon(Heroicon::PencilSquare)
            ->link()
            ->visible(fn (WhatsappConversation $record): bool => $record->isOpen())
            ->modalHeading('Productos del pedido')
            ->modalWidth('3xl')
            ->fillForm(fn (WhatsappConversation $record): array => [
                'items' => $record->items->map(fn (WhatsappConversationItem $item): array => [
                    'product_id' => $item->product_id,
                    'requested_name' => $item->requested_name,
                    'quantity' => (float) $item->quantity,
                    'notes' => $item->notes,
                ])->all(),
            ])
            ->schema([
                Repeater::make('items')
                    ->hiddenLabel()
                    ->addActionLabel('Agregar producto')
                    ->defaultItems(0)
                    ->columns(6)
                    ->schema([
                        Select::make('product_id')
                            ->label('Producto del catálogo')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Product::query()
                                ->where('is_active', true)
                                ->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('barcode', $search))
                                ->orderBy('name')
                                ->limit(30)
                                ->pluck('name', 'id')
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => Product::query()->whereKey($value)->value('name'))
                            ->columnSpan(3),
                        TextInput::make('requested_name')->label('Lo que pidió')->required()->maxLength(255)->columnSpan(2),
                        TextInput::make('quantity')->label('Cant.')->numeric()->minValue(0.001)->default(1)->required()->columnSpan(1),
                        TextInput::make('notes')->label('Nota')->maxLength(500)->columnSpanFull(),
                    ]),
            ])
            ->action(self::run(fn (WhatsappConversation $record, User $user, array $data) => app(WhatsappConversationWorkflow::class)->syncItems($record, array_values((array) ($data['items'] ?? []))), 'Productos actualizados'));
    }

    public static function editDelivery(): Action
    {
        return Action::make('editDelivery')
            ->label('Editar')
            ->icon(Heroicon::PencilSquare)
            ->link()
            ->visible(fn (WhatsappConversation $record): bool => $record->isOpen())
            ->modalHeading('Entrega y pago')
            ->fillForm(fn (WhatsappConversation $record): array => [
                'fulfillment_type' => $record->fulfillment_type?->value,
                'delivery_address' => $record->delivery_address,
                'delivery_reference' => $record->delivery_reference,
                'delivery_recipient_name' => $record->delivery_recipient_name,
                'delivery_recipient_phone' => $record->delivery_recipient_phone,
                'payment_method_note' => $record->payment_method_note,
            ])
            ->schema([
                Select::make('fulfillment_type')->label('Entrega')->options(WhatsappFulfillmentType::options())->native(false),
                Textarea::make('delivery_address')->label('Dirección')->rows(2)->maxLength(500),
                TextInput::make('delivery_reference')->label('Punto de referencia')->maxLength(255),
                Grid::make(2)->schema([
                    TextInput::make('delivery_recipient_name')->label('Quién recibe')->maxLength(160),
                    TextInput::make('delivery_recipient_phone')->label('Teléfono de quien recibe')->maxLength(30),
                ]),
                TextInput::make('payment_method_note')->label('Forma de pago')->maxLength(255),
            ])
            ->action(self::run(fn (WhatsappConversation $record, User $user, array $data) => app(WhatsappConversationWorkflow::class)->updateDelivery($record, $data), 'Datos de entrega actualizados'));
    }

    public static function validatePrescriptions(): Action
    {
        return Action::make('validatePrescriptions')
            ->label('Marcar como validados')
            ->icon(Heroicon::CheckBadge)
            ->link()
            ->visible(fn (WhatsappConversation $record): bool => $record->prescriptionReadings()->whereNull('validated_at')->exists())
            ->requiresConfirmation()
            ->modalDescription('Confirma que revisó personalmente los récipes de esta conversación.')
            ->action(function (WhatsappConversation $record): void {
                $user = self::user();

                if (! $user instanceof User) {
                    return;
                }

                $workflow = app(WhatsappConversationWorkflow::class);
                $record->prescriptionReadings()->whereNull('validated_at')->get()->each(fn ($reading) => $workflow->validatePrescription($reading, $user));

                Notification::make()->title('Récipes validados')->success()->send();
                $record->refresh();
            });
    }

    private static function branchSelect(): Select
    {
        return Select::make('branch_id')
            ->label('Sucursal')
            ->options(fn (): array => Branch::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
            ->default(fn (WhatsappConversation $record): ?int => $record->branch_id)
            ->searchable()
            ->required();
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private static function notifyCustomer(WhatsappConversation $record, User $user, string $message, array $replacements): void
    {
        $message = trim(strtr($message, $replacements));

        if ($message === '') {
            return;
        }

        app(WhatsappOutboundMessenger::class)->queue($record->loadMissing('contact'), $message, WhatsappMessageAuthor::Analyst, $user);
    }

    private static function customerFirstName(WhatsappConversation $record): string
    {
        $name = (string) ($record->contact?->full_name ?: $record->contact?->profile_name ?: '');
        $first = trim((string) strtok($name, ' '));

        return $first !== '' ? $first : 'gracias por esperar';
    }

    /**
     * @param  Closure(WhatsappConversation, User, array<string, mixed>): mixed  $operation
     */
    private static function run(Closure $operation, string $successTitle): Closure
    {
        return function (WhatsappConversation $record, array $data, Action $action) use ($operation, $successTitle): void {
            $user = self::user();

            if (! $user instanceof User) {
                $action->halt();
            }

            try {
                $operation($record, $user, $data);
            } catch (ValidationException $exception) {
                self::fail($exception, $action);
            }

            Notification::make()->title($successTitle)->success()->send();
            $record->refresh();
        };
    }

    private static function fail(ValidationException $exception, Action $action): never
    {
        Notification::make()
            ->title('No se pudo completar')
            ->body(collect($exception->errors())->flatten()->implode(' '))
            ->danger()
            ->persistent()
            ->send();

        $action->halt();
    }

    private static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
