<?php

namespace App\Models;

use App\Enums\PartnerCompanyDocumentType;
use Database\Factories\PartnerCompanyDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PartnerCompanyDocument extends Model
{
    /** @use HasFactory<PartnerCompanyDocumentFactory> */
    use HasFactory;

    public const string DISK = 'local';

    public const string DIRECTORY = 'partner-companies/documents';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'partner_company_id',
        'type',
        'file_path',
        'original_name',
        'expires_at',
        'notes',
        'sort',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PartnerCompanyDocumentType::class,
            'expires_at' => 'date',
            'sort' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (PartnerCompanyDocument $document): void {
            $previousPath = (string) $document->getOriginal('file_path');

            if ($document->isDirty('file_path') && $previousPath !== '') {
                Storage::disk(self::DISK)->delete($previousPath);
            }
        });

        static::deleted(function (PartnerCompanyDocument $document): void {
            if (filled($document->file_path)) {
                Storage::disk(self::DISK)->delete((string) $document->file_path);
            }
        });
    }

    /**
     * @return BelongsTo<PartnerCompany, $this>
     */
    public function partnerCompany(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class);
    }

    public function fileExists(): bool
    {
        return filled($this->file_path) && Storage::disk(self::DISK)->exists((string) $this->file_path);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast() && ! $this->expires_at->isToday();
    }

    public function displayName(): string
    {
        return filled($this->original_name)
            ? (string) $this->original_name
            : basename((string) $this->file_path);
    }

    public function downloadUrl(): string
    {
        return route('partner-companies.document', ['document' => $this]);
    }
}
