<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chunk extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'qdrant_point_id',
        'run_id',
        'text',
        'source',
        'run_topic',
        'semantic_domain',
        'domain_confidence',
        'lens_processed',
        'lens_correction',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'qdrant_point_id'  => 'integer',
            'lens_processed'   => 'boolean',
            'lens_correction'  => 'boolean',
            'domain_confidence'=> 'float',
            'synced_at'        => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id', 'id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class, 'anchor_chunk_id', 'id');
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('lens_processed', false)
                     ->where('lens_correction', false);
    }
}
