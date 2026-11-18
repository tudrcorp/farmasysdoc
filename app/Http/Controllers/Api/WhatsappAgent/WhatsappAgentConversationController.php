<?php

namespace App\Http\Controllers\Api\WhatsappAgent;

use App\Enums\WhatsappFulfillmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsappAgent\ConfirmWhatsappOrderRequest;
use App\Http\Requests\WhatsappAgent\EscalateWhatsappConversationRequest;
use App\Http\Requests\WhatsappAgent\RegisterWhatsappCustomerRequest;
use App\Http\Requests\WhatsappAgent\SendWhatsappAgentReplyRequest;
use App\Http\Requests\WhatsappAgent\UpdateWhatsappConversationStateRequest;
use App\Models\WhatsappConversation;
use App\Services\WhatsappAgent\WhatsappConversationContextBuilder;
use App\Services\WhatsappAgent\WhatsappConversationEscalator;
use App\Services\WhatsappAgent\WhatsappCustomerRegistrar;
use App\Services\WhatsappAgent\WhatsappOrderConfirmer;
use App\Services\WhatsappAgent\WhatsappOutboundMessenger;
use App\Support\WhatsappAgent\WhatsappAnalystSchedule;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Herramientas del asistente (n8n) sobre una conversación.
 */
class WhatsappAgentConversationController extends Controller
{
    public function context(WhatsappConversation $conversation, WhatsappConversationContextBuilder $builder): JsonResponse
    {
        return response()->json($builder->build($conversation));
    }

    public function reply(SendWhatsappAgentReplyRequest $request, WhatsappConversation $conversation, WhatsappOutboundMessenger $messenger): JsonResponse
    {
        if (! $conversation->isHandledByAgent() || ! $conversation->isOpen()) {
            return response()->json([
                'sent' => false,
                'message' => 'Un analista está atendiendo esta conversación (o ya se cerró). No envíes mensajes al cliente.',
            ], Response::HTTP_CONFLICT);
        }

        $message = $messenger->sendAsBubbles($conversation->loadMissing('contact'), (string) $request->validated('body'));

        return response()->json([
            'sent' => ! $message->delivery_failed,
            'message_id' => (int) $message->getKey(),
            'error' => $message->meta['error'] ?? null,
        ]);
    }

    public function updateState(UpdateWhatsappConversationStateRequest $request, WhatsappConversation $conversation): JsonResponse
    {
        $data = $request->validated();

        $statePatch = array_filter([
            'stage' => $data['stage'] ?? null,
            'pending_question' => $data['pending_question'] ?? null,
            'notes' => $data['notes'] ?? null,
            'preferred_branch' => $data['preferred_branch'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);

        $attributes = array_filter([
            'summary' => $data['summary'] ?? null,
            'fulfillment_type' => isset($data['fulfillment_type']) ? WhatsappFulfillmentType::from($data['fulfillment_type']) : null,
            'delivery_address' => $data['delivery_address'] ?? null,
            'delivery_reference' => $data['delivery_reference'] ?? null,
            'delivery_latitude' => $data['delivery_latitude'] ?? null,
            'delivery_longitude' => $data['delivery_longitude'] ?? null,
            'delivery_recipient_name' => $data['delivery_recipient_name'] ?? null,
            'delivery_recipient_phone' => $data['delivery_recipient_phone'] ?? null,
            'payment_method_note' => $data['payment_method'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);

        if (array_key_exists('pending_question', $data) && $data['pending_question'] === null) {
            $statePatch['pending_question'] = null;
        }

        $attributes['state'] = [...($conversation->state ?? []), ...$statePatch];

        $conversation->forceFill($attributes)->save();

        return response()->json([
            'saved' => true,
            'state' => $conversation->state,
        ]);
    }

    public function registerCustomer(RegisterWhatsappCustomerRequest $request, WhatsappConversation $conversation, WhatsappCustomerRegistrar $registrar): JsonResponse
    {
        $result = $registrar->register(
            $conversation->loadMissing('contact'),
            (string) $request->validated('full_name'),
            (string) $request->validated('document_type'),
            (string) $request->validated('document_number'),
        );

        return response()->json($result);
    }

    public function confirmOrder(ConfirmWhatsappOrderRequest $request, WhatsappConversation $conversation, WhatsappOrderConfirmer $confirmer): JsonResponse
    {
        $data = $request->validated();

        $result = $confirmer->confirm(
            $conversation,
            WhatsappFulfillmentType::from((string) $data['fulfillment_type']),
            isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            $data['payment_method'] ?? null,
            (string) $data['summary'],
        );

        $schedule = WhatsappAnalystSchedule::current();

        return response()->json([
            'confirmed' => true,
            ...$result,
            'analysts_within_hours' => $schedule->isWithinHours(),
            'next_shift_start' => $schedule->nextShiftStart()->locale('es')->isoFormat('dddd D [a las] h:mm a'),
            'instruction' => 'Dile al cliente que su pedido quedó registrado en la sucursal '.$result['branch']
                .' y que un asesor verifica su pago y le confirma'
                .($result['to_source'] !== [] ? '; los productos por conseguir los gestiona el equipo y le avisa' : '').'.',
        ]);
    }

    public function escalate(EscalateWhatsappConversationRequest $request, WhatsappConversation $conversation, WhatsappConversationEscalator $escalator): JsonResponse
    {
        $conversation = $escalator->escalate(
            $conversation->loadMissing('contact'),
            (string) $request->validated('reason'),
            (string) $request->validated('summary'),
        );

        $schedule = WhatsappAnalystSchedule::current();

        return response()->json([
            'escalated' => true,
            'status' => $conversation->status?->value,
            'analysts_within_hours' => $schedule->isWithinHours(),
            'next_shift_start' => $schedule->nextShiftStart()->locale('es')->isoFormat('dddd D [a las] h:mm a'),
        ]);
    }
}
