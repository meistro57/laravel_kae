<?php

use App\Jobs\Sync\SyncChunksFromQdrant;
use App\Jobs\Sync\SyncFindingsFromQdrant;
use App\Jobs\Sync\SyncMetaGraphFromQdrant;
use App\Jobs\Sync\SyncNodesFromQdrant;
use App\Services\QdrantService;

beforeEach(function () {
    $qdrant = Mockery::mock(QdrantService::class);
    $qdrant->shouldReceive('scroll')->andReturn(['points' => [], 'next_page_offset' => null]);
    app()->instance(QdrantService::class, $qdrant);
});

it('runs all four sync jobs when no argument is given', function () {
    $this->artisan('kae:sync')
        ->assertSuccessful()
        ->expectsOutputToContain('Syncing chunks')
        ->expectsOutputToContain('Syncing nodes')
        ->expectsOutputToContain('Syncing meta')
        ->expectsOutputToContain('Syncing findings');
});

it('runs only the specified collection', function () {
    $this->artisan('kae:sync', ['collection' => 'chunks'])
        ->assertSuccessful()
        ->expectsOutputToContain('Syncing chunks');
});

it('returns failure for an unknown collection', function () {
    $this->artisan('kae:sync', ['collection' => 'invalid'])
        ->assertFailed()
        ->expectsOutputToContain("Unknown collection 'invalid'");
});

it('accepts each valid collection name', function (string $collection) {
    $this->artisan('kae:sync', ['collection' => $collection])
        ->assertSuccessful();
})->with(['chunks', 'nodes', 'meta', 'findings']);
