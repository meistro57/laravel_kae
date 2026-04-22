<?php

use App\Jobs\Sync\SyncChunksFromQdrant;
use App\Jobs\Sync\SyncFindingsFromQdrant;
use App\Jobs\Sync\SyncMetaGraphFromQdrant;
use App\Jobs\Sync\SyncNodesFromQdrant;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SyncChunksFromQdrant())->everyFiveMinutes()->name('sync-chunks')->withoutOverlapping();
Schedule::job(new SyncNodesFromQdrant())->everyFifteenMinutes()->name('sync-nodes')->withoutOverlapping();
Schedule::job(new SyncFindingsFromQdrant())->everyFifteenMinutes()->name('sync-findings')->withoutOverlapping();
Schedule::job(new SyncMetaGraphFromQdrant())->hourly()->name('sync-meta')->withoutOverlapping();

