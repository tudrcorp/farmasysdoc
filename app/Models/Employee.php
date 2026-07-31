<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'national_id',
        'phone',
        'email',
        'address',
        'photo_path',
        'monthly_salary_usd',
        'branch_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_salary_usd' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function initials(): string
    {
        $initials = mb_strtoupper(
            mb_substr((string) $this->first_name, 0, 1, 'UTF-8').mb_substr((string) $this->last_name, 0, 1, 'UTF-8'),
            'UTF-8',
        );

        return $initials !== '' ? $initials : 'EM';
    }

    public function hasPhoto(): bool
    {
        return filled($this->photo_path);
    }

    public function photoUrl(): ?string
    {
        if (! $this->hasPhoto()) {
            return null;
        }

        return Storage::disk('public')->url((string) $this->photo_path);
    }

    /**
     * Placeholder circular con iniciales para la columna de avatar en tablas.
     */
    public function tableAvatarPlaceholderDataUri(int $size = 80): string
    {
        $escaped = htmlspecialchars($this->initials(), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $fontSize = (int) round($size * 0.36);
        $radius = (int) round($size / 2);

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}">
            <circle cx="{$radius}" cy="{$radius}" r="{$radius}" fill="#0d9488"/>
            <text x="50%" y="50%" dominant-baseline="central" text-anchor="middle" fill="#ffffff" font-family="system-ui,-apple-system,sans-serif" font-size="{$fontSize}" font-weight="700">{$escaped}</text>
            </svg>
            SVG;

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<HrAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(HrAssignment::class);
    }

    /**
     * @return HasMany<HrDeduction, $this>
     */
    public function deductions(): HasMany
    {
        return $this->hasMany(HrDeduction::class);
    }

    /**
     * @return HasMany<HrLoan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(HrLoan::class);
    }

    /**
     * @return HasMany<PayrollLine, $this>
     */
    public function payrollLines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }
}
