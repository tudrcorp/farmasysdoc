<?php

namespace App\Http\Requests\WhatsappAgent;

use Illuminate\Foundation\Http\FormRequest;

class SearchWhatsappProductsRequest extends FormRequest
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
            'q' => ['required', 'string', 'min:2', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.required' => 'Indique qué producto buscar (parámetro q).',
            'q.min' => 'La búsqueda debe tener al menos 2 caracteres.',
        ];
    }
}
