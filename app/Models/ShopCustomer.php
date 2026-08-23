<?php

namespace App\Models;

use Database\Factories\ShopCustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Fillable([
    'first_name',
    'last_name',
    'document_type',
    'document_number',
    'phone',
    'email',
    'password',
    'google_id',
    'google_avatar',
])]
#[Hidden(['password', 'remember_token'])]
class ShopCustomer extends Authenticatable
{
    /** @use HasFactory<ShopCustomerFactory> */
    use HasFactory;

    protected $table = 'pwa_customers';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public static function current(): ?self
    {
        $customer = Auth::guard('shop')->user();

        return $customer instanceof self ? $customer : null;
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function firstName(): string
    {
        return trim((string) $this->first_name);
    }

    public function initials(): string
    {
        return Str::of($this->fullName())
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function usesGoogle(): bool
    {
        return filled($this->google_id);
    }

    public function hasPassword(): bool
    {
        return filled($this->password);
    }

    public function checkoutDocumentType(): string
    {
        return $this->document_type === 'E' ? 'CE' : 'CC';
    }

    /**
     * @return HasMany<ShopAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(ShopAddress::class, 'pwa_customer_id')
            ->orderByDesc('is_primary')
            ->orderByDesc('id');
    }

    /**
     * @return HasOne<ShopAddress, $this>
     */
    public function primaryAddress(): HasOne
    {
        return $this->hasOne(ShopAddress::class, 'pwa_customer_id')
            ->where('is_primary', true);
    }
}
