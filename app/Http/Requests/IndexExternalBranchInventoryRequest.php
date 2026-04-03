<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexExternalBranchInventoryRequest extends FormRequest
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
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('is_active', true),
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
            'branch_id.required' => 'Debe indicar la sucursal (parámetro branch_id).',
            'branch_id.exists' => 'La sucursal no existe, no está activa o el id no es válido.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'partner_company' => 'código de compañía aliada',
            'branch_id' => 'sucursal',
        ];
    }
}
