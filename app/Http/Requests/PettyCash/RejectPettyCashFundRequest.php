<?php

namespace App\Http\Requests\PettyCash;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RejectPettyCashFundRequest extends FormRequest
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
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
