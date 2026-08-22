<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorefrontCheckoutRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'min:1', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:1', 'max:99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Agrega productos al carrito para continuar.',
            'items.min' => 'Agrega productos al carrito para continuar.',
            'items.*.product_id.exists' => 'Uno de los productos ya no está disponible.',
        ];
    }

    /**
     * @return array<int, float>
     */
    public function quantitiesByProductId(): array
    {
        $quantities = [];

        foreach ($this->validated('items') as $item) {
            $productId = (int) $item['product_id'];
            $quantities[$productId] = ($quantities[$productId] ?? 0) + (float) $item['quantity'];
        }

        return $quantities;
    }
}
