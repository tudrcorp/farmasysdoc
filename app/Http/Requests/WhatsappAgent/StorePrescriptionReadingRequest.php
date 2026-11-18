<?php

namespace App\Http\Requests\WhatsappAgent;

use App\Enums\PrescriptionLegibility;
use App\Enums\WhatsappImageKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrescriptionReadingRequest extends FormRequest
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
            'whatsapp_message_id' => ['nullable', 'integer', Rule::exists('whatsapp_messages', 'id')],
            'is_prescription' => ['sometimes', 'boolean'],
            'image_kind' => ['nullable', Rule::enum(WhatsappImageKind::class)],
            'description' => ['nullable', 'string', 'max:500'],
            'payment_data' => ['nullable', 'array'],
            'legibility' => ['required', Rule::enum(PrescriptionLegibility::class)],
            'overall_confidence' => ['nullable', 'numeric', 'between:0,1'],
            'doctor_name' => ['nullable', 'string', 'max:160'],
            'prescription_date' => ['nullable', 'string', 'max:40'],
            'issues' => ['nullable', 'array'],
            'issues.*' => ['string', 'max:160'],
            'items' => ['present', 'array', 'max:20'],
            'items.*.medication_name' => ['required', 'string', 'max:255'],
            'items.*.concentration' => ['nullable', 'string', 'max:100'],
            'items.*.presentation' => ['nullable', 'string', 'max:150'],
            'items.*.quantity_text' => ['nullable', 'string', 'max:100'],
            'items.*.dosage_instructions' => ['nullable', 'string', 'max:500'],
            'items.*.confidence' => ['nullable', 'numeric', 'between:0,1'],
            'items.*.is_controlled_substance' => ['nullable', 'boolean'],
            'raw_response' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'legibility.required' => 'Indique la legibilidad: legible, dudosa o ilegible.',
            'legibility.enum' => 'La legibilidad debe ser legible, dudosa o ilegible.',
            'items.present' => 'Envíe la lista de medicamentos (puede ser vacía).',
            'items.*.medication_name.required' => 'Cada medicamento debe tener nombre.',
        ];
    }
}
