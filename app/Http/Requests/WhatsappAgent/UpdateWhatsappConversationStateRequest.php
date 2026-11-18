<?php

namespace App\Http\Requests\WhatsappAgent;

use App\Enums\WhatsappFulfillmentType;
use App\Models\WhatsappConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWhatsappConversationStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage' => ['nullable', 'string', Rule::in(array_keys(WhatsappConversation::STAGES))],
            'pending_question' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'preferred_branch' => ['nullable', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'fulfillment_type' => ['nullable', 'string', Rule::enum(WhatsappFulfillmentType::class)],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'delivery_reference' => ['nullable', 'string', 'max:255'],
            'delivery_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'delivery_recipient_name' => ['nullable', 'string', 'max:160'],
            'delivery_recipient_phone' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stage.in' => 'Etapa desconocida. Use: '.implode(', ', array_keys(WhatsappConversation::STAGES)).'.',
            'fulfillment_type.enum' => 'El tipo de entrega debe ser «delivery» o «retiro».',
        ];
    }
}
