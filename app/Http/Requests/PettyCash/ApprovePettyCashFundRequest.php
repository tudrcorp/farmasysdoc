<?php

namespace App\Http\Requests\PettyCash;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ApprovePettyCashFundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isAdministrator();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_reference' => ['required', 'string', 'max:255'],
            'payment_proof' => [
                'required',
                'file',
                'max:5120',
                'mimes:jpg,jpeg,png,webp,pdf',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_reference.required' => 'Indique la referencia o el número de comprobante.',
            'payment_proof.required' => 'Adjunte la captura del pago.',
            'payment_proof.mimes' => 'El comprobante debe ser JPG, PNG, WebP o PDF.',
            'payment_proof.max' => 'El comprobante no puede superar 5 MB.',
        ];
    }
}
