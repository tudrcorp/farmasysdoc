<?php

namespace App\Models;

use App\Enums\PartnerAgreementStatus;
use App\Enums\PartnerAgreementType;
use App\Enums\PartnerCompanyPaymentModality;
use App\Services\Partners\PartnerAgreementWorkflow;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\PartnerCompanyAgreementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

/**
 * Versión de convenio con una compañía aliada. Las transiciones de estado se hacen solo a través de
 * {@see PartnerAgreementWorkflow}; un convenio que salió de borrador no se edita.
 *
 * @property PartnerAgreementType $type
 * @property PartnerAgreementStatus $status
 * @property PartnerCompanyPaymentModality $payment_modality
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 */
class PartnerCompanyAgreement extends Model
{
    /** @use HasFactory<PartnerCompanyAgreementFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'partner_company_id',
        'previous_agreement_id',
        'type',
        'starts_on',
        'ends_on',
        'auto_renews',
        'renewal_term_months',
        'payment_modality',
        'credit_limit_usd',
        'discount_percentage',
        'profit_percentage_a',
        'terms',
        'change_reason',
        'signed_document_path',
        'signed_document_name',
        'signed_on',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'borrador',
        'is_automatic' => false,
        'auto_renews' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PartnerAgreementType::class,
            'status' => PartnerAgreementStatus::class,
            'payment_modality' => PartnerCompanyPaymentModality::class,
            'is_automatic' => 'boolean',
            'auto_renews' => 'boolean',
            'renewal_term_months' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'signed_on' => 'immutable_date',
            'credit_limit_usd' => 'decimal:2',
            'discount_percentage' => 'decimal:6',
            'profit_percentage_a' => 'decimal:6',
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    public static function formatNumber(int|string $id, ?int $year = null): string
    {
        return 'CONV-'.($year ?? (int) now()->format('Y')).'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<PartnerCompany, $this>
     */
    public function partnerCompany(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class);
    }

    /**
     * @return BelongsTo<PartnerCompanyAgreement, $this>
     */
    public function previousAgreement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_agreement_id');
    }

    /**
     * @return HasOne<PartnerCompanyAgreement, $this>
     */
    public function nextAgreement(): HasOne
    {
        return $this->hasOne(self::class, 'previous_agreement_id')->latestOfMany();
    }

    /**
     * @return HasMany<PartnerCompanyAgreementEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PartnerCompanyAgreementEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<PartnerCompanyAgreementNotice, $this>
     */
    public function notices(): HasMany
    {
        return $this->hasMany(PartnerCompanyAgreementNotice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @param  Builder<PartnerCompanyAgreement>  $query
     * @return Builder<PartnerCompanyAgreement>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PartnerAgreementStatus::Active->value);
    }

    /**
     * Vigentes cuya fecha de fin cae dentro de los próximos `$days` días (incluye hoy).
     *
     * @param  Builder<PartnerCompanyAgreement>  $query
     * @return Builder<PartnerCompanyAgreement>
     */
    public function scopeExpiringWithin(Builder $query, int $days, ?CarbonInterface $from = null): Builder
    {
        $today = CarbonImmutable::parse(($from ?? now())->toDateString())->startOfDay();

        return $query->active()
            ->whereDate('ends_on', '>=', $today->toDateString())
            ->whereDate('ends_on', '<=', $today->addDays($days)->toDateString());
    }

    public function usesCreditLimit(): bool
    {
        return $this->payment_modality?->usesCreditLimit() ?? false;
    }

    /**
     * Días que faltan para la fecha de fin (negativo si ya pasó).
     */
    public function daysUntilEnd(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->ends_on->startOfDay(), false);
    }

    public function termInDays(): int
    {
        return (int) $this->starts_on->diffInDays($this->ends_on) + 1;
    }

    public function termLabel(): string
    {
        return $this->starts_on->format('d/m/Y').' — '.$this->ends_on->format('d/m/Y');
    }

    public function hasSignedDocument(): bool
    {
        return filled($this->signed_document_path)
            && Storage::disk(self::documentDisk())->exists((string) $this->signed_document_path);
    }

    public function signedDocumentUrl(): ?string
    {
        return filled($this->signed_document_path)
            ? route('partner-agreements.document', ['agreement' => $this])
            : null;
    }

    public static function documentDisk(): string
    {
        return (string) config('partner_agreements.document_disk', 'local');
    }

    public function displayTitle(): string
    {
        return trim(($this->number ?? 'Convenio en borrador').' · '.($this->partnerCompany?->legal_name ?? ''), ' ·');
    }
}
