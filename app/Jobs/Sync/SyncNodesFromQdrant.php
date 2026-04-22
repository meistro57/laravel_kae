<?php

namespace App\Jobs\Sync;

use App\Models\Node;
use App\Models\Run;
use App\Models\SyncCursor;
use App\Services\QdrantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncNodesFromQdrant implements ShouldQueue
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
        $collection = config('kae.collections.nodes');
        $cursor     = SyncCursor::forCollection($collection);

        $filter = [];
        if ($this->runIdGo) {
            $filter = ['must' => [['key' => 'run_id', 'match' => ['value' => $this->runIdGo]]]];
        }

        $offset   = $this->runIdGo ? null : $cursor->last_point_id;
        $total    = 0;
        $runCache = [];

        do {
            $page = $qdrant->scroll($collection, $filter, 100, $offset);

            if (! $page || empty($page['points'])) {
                break;
            }

            foreach ($page['points'] as $point) {
                $this->upsertNode($point, $runCache);
                $total++;
            }

            $offset = $page['next_page_offset'] ?? null;

            if (! $this->runIdGo) {
                $cursor->update(['last_point_id' => $offset]);
            }
        } while ($offset !== null);

        if (! $this->runIdGo) {
            $cursor->markSynced();
        }

        Log::info("SyncNodesFromQdrant: upserted {$total} records", [
            'run_id_go' => $this->runIdGo,
        ]);
    }

    private function upsertNode(array $point, array &$runCache): void
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

        // sources can be null in Qdrant (not all nodes carry citations)
        $sources = $payload['sources'];
        if (is_string($sources)) {
            $sources = json_decode($sources, true) ?? [];
        }

        Node::updateOrCreate(
            ['qdrant_point_id' => (int) $point['id']],
            [
                'run_id'    => $laravelRunId,
                'label'     => $payload['label']  ?? '',
                'domain'    => $payload['domain'] ?? 'inferred',
                'weight'    => (float) ($payload['weight']  ?? 0.0),
                'anomaly'   => (bool)  ($payload['anomaly'] ?? false),
                'sources'   => $sources,
                'cycle'     => (int)   ($payload['cycle']   ?? 0),
                'synced_at' => now(),
            ]
        );
    }
}
