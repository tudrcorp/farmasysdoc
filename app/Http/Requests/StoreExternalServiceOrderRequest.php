<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|Rule>>
     */
    public function rules(): array
    {
        return [
            'partner_company' => [
                'required',
                'string',
                'max:255',
                Rule::exists('partner_companies', 'code'),
            ],
            'status' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'string', 'max:255'],
            'service_type' => ['required', 'string', 'max:255'],
            'external_reference' => ['required', 'string', 'max:255'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_document' => ['required', 'string', 'max:255'],
            'patient_phone' => ['required', 'string', 'max:40', 'regex:/^[0-9]+$/'],
            'patient_email' => ['required', 'string', 'email', 'max:255'],
            'diagnosis' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:500'],
            'items.*.indicacion' => ['required', 'string', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'partner_company.exists' => 'La compañía aliada no existe o el código no coincide con un registro activo.',
            'patient_phone.regex' => 'El teléfono del paciente solo puede contener números.',
            'items.required' => 'Debe enviar al menos un medicamento con su indicación.',
            'items.min' => 'Debe enviar al menos un medicamento con su indicación.',
            'items.*.name.required' => 'Cada ítem debe incluir el nombre del medicamento.',
            'items.*.indicacion.required' => 'Cada ítem debe incluir la indicación del medicamento.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'partner_company' => 'código de compañía aliada',
            'external_reference' => 'referencia externa',
            'patient_name' => 'nombre del paciente',
            'patient_document' => 'documento del paciente',
            'patient_phone' => 'teléfono del paciente',
            'patient_email' => 'correo del paciente',
            'items.*.name' => 'nombre del medicamento',
            'items.*.indicacion' => 'indicación',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('patient_phone') && is_string($this->input('patient_phone'))) {
            $this->merge([
                'patient_phone' => preg_replace('/[^0-9]/', '', $this->input('patient_phone')),
            ]);
        }
    }
}
