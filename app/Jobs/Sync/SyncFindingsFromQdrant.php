<?php

namespace App\Jobs\Sync;

use App\Models\Chunk;
use App\Models\Finding;
use App\Models\Run;
use App\Models\SyncCursor;
use App\Services\QdrantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncFindingsFromQdrant implements ShouldQueue
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
        $collection = config('kae.collections.findings');
        $cursor     = SyncCursor::forCollection($collection);
        $offset     = $cursor->last_point_id;
        $total      = 0;

        do {
            $page = $qdrant->scroll($collection, [], 100, $offset);

            if (! $page || empty($page['points'])) {
                break;
            }

            foreach ($page['points'] as $point) {
                $this->upsertFinding($point);
                $total++;
            }

            $offset = $page['next_page_offset'] ?? null;
            $cursor->update(['last_point_id' => $offset]);
        } while ($offset !== null);

        $cursor->markSynced();

        Log::info("SyncFindingsFromQdrant: upserted {$total} records");
    }

    private function upsertFinding(array $point): void
    {
        $payload       = $point['payload'] ?? [];
        $qdrantPointId = (string) $point['id'];   // UUID string for findings

        // Resolve the first source_point_id → anchor Chunk, if any.
        $anchorChunkId = null;
        $sourcePointIds = $payload['source_point_ids'] ?? [];
        if (! empty($sourcePointIds)) {
            $firstId = is_numeric($sourcePointIds[0])
                ? (int) $sourcePointIds[0]
                : $sourcePointIds[0];
            $anchor  = Chunk::where('qdrant_point_id', $firstId)->first();
            if ($anchor) {
                $anchorChunkId = $anchor->id;
            }
        }

        // Resolve run_id from the anchor chunk's run.
        $laravelRunId = null;
        if ($anchorChunkId) {
            $laravelRunId = Chunk::find($anchorChunkId)?->run_id;
        }

        $createdAt = isset($payload['created_at'])
            ? Carbon::createFromTimestamp((int) $payload['created_at'])
            : null;

        Finding::updateOrCreate(
            ['qdrant_point_id' => $qdrantPointId],
            [
                'id'                => $qdrantPointId,  // UUID pk matches Qdrant UUID
                'run_id'            => $laravelRunId,
                'anchor_chunk_id'   => $anchorChunkId,
                // Map real Lens Writer fields onto the existing + new schema
                'finding'           => $payload['summary']          ?? $payload['finding']  ?? '',
                'confidence'        => (float) ($payload['confidence']     ?? 0.0),
                'sources'           => $payload['source_point_ids'] ?? $payload['sources']  ?? [],
                'density_assessment'=> null,            // not emitted by current Lens
                'reasoning_model'   => $payload['reasoning_model']  ?? null,
                'created_at'        => $createdAt,
                'synced_at'         => now(),
                // Real Lens fields
                'type'              => $payload['type']             ?? null,
                'batch_id'          => $payload['batch_id']         ?? null,
                'reviewed'          => (bool) ($payload['reviewed'] ?? false),
                'reasoning_trace'   => $payload['reasoning_trace']  ?? null,
                'correction'        => $payload['correction']       ?? null,
                'domains'           => $payload['domains']          ?? null,
                // Spill-over: anything not mapped to its own column
                'raw_payload'       => array_intersect_key($payload, array_flip(['embedding_text', 'source_urls'])),
            ]
        );
    }
}
