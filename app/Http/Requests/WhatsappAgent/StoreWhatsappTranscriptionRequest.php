<?php

namespace App\Http\Requests\WhatsappAgent;

use Illuminate\Foundation\Http\FormRequest;

class StoreWhatsappTranscriptionRequest extends FormRequest
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
            'transcription' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transcription.required' => 'La transcripción de la nota de voz está vacía.',
        ];
    }
}
