<?php

namespace App\Http\Requests\WhatsappAgent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWhatsappCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * La IA envía 0 cuando el producto no está en el catálogo.
     */
    protected function prepareForValidation(): void
    {
        if ((int) $this->input('product_id', 0) <= 0) {
            $this->merge(['product_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'requested_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'Ese producto no existe. Use el product_id que devolvió buscar_producto.',
            'requested_name.required' => 'Indique qué pidió el cliente.',
            'quantity.required' => 'Indique la cantidad.',
            'quantity.min' => 'La cantidad debe ser mayor que cero.',
        ];
    }
}
