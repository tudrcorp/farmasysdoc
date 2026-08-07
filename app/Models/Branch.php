<?php

namespace App\Models;

use App\Services\Pricing\BranchCategoryProfitMarginProvisioner;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'legal_name',
        'tax_id',
        'email',
        'phone',
        'mobile_phone',
        'pm_conciliation_phone',
        'address',
        'city',
        'state',
        'country',
        'is_headquarters',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_headquarters' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Branch $branch): void {
            $branch->forceFill(['code' => 'SUC-'.$branch->id])->saveQuietly();

            $actor = Auth::user()?->email
                ?? Auth::user()?->name
                ?? 'sistema';

            app(BranchCategoryProfitMarginProvisioner::class)->provisionForBranch($branch, $actor);
        });
    }

    /**
     * @return HasMany<Inventory, $this>
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * @return HasMany<BranchCategoryProfitMargin, $this>
     */
    public function categoryProfitMargins(): HasMany
    {
        return $this->hasMany(BranchCategoryProfitMargin::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Roles con alcance operativo en esta sucursal (pivote `branch_rol`).
     *
     * @return BelongsToMany<Rol, $this>
     */
    public function rols(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'branch_rol')->withTimestamps();
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
