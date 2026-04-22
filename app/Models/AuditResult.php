<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditResult extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'run_timestamp',
        'summary',
        'issues_found',
        'issues_repaired',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'run_timestamp'  => 'datetime',
            'summary'        => 'array',
            'issues_found'   => 'integer',
            'issues_repaired'=> 'integer',
            'details'        => 'array',
        ];
    }
}
