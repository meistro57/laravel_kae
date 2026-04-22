<?php

namespace App\Models;

use App\Data\RunSettings;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Run extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'seed',
        'status',
        'started_at',
        'completed_at',
        'report_text',
        'run_id_go',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
            'settings'     => RunSettings::class,
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class, 'run_id', 'id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class, 'run_id', 'id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class, 'run_id', 'id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }
}
