<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * Typed representation of the `settings` JSON column on the Run model.
 * Mirrors the CLI flags accepted by the Go worker (see MIGRATION_ORIENTATION.md §2).
 */
class RunSettings extends Data
{
    public function __construct(
        public readonly string  $model             = 'deepseek/deepseek-r1',
        public readonly string  $fast_model        = 'google/gemini-2.5-flash',
        public readonly int     $max_cycles        = 0,
        public readonly int     $stagnation_window = 3,
        public readonly float   $novelty_threshold = 0.05,
        public readonly float   $branch_threshold  = 0.7,
        public readonly bool    $ensemble_enabled  = false,
        /** @var string[] */
        public readonly array   $ensemble_models   = [],
        /** @var string[] */
        public readonly array   $sources_enabled   = ['arxiv', 'wiki', 'pubmed', 'openalex', 'gutenberg'],
    ) {}
}
