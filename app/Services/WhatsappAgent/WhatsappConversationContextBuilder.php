<?php

namespace App\Services\WhatsappAgent;

use App\Enums\WhatsappMessageAuthor;
use App\Enums\WhatsappMessageType;
use App\Models\Branch;
use App\Models\Order;
use App\Models\PrescriptionReading;
use App\Models\PrescriptionReadingItem;
use App\Models\WhatsappAgentSetting;
use App\Models\WhatsappConversation;
use App\Models\WhatsappConversationItem;
use App\Models\WhatsappMessage;
use App\Support\WhatsappAgent\WhatsappAgentUrls;
use App\Support\WhatsappAgent\WhatsappAnalystSchedule;
use Carbon\CarbonImmutable;

/**
 * Todo lo que el asistente necesita en cada turno para no perder el hilo: instrucciones, estado,
 * resumen, datos del cliente, carrito, récipes, últimos mensajes y los mensajes nuevos que debe responder.
 */
final class WhatsappConversationContextBuilder
{
    public const HISTORY_LIMIT = 30;

    /**
     * @return array<string, mixed>
     */
    public function build(WhatsappConversation $conversation): array
    {
        $conversation->loadMissing(['contact.client', 'items.product', 'prescriptionReadings.items', 'branch']);
        $setting = WhatsappAgentSetting::current();
        $schedule = new WhatsappAnalystSchedule($setting);
        $now = CarbonImmutable::now('America/Caracas');
        $contact = $conversation->contact;

        $history = $conversation->messages()
            ->with('prescriptionReading.items')
            ->reorder('id', 'desc')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->values();

        $lastAnsweredId = (int) ($conversation->messages()
            ->whereIn('author', [WhatsappMessageAuthor::Agent->value, WhatsappMessageAuthor::Analyst->value])
            ->max('id') ?? 0);

        return [
            'conversation_id' => (int) $conversation->getKey(),
            'agent' => [
                'name' => $setting->agentDisplayName(),
                'system_prompt' => $setting->effectiveSystemPrompt(),
                'suggested_products' => $setting->suggested_products_notes,
                'prescription_confidence_threshold' => (float) $setting->prescription_confidence_threshold,
            ],
            'now' => [
                'datetime' => $now->toIso8601String(),
                'human' => $now->locale('es')->isoFormat('dddd D [de] MMMM, h:mm a'),
            ],
            'analysts' => [
                'within_hours' => $schedule->isWithinHours(),
                'schedule' => $schedule->describe(),
                'next_shift_start' => $schedule->nextShiftStart()->locale('es')->isoFormat('dddd D [a las] h:mm a'),
            ],
            'customer' => [
                'phone' => $contact?->localPhone(),
                'whatsapp_profile_name' => $contact?->profile_name,
                'full_name' => $contact?->full_name,
                'document' => $contact?->formattedDocument(),
                'registered_in_pos' => $contact?->client_id !== null,
                'data_mismatch' => (bool) $contact?->client_data_mismatch,
                'returning_customer' => $contact !== null && $contact->conversations()->whereKeyNot($conversation->getKey())->exists(),
                'recent_orders' => $this->recentOrders($contact?->client_id),
            ],
            'conversation' => [
                'status' => $conversation->status?->value,
                'status_label' => $conversation->status?->label(),
                'handled_by' => $conversation->handled_by?->value,
                'state' => $conversation->state ?? [],
                'summary' => $conversation->summary,
                'escalation_reason' => $conversation->escalation_reason,
                'fulfillment_type' => $conversation->fulfillment_type?->value,
                'delivery' => [
                    'address' => $conversation->delivery_address,
                    'reference' => $conversation->delivery_reference,
                    'latitude' => $conversation->delivery_latitude,
                    'longitude' => $conversation->delivery_longitude,
                    'recipient_name' => $conversation->delivery_recipient_name,
                    'recipient_phone' => $conversation->delivery_recipient_phone,
                ],
                'payment_method' => $conversation->payment_method_note,
                'branch' => $conversation->branch?->name,
            ],
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'city', 'address'])
                ->map(fn (Branch $branch): array => [
                    'branch_id' => (int) $branch->getKey(),
                    'name' => $branch->name,
                    'city' => $branch->city,
                    'address' => $branch->address,
                ])
                ->all(),
            'cart' => $conversation->items
                ->map(fn (WhatsappConversationItem $item): array => [
                    'item_id' => (int) $item->getKey(),
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'requested_name' => $item->requested_name,
                    'quantity' => (float) $item->quantity,
                    'notes' => $item->notes,
                ])
                ->all(),
            'prescription_readings' => $conversation->prescriptionReadings
                ->map(fn (PrescriptionReading $reading): array => [
                    'reading_id' => (int) $reading->getKey(),
                    'message_id' => $reading->whatsapp_message_id,
                    'image_kind' => $reading->image_kind?->value,
                    'image_kind_label' => $reading->image_kind?->label(),
                    'description' => $reading->description,
                    'payment' => $reading->payment_data,
                    'doctor' => $reading->doctor_name,
                    'issues' => $reading->issues,
                    'legibility' => $reading->legibility?->value,
                    'requires_analyst' => $reading->requires_analyst,
                    'has_controlled_substance' => $reading->has_controlled_substance,
                    'validated_by_analyst' => $reading->validated_at !== null,
                    'items' => $reading->items->map(fn (PrescriptionReadingItem $item): array => [
                        'medication' => $item->medication_name,
                        'concentration' => $item->concentration,
                        'presentation' => $item->presentation,
                        'quantity' => $item->quantity_text,
                        'instructions' => $item->dosage_instructions,
                        'confidence' => $item->confidence !== null ? (float) $item->confidence : null,
                        'matched_product_id' => $item->product_id,
                    ])->all(),
                ])
                ->all(),
            'history' => $history->map(fn (WhatsappMessage $message): array => $this->presentMessage($message))->all(),
            'new_messages' => $history
                ->filter(fn (WhatsappMessage $message): bool => $message->author === WhatsappMessageAuthor::Customer
                    && (int) $message->getKey() > $lastAnsweredId)
                ->map(fn (WhatsappMessage $message): array => $this->presentMessage($message))
                ->values()
                ->all(),
            'pending_media' => $this->pendingMedia($conversation),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMessage(WhatsappMessage $message): array
    {
        return [
            'message_id' => (int) $message->getKey(),
            'author' => $message->author?->value,
            'type' => $message->type?->value,
            'text' => $message->textForAgent(),
            'image_reading' => $message->type === WhatsappMessageType::Image
                ? ($message->prescriptionReading?->summaryLine() ?? 'Foto aún sin leer')
                : null,
            'quoted' => $message->meta['quoted_body'] ?? null,
            'at' => $message->created_at?->setTimezone('America/Caracas')->format('Y-m-d H:i'),
        ];
    }

    /**
     * Fotos sin leer y notas de voz sin transcribir: n8n las procesa antes de responder.
     *
     * @return list<array<string, mixed>>
     */
    private function pendingMedia(WhatsappConversation $conversation): array
    {
        return $conversation->messages()
            ->where('author', WhatsappMessageAuthor::Customer->value)
            ->whereIn('type', [WhatsappMessageType::Image->value, WhatsappMessageType::Audio->value])
            ->whereNotNull('media_path')
            ->where(function ($query): void {
                $query->where(function ($image): void {
                    $image->where('type', WhatsappMessageType::Image->value)->doesntHave('prescriptionReading');
                })->orWhere(function ($audio): void {
                    $audio->where('type', WhatsappMessageType::Audio->value)->whereNull('transcription');
                });
            })
            ->get()
            ->map(fn (WhatsappMessage $message): array => [
                'message_id' => (int) $message->getKey(),
                'type' => $message->type?->value,
                'mime' => $message->media_mime,
                'caption' => $message->body,
                'download_url' => WhatsappAgentUrls::api('messages/'.$message->getKey().'/media'),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentOrders(?int $clientId): array
    {
        if ($clientId === null) {
            return [];
        }

        return Order::query()
            ->where('client_id', $clientId)
            ->with('items:id,order_id,product_name_snapshot,quantity')
            ->latest('id')
            ->limit(3)
            ->get()
            ->map(fn (Order $order): array => [
                'date' => $order->created_at?->format('Y-m-d'),
                'products' => $order->items
                    ->map(fn ($item): string => rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.').' × '.$item->product_name_snapshot)
                    ->all(),
            ])
            ->all();
    }
}
