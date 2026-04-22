<?php

namespace App\Jobs\Sync;

use App\Models\MetaConcept;
use App\Models\SyncCursor;
use App\Services\QdrantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncMetaGraphFromQdrant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('kae_sync');
    }

    public function handle(QdrantService $qdrant): void
    {
        $collection = config('kae.collections.meta');
        $cursor     = SyncCursor::forCollection($collection);
        $offset     = $cursor->last_point_id;
        $total      = 0;

        do {
            $page = $qdrant->scroll($collection, [], 100, $offset);

            if (! $page || empty($page['points'])) {
                break;
            }

            foreach ($page['points'] as $point) {
                $this->upsertMetaConcept($point);
                $total++;
            }

            $offset = $page['next_page_offset'] ?? null;
            $cursor->update(['last_point_id' => $offset]);
        } while ($offset !== null);

        $cursor->markSynced();

        Log::info("SyncMetaGraphFromQdrant: upserted {$total} records");
    }

    private function upsertMetaConcept(array $point): void
    {
        $payload = $point['payload'] ?? [];

        // run_occurrences is stored as a JSON-encoded string in Qdrant (double-encoded).
        $runOccurrences = $payload['run_occurrences'] ?? null;
        if (is_string($runOccurrences)) {
            $runOccurrences = json_decode($runOccurrences, true) ?? [];
        }

        // domains is a proper JSON array in Qdrant.
        $domains = $payload['domains'] ?? [];
        if (is_string($domains)) {
            $domains = json_decode($domains, true) ?? [];
        }

        // first_seen is a unix timestamp integer.
        $firstSeen = isset($payload['first_seen'])
            ? Carbon::createFromTimestamp((int) $payload['first_seen'])
            : null;

        MetaConcept::updateOrCreate(
            ['qdrant_point_id' => (int) $point['id']],
            [
                'concept'          => $payload['concept']          ?? '',
                'first_seen_at'    => $firstSeen,
                'total_weight'     => (float) ($payload['total_weight']     ?? 0.0),
                'avg_anomaly'      => (float) ($payload['avg_anomaly']      ?? 0.0),
                'domains'          => $domains,
                'is_attractor'     => (bool)  ($payload['is_attractor']     ?? false),
                'occurrence_count' => (int)   ($payload['occurrence_count'] ?? 0),
                'run_occurrences'  => $runOccurrences,
                'synced_at'        => now(),
            ]
        );
    }
}
