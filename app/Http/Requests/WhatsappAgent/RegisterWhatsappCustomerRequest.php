<?php

namespace App\Http\Requests\WhatsappAgent;

use Illuminate\Foundation\Http\FormRequest;

class RegisterWhatsappCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $number = (string) $this->input('document_number', '');
        $letter = (string) $this->input('document_type', '');

        if ($letter === '' && preg_match('/^\s*([VEJGPvejgp])\s*[-.]?\s*/', $number, $matches) === 1) {
            $letter = $matches[1];
        }

        $this->merge([
            'document_type' => mb_strtoupper(trim($letter)) ?: 'V',
            'document_number' => (string) preg_replace('/\D+/', '', $number),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:5', 'max:160'],
            'document_type' => ['required', 'string', 'in:V,E,J,G,P'],
            'document_number' => ['required', 'digits_between:6,9'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Falta el nombre y apellido del cliente.',
            'full_name.min' => 'Pida el nombre y el apellido completos.',
            'document_type.in' => 'El tipo de cédula debe ser V o E (o J, G, P).',
            'document_number.required' => 'Falta el número de cédula.',
            'document_number.digits_between' => 'La cédula debe tener entre 6 y 9 dígitos. Confírmela con el cliente.',
        ];
    }
}
