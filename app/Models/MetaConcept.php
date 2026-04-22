<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetaConcept extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'qdrant_point_id',
        'concept',
        'first_seen_at',
        'total_weight',
        'avg_anomaly',
        'domains',
        'is_attractor',
        'occurrence_count',
        'run_occurrences',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'qdrant_point_id'  => 'integer',
            'first_seen_at'    => 'datetime',
            'total_weight'     => 'float',
            'avg_anomaly'      => 'float',
            'domains'          => 'array',
            'is_attractor'     => 'boolean',
            'occurrence_count' => 'integer',
            'run_occurrences'  => 'array',
            'synced_at'        => 'datetime',
        ];
    }

    public function scopeAttractors($query)
    {
        return $query->where('is_attractor', true);
    }

    public function scopeByOccurrences($query)
    {
        return $query->orderBy('occurrence_count', 'desc');
    }
}
