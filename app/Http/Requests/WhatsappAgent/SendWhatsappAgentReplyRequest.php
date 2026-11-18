<?php

namespace App\Http\Requests\WhatsappAgent;

use Illuminate\Foundation\Http\FormRequest;

class SendWhatsappAgentReplyRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'El mensaje para el cliente está vacío.',
            'body.max' => 'El mensaje no puede superar los 4000 caracteres.',
        ];
    }
}
