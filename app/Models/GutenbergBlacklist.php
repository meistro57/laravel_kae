<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GutenbergBlacklist extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'reason',
        'detection_date',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'detection_date' => 'date',
            'active'         => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
