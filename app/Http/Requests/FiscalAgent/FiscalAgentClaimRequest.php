<?php

namespace App\Http\Requests\FiscalAgent;

use Illuminate\Foundation\Http\FormRequest;

class FiscalAgentClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'wait' => ['nullable', 'integer', 'min:0', 'max:60'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'wait.integer' => 'El tiempo de espera debe ser un número entero de segundos.',
            'wait.min' => 'El tiempo de espera no puede ser negativo.',
            'wait.max' => 'El tiempo de espera no puede superar 60 segundos.',
            'capabilities.array' => 'capabilities debe ser una lista.',
        ];
    }
}
