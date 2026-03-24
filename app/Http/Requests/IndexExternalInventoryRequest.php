<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexExternalInventoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'partner_company' => [
                'required',
                'string',
                'max:255',
                Rule::exists('partner_companies', 'code'),
            ],
            'active_ingredient' => ['required', 'string', 'min:2', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'partner_company.required' => 'Debe indicar el código de compañía aliada (parámetro partner_company).',
            'partner_company.exists' => 'La compañía aliada no existe o el código no coincide con un registro en el sistema.',
            'active_ingredient.required' => 'Debe indicar el principio activo (parámetro active_ingredient).',
            'active_ingredient.min' => 'El principio activo debe tener al menos :min caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'partner_company' => 'código de compañía aliada',
            'active_ingredient' => 'principio activo',
        ];
    }
}
