<?php

namespace App\Console\Commands;

use App\Models\GutenbergBlacklist;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ImportGutenbergBlacklist extends Command
{
    protected $signature   = 'kae:import-gutenberg-blacklist
                                {--path= : Absolute path to gutenberg_blacklist.json (defaults to Go repo root)}
                                {--dry-run : Print what would be imported without writing}';

    protected $description = 'Import the Go-side gutenberg_blacklist.json into the gutenberg_blacklists table';

    public function handle(): int
    {
        $path = $this->option('path')
            ?? base_path('../kae/gutenberg_blacklist.json');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");
            $this->line("Pass --path=/absolute/path/to/gutenberg_blacklist.json");

            return self::FAILURE;
        }

        $raw = json_decode(file_get_contents($path), true);

        if (! isset($raw['blacklisted_titles']) || ! is_array($raw['blacklisted_titles'])) {
            $this->error("Invalid format: expected top-level 'blacklisted_titles' array.");

            return self::FAILURE;
        }

        $entries = $raw['blacklisted_titles'];
        $this->info("Found " . count($entries) . " entries in {$path}");

        if ($this->option('dry-run')) {
            $this->table(['Title', 'Reason', 'Detection Date'], array_map(fn ($e) => [
                $e['title'],
                $e['reason'],
                $e['detection_date'],
            ], $entries));

            return self::SUCCESS;
        }

        $imported = 0;
        $skipped  = 0;

        foreach ($entries as $entry) {
            $detectionDate = Carbon::parse($entry['detection_date'])->toDateString();

            $created = GutenbergBlacklist::firstOrCreate(
                ['title' => $entry['title']],
                [
                    'reason'         => $entry['reason'],
                    'detection_date' => $detectionDate,
                    'active'         => true,
                ]
            );

            $created->wasRecentlyCreated ? $imported++ : $skipped++;
        }

        $this->info("Imported: {$imported} | Already existed: {$skipped}");

        return self::SUCCESS;
    }
}
