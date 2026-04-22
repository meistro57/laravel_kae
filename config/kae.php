<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Qdrant Vector Database
    |--------------------------------------------------------------------------
    */
    'qdrant' => [
        'url'       => env('QDRANT_URL', 'http://localhost:6333'),
        'grpc_addr' => env('QDRANT_GRPC_ADDR', 'localhost:6334'),
        'api_key'   => env('QDRANT_API_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM Providers (consumed by Go worker; Prism reads these in Phase 3)
    |--------------------------------------------------------------------------
    */
    'llm' => [
        'openrouter_key' => env('OPENROUTER_API_KEY'),
        'anthropic_key'  => env('ANTHROPIC_API_KEY'),
        'openai_key'     => env('OPENAI_API_KEY'),
        'gemini_key'     => env('GEMINI_API_KEY'),
        'ollama_url'     => env('OLLAMA_URL', 'http://localhost:11434'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    */
    'embeddings' => [
        'url'   => env('EMBEDDINGS_URL', 'https://openrouter.ai/api/v1'),
        'key'   => env('EMBEDDINGS_KEY', env('OPENROUTER_API_KEY')),
        'model' => env('EMBEDDINGS_MODEL', 'openai/text-embedding-3-small'),
    ],

    /*
    |--------------------------------------------------------------------------
    | External Data Sources
    |--------------------------------------------------------------------------
    */
    'sources' => [
        'core_api_key' => env('CORE_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Go Worker Dispatch (Redis plain-JSON protocol)
    |--------------------------------------------------------------------------
    | These keys are SEPARATE from Laravel's internal Horizon queues.
    | Laravel pushes plain JSON onto kae:run_jobs; Go reads via BLPOP.
    | Go pushes status events onto kae:run_events; Laravel polls via LPOP.
    */
    'worker' => [
        'run_jobs_key'    => env('KAE_RUN_JOBS_KEY', 'kae:run_jobs'),
        'run_events_key'  => env('KAE_RUN_EVENTS_KEY', 'kae:run_events'),
        'binary'          => env('KAE_WORKER_BIN', '/home/mark/kae/kae'),
        'timeout_seconds' => (int) env('KAE_WORKER_TIMEOUT', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Qdrant Collection Names
    |--------------------------------------------------------------------------
    */
    'collections' => [
        'chunks'   => 'kae_chunks',
        'nodes'    => 'kae_nodes',
        'meta'     => 'kae_meta_graph',
        'findings' => 'kae_lens_findings',
    ],

];
