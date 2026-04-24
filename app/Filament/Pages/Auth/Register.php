<?php

namespace App\Filament\Pages\Auth;

use App\Models\Branch;
use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class Register extends BaseRegister
{
    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void {}

    protected function isRegisterRateLimited(string $email): bool
    {
        return false;
    }

    public function getSloganAfterLogo(): string|Htmlable|null
    {
        return new HtmlString(
            '<span class="fi-login-slogan">Nuestra gente, su bienestar.</span>'
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getBranchFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function getBranchFormComponent(): Component
    {
        return Select::make('branch_id')
            ->label('Sucursal')
            ->placeholder('Seleccione la sucursal del usuario')
            ->options(fn (): array => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all())
            ->required()
            ->searchable()
            ->preload()
            ->native(false)
            ->helperText('El usuario quedará asociado a esta sucursal.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(array $data): Model
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'branch_id' => (int) $data['branch_id'],
        ]);
    }
}
