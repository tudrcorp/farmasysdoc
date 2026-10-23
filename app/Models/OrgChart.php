<?php

namespace App\Models;

use Database\Factories\OrgChartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgChart extends Model
{
    /** @use HasFactory<OrgChartFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(OrgChartNode::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (OrgChart $chart): void {
            $chart->nodes()->update(['parent_id' => null]);
            $chart->nodes()->delete();
        });
    }
}
