<?php

namespace App\Console\Commands;

use App\Jobs\Sync\SyncChunksFromQdrant;
use App\Jobs\Sync\SyncFindingsFromQdrant;
use App\Jobs\Sync\SyncMetaGraphFromQdrant;
use App\Jobs\Sync\SyncNodesFromQdrant;
use App\Services\QdrantService;
use Illuminate\Console\Command;

class KaeSync extends Command
{
    protected $signature = 'kae:sync {collection? : chunks|nodes|meta|findings}';

    protected $description = 'Sync data from Qdrant into Laravel (all collections or one)';

    private const JOBS = [
        'chunks'   => SyncChunksFromQdrant::class,
        'nodes'    => SyncNodesFromQdrant::class,
        'meta'     => SyncMetaGraphFromQdrant::class,
        'findings' => SyncFindingsFromQdrant::class,
    ];

    public function handle(QdrantService $qdrant): int
    {
        $collection = $this->argument('collection');

        if ($collection && ! array_key_exists($collection, self::JOBS)) {
            $this->error("Unknown collection '{$collection}'. Valid: " . implode(', ', array_keys(self::JOBS)));
            return self::FAILURE;
        }

        $targets = $collection ? [$collection => self::JOBS[$collection]] : self::JOBS;

        foreach ($targets as $name => $jobClass) {
            $this->info("Syncing {$name}...");
            $job = new $jobClass();
            $job->handle($qdrant);
            $this->line("  done.");
        }

        $this->info('kae:sync complete.');
        return self::SUCCESS;
    }
}
