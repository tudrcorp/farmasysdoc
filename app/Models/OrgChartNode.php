<?php

namespace App\Models;

use Database\Factories\OrgChartNodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgChartNode extends Model
{
    /** @use HasFactory<OrgChartNodeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'org_chart_id',
        'parent_id',
        'employee_id',
        'branch_id',
        'title',
        'shape',
        'area',
        'position_x',
        'position_y',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position_x' => 'integer',
            'position_y' => 'integer',
        ];
    }

    public function chart(): BelongsTo
    {
        return $this->belongsTo(OrgChart::class, 'org_chart_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isVacant(): bool
    {
        return $this->employee_id === null;
    }
}
