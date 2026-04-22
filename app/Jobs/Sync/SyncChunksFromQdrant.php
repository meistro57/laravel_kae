<?php

namespace App\Jobs\Sync;

use App\Models\Chunk;
use App\Models\Run;
use App\Models\SyncCursor;
use App\Services\QdrantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncChunksFromQdrant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    public function __construct(
        public readonly ?string $runIdGo = null,
    ) {
        $this->onQueue('kae_sync');
    }

    public function handle(QdrantService $qdrant): void
    {
        $collection = config('kae.collections.chunks');
        $cursor     = SyncCursor::forCollection($collection);

        $filter = [];
        if ($this->runIdGo) {
            $filter = ['must' => [['key' => 'run_id', 'match' => ['value' => $this->runIdGo]]]];
        }

        // When filtering by run, always start from the beginning (not the global cursor).
        $offset  = $this->runIdGo ? null : $cursor->last_point_id;
        $total   = 0;
        $runCache = [];

        do {
            $page = $qdrant->scroll($collection, $filter, 100, $offset);

            if (! $page || empty($page['points'])) {
                break;
            }

            foreach ($page['points'] as $point) {
                $this->upsertChunk($point, $runCache);
                $total++;
            }

            $offset = $page['next_page_offset'] ?? null;

            // Persist cursor after each page so the job is resumable on failure.
            if (! $this->runIdGo) {
                $cursor->update(['last_point_id' => $offset]);
            }
        } while ($offset !== null);

        if (! $this->runIdGo) {
            $cursor->markSynced();
        }

        Log::info("SyncChunksFromQdrant: upserted {$total} records", [
            'run_id_go' => $this->runIdGo,
        ]);
    }

    private function upsertChunk(array $point, array &$runCache): void
    {
        $payload = $point['payload'] ?? [];
        $goRunId = $payload['run_id'] ?? null;

        $laravelRunId = null;
        if ($goRunId) {
            if (! isset($runCache[$goRunId])) {
                $run = Run::firstOrCreate(
                    ['run_id_go' => $goRunId],
                    ['status' => 'completed'],
                );
                $runCache[$goRunId] = $run->id;
            }
            $laravelRunId = $runCache[$goRunId];
        }

        Chunk::updateOrCreate(
            ['qdrant_point_id' => (int) $point['id']],
            [
                'run_id'            => $laravelRunId,
                'text'              => $payload['text']              ?? '',
                'source'            => $payload['source']            ?? '',
                'run_topic'         => $payload['run_topic']         ?? '',
                'semantic_domain'   => $payload['semantic_domain']   ?? '',
                'domain_confidence' => (float) ($payload['domain_confidence'] ?? 0.0),
                'lens_processed'    => (bool) ($payload['lens_processed']  ?? false),
                'lens_correction'   => (bool) ($payload['lens_correction'] ?? false),
                'synced_at'         => now(),
            ]
        );
    }
}
