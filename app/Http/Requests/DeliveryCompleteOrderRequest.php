<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeliveryCompleteOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isDeliveryUser();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'evidence' => ['required', 'file', 'image', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'evidence.required' => __('Debes adjuntar una foto de evidencia de la entrega.'),
            'evidence.image' => __('El archivo debe ser una imagen (JPG, PNG o WebP).'),
            'evidence.max' => __('La imagen no puede superar 5 MB.'),
        ];
    }
}
