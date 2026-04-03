<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexExternalBranchesRequest extends FormRequest
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
            'partner_company' => [
                'required',
                'string',
                'max:255',
                Rule::exists('partner_companies', 'code'),
            ],
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'partner_company' => 'código de compañía aliada',
        ];
    }
}
