<?php

namespace App\Http\Requests\WhatsappAgent;

use App\Enums\WhatsappFulfillmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmWhatsappOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ((int) $this->input('branch_id', 0) <= 0) {
            $this->merge(['branch_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fulfillment_type' => ['required', Rule::enum(WhatsappFulfillmentType::class)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fulfillment_type.required' => 'Indica si es «delivery» o «retiro».',
            'fulfillment_type.enum' => 'El tipo de entrega debe ser «delivery» o «retiro».',
            'branch_id.exists' => 'Esa sucursal no existe o no está activa. Usa un branch_id de la lista de sucursales del contexto.',
            'summary.required' => 'Escribe el resumen del pedido para el equipo.',
        ];
    }
}
