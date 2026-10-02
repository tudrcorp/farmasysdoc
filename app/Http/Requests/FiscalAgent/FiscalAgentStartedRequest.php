<?php

namespace App\Http\Requests\FiscalAgent;

use Illuminate\Foundation\Http\FormRequest;

class FiscalAgentStartedRequest extends FormRequest
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
            'counter_before' => ['present', 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'counter_before.present' => 'Debe enviar el último número fiscal leído antes de imprimir (counter_before).',
            'counter_before.max' => 'El contador fiscal no puede superar 20 caracteres.',
        ];
    }
}
