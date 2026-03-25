<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('is_active', true),
            ],
            'password' => $this->passwordRules(),
        ], [
            'branch_id.required' => 'Debe seleccionar la sucursal a la que pertenece el usuario.',
            'branch_id.exists' => 'La sucursal seleccionada no es válida o está inactiva.',
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'branch_id' => (int) $input['branch_id'],
            'password' => $input['password'],
        ]);
    }
}
