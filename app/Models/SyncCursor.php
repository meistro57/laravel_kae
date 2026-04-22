<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncCursor extends Model
{
    protected $primaryKey = 'collection_name';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'collection_name',
        'last_synced_at',
        'last_point_id',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'last_point_id'  => 'json',  // preserves int|string|null without type coercion
        ];
    }

    public static function forCollection(string $collection): static
    {
        return static::firstOrCreate(['collection_name' => $collection]);
    }

    public function markSynced(): void
    {
        $this->update([
            'last_synced_at' => now(),
            'last_point_id'  => null,
        ]);
    }
}
