<?php

namespace App\Http\Requests\FiscalAgent;

use Illuminate\Foundation\Http\FormRequest;

class FiscalAgentHeartbeatRequest extends FormRequest
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
            'agent_version' => ['nullable', 'string', 'max:40'],
            'last_fiscal_number' => ['nullable', 'string', 'max:20'],
            'last_z_number' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.array' => 'El estado de la máquina fiscal debe enviarse como objeto JSON.',
        ];
    }
}
