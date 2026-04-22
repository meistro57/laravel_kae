<?php

use App\Models\GutenbergBlacklist;
use Illuminate\Support\Facades\Artisan;

it('kae:import-gutenberg-blacklist populates the table from the go repo json', function () {
    $jsonPath = base_path('../kae/gutenberg_blacklist.json');

    if (! file_exists($jsonPath)) {
        $this->markTestSkipped("gutenberg_blacklist.json not found at {$jsonPath}");
    }

    $raw      = json_decode(file_get_contents($jsonPath), true);
    $expected = count($raw['blacklisted_titles']);

    Artisan::call('kae:import-gutenberg-blacklist', [
        '--path' => $jsonPath,
    ]);

    expect(GutenbergBlacklist::count())->toBe($expected);
});

it('imports all titles from the json with correct field values', function () {
    $jsonPath = base_path('../kae/gutenberg_blacklist.json');

    if (! file_exists($jsonPath)) {
        $this->markTestSkipped("gutenberg_blacklist.json not found at {$jsonPath}");
    }

    Artisan::call('kae:import-gutenberg-blacklist', ['--path' => $jsonPath]);

    $raw     = json_decode(file_get_contents($jsonPath), true);
    $firstEntry = $raw['blacklisted_titles'][0];

    $record = GutenbergBlacklist::where('title', $firstEntry['title'])->first();

    expect($record)->not->toBeNull()
        ->and($record->title)->toBe($firstEntry['title'])
        ->and($record->reason)->toBe($firstEntry['reason'])
        ->and($record->active)->toBeTrue();
});

it('is idempotent: running import twice does not create duplicates', function () {
    $jsonPath = base_path('../kae/gutenberg_blacklist.json');

    if (! file_exists($jsonPath)) {
        $this->markTestSkipped("gutenberg_blacklist.json not found at {$jsonPath}");
    }

    Artisan::call('kae:import-gutenberg-blacklist', ['--path' => $jsonPath]);
    $countAfterFirst = GutenbergBlacklist::count();

    Artisan::call('kae:import-gutenberg-blacklist', ['--path' => $jsonPath]);
    $countAfterSecond = GutenbergBlacklist::count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});

it('dry-run does not write to the database', function () {
    $jsonPath = base_path('../kae/gutenberg_blacklist.json');

    if (! file_exists($jsonPath)) {
        $this->markTestSkipped("gutenberg_blacklist.json not found at {$jsonPath}");
    }

    Artisan::call('kae:import-gutenberg-blacklist', [
        '--path'    => $jsonPath,
        '--dry-run' => true,
    ]);

    expect(GutenbergBlacklist::count())->toBe(0);
});
