<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Soporte cargado por el empleado (reposo médico o permiso). Se guarda en el disco privado.
 */
class EmployeeRequestAttachment extends Model
{
    public const string DISK = 'local';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_request_id',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<EmployeeRequest, $this>
     */
    public function employeeRequest(): BelongsTo
    {
        return $this->belongsTo(EmployeeRequest::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function sizeLabel(): string
    {
        $kb = $this->size_bytes / 1024;

        return $kb >= 1024
            ? number_format($kb / 1024, 1, ',', '.').' MB'
            : number_format($kb, 0, ',', '.').' KB';
    }
}
