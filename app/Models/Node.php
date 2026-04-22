<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Node extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'qdrant_point_id',
        'run_id',
        'label',
        'domain',
        'weight',
        'anomaly',
        'sources',
        'cycle',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'qdrant_point_id' => 'integer',
            'weight'          => 'float',
            'anomaly'         => 'boolean',
            'sources'         => 'array',
            'cycle'           => 'integer',
            'synced_at'       => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id', 'id');
    }

    public function scopeAnomalous($query)
    {
        return $query->where('anomaly', true);
    }

    public function scopeByWeight($query, string $direction = 'desc')
    {
        return $query->orderBy('weight', $direction);
    }
}
