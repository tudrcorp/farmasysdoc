<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Foto o soporte de una devolución (al registrarla o al entregarla al proveedor).
 */
class SupplierReturnAttachment extends Model
{
    public const string STAGE_REGISTRATION = 'registro';

    public const string STAGE_RETURN = 'devolucion';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_return_id',
        'stage',
        'file_path',
        'original_name',
        'uploaded_by',
    ];

    protected static function booted(): void
    {
        static::deleted(function (SupplierReturnAttachment $attachment): void {
            if (filled($attachment->file_path)) {
                Storage::disk(self::disk())->delete((string) $attachment->file_path);
            }
        });
    }

    public static function disk(): string
    {
        return (string) config('supplier_returns.attachment_disk', 'local');
    }

    /**
     * @return BelongsTo<SupplierReturn, $this>
     */
    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo(SupplierReturn::class);
    }

    public function displayName(): string
    {
        return filled($this->original_name) ? (string) $this->original_name : basename((string) $this->file_path);
    }

    public function url(): string
    {
        return route('supplier-returns.attachment', ['attachment' => $this]);
    }

    public function stageLabel(): string
    {
        return $this->stage === self::STAGE_RETURN ? 'Entrega al proveedor' : 'Registro';
    }
}
