<?php

namespace App\Models;

use App\Enums\EmployeeBankAccountType;
use App\Enums\HrPayCurrencyBucket;
use App\Enums\VenezuelanPagoMovilBank;
use App\Support\Notifications\WhatsAppLink;
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
        'bank_account_number',
        'bank_code',
        'bank_account_type',
        'photo_path',
        'signature_path',
        'fingerprint_path',
        'file_terms_accepted_at',
        'monthly_salary_usd',
        'legal_salary_ves',
        'first_half_pay_currency',
        'second_half_pay_currency',
        'first_half_usd_cash',
        'second_half_usd_cash',
        'first_half_ves_cash',
        'second_half_ves_cash',
        'branch_id',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'portal_password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bank_account_type' => EmployeeBankAccountType::class,
            'monthly_salary_usd' => 'decimal:2',
            'legal_salary_ves' => 'decimal:2',
            'first_half_pay_currency' => HrPayCurrencyBucket::class,
            'second_half_pay_currency' => HrPayCurrencyBucket::class,
            'first_half_usd_cash' => 'decimal:2',
            'second_half_usd_cash' => 'decimal:2',
            'first_half_ves_cash' => 'decimal:2',
            'second_half_ves_cash' => 'decimal:2',
            'is_active' => 'boolean',
            'file_terms_accepted_at' => 'datetime',
            'portal_password' => 'hashed',
        ];
    }

    public function bank(): ?VenezuelanPagoMovilBank
    {
        if (! filled($this->bank_code)) {
            return null;
        }

        return VenezuelanPagoMovilBank::tryFrom((string) $this->bank_code);
    }

    public function bankLabel(): ?string
    {
        return $this->bank()?->optionLabel();
    }

    protected static function booted(): void
    {
        static::saving(function (Employee $employee): void {
            $employee->syncUsdCashPortions();
        });
    }

    public function biweeklyBaseUsd(): float
    {
        return round((float) $this->monthly_salary_usd / 2, 2);
    }

    public function hasLegalSalary(): bool
    {
        return $this->legal_salary_ves !== null && (float) $this->legal_salary_ves > 0;
    }

    public function biweeklyLegalSalaryVes(): float
    {
        return round((float) ($this->legal_salary_ves ?? 0) / 2, 2);
    }

    public function payCurrencyForPeriod(bool $isMonthEnd): HrPayCurrencyBucket
    {
        if ($this->usdCashForPeriod($isMonthEnd) <= 0) {
            return HrPayCurrencyBucket::Ves;
        }

        $currency = $isMonthEnd
            ? $this->second_half_pay_currency
            : $this->first_half_pay_currency;

        return $currency ?? HrPayCurrencyBucket::Usd;
    }

    /**
     * USD efectivo cargado para la quincena.
     */
    public function usdCashForPeriod(bool $isMonthEnd): float
    {
        $configured = $isMonthEnd
            ? (float) $this->second_half_usd_cash
            : (float) $this->first_half_usd_cash;

        return round(min(max(0, $configured), $this->biweeklyBaseUsd()), 2);
    }

    /**
     * Bolívares cargados para la quincena. Si es 0, el resto de la base se paga en VES a tasa BCV.
     */
    public function vesCashForPeriod(bool $isMonthEnd): float
    {
        $configured = $isMonthEnd
            ? (float) $this->second_half_ves_cash
            : (float) $this->first_half_ves_cash;

        return round(max(0, $configured), 2);
    }

    public function syncUsdCashPortions(): void
    {
        $base = $this->biweeklyBaseUsd();

        $firstUsd = $this->normalizedUsdCash((float) $this->first_half_usd_cash, $base);
        $secondUsd = $this->normalizedUsdCash((float) $this->second_half_usd_cash, $base);
        $firstVes = $this->normalizedVesCash((float) $this->first_half_ves_cash);
        $secondVes = $this->normalizedVesCash((float) $this->second_half_ves_cash);

        $this->first_half_usd_cash = $firstUsd;
        $this->second_half_usd_cash = $secondUsd;
        $this->first_half_ves_cash = $firstVes;
        $this->second_half_ves_cash = $secondVes;
        $this->first_half_pay_currency = $firstUsd > 0 ? HrPayCurrencyBucket::Usd : HrPayCurrencyBucket::Ves;
        $this->second_half_pay_currency = $secondUsd > 0 ? HrPayCurrencyBucket::Usd : HrPayCurrencyBucket::Ves;
    }

    private function normalizedUsdCash(float $amount, float $base): float
    {
        return round(min(max(0, $amount), $base), 2);
    }

    private function normalizedVesCash(float $amount): float
    {
        return round(max(0, $amount), 2);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function formattedNationalId(): ?string
    {
        $raw = trim((string) $this->national_id);
        if ($raw === '') {
            return null;
        }

        $upper = mb_strtoupper($raw, 'UTF-8');
        $letter = str_starts_with($upper, 'E') ? 'E' : 'V';
        $digits = preg_replace('/\D/', '', $upper) ?? '';

        if ($digits === '') {
            return $raw;
        }

        return $letter.'-'.number_format((int) $digits, 0, ',', '.');
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

    public function hasSignature(): bool
    {
        return filled($this->signature_path);
    }

    public function signatureUrl(): ?string
    {
        if (! $this->hasSignature()) {
            return null;
        }

        return Storage::disk('public')->url((string) $this->signature_path);
    }

    public function hasFingerprint(): bool
    {
        return filled($this->fingerprint_path);
    }

    public function fingerprintUrl(): ?string
    {
        if (! $this->hasFingerprint()) {
            return null;
        }

        return Storage::disk('public')->url((string) $this->fingerprint_path);
    }

    public function hasCompleteEmployeeFile(): bool
    {
        return $this->hasSignature() && $this->hasFingerprint();
    }

    public function hasAcceptedFileTerms(): bool
    {
        return $this->file_terms_accepted_at !== null;
    }

    public function acceptFileTerms(): void
    {
        if ($this->hasAcceptedFileTerms()) {
            return;
        }

        $this->forceFill(['file_terms_accepted_at' => now()])->save();
    }

    public function hasPortalPassword(): bool
    {
        return filled($this->portal_password);
    }

    public function hasPortalEmail(): bool
    {
        return filled($this->email) && filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function maskedPortalEmail(): ?string
    {
        if (! $this->hasPortalEmail()) {
            return null;
        }

        [$local, $domain] = explode('@', (string) $this->email, 2);
        $visible = mb_substr($local, 0, 1);

        return $visible.'***@'.$domain;
    }

    public function maskedPortalPhone(): ?string
    {
        $digits = WhatsAppLink::normalizePhoneDigits($this->phone);

        if ($digits === null) {
            return null;
        }

        return '***'.substr($digits, -4);
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

    /**
     * @return HasMany<HrPayrollReceipt, $this>
     */
    public function payrollReceipts(): HasMany
    {
        return $this->hasMany(HrPayrollReceipt::class);
    }
}
