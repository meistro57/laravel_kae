<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finding extends Model
{
    use HasFactory, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'qdrant_point_id',
        'run_id',
        'anchor_chunk_id',
        'finding',          // from Qdrant 'summary' field
        'confidence',
        'sources',          // from Qdrant 'source_point_ids' field
        'density_assessment',
        'reasoning_model',
        'created_at',
        'synced_at',
        // Fields from real Lens Writer (not in original orientation report)
        'type',
        'batch_id',
        'reviewed',
        'reasoning_trace',
        'correction',
        'domains',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'confidence'      => 'float',
            'sources'         => 'array',
            'domains'         => 'array',
            'raw_payload'     => 'array',
            'reviewed'        => 'boolean',
            'created_at'      => 'datetime',
            'synced_at'       => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id', 'id');
    }

    public function anchorChunk(): BelongsTo
    {
        return $this->belongsTo(Chunk::class, 'anchor_chunk_id', 'id');
    }

    public function scopeHighConfidence($query, float $threshold = 0.75)
    {
        return $query->where('confidence', '>=', $threshold);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
