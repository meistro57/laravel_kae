<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QdrantService
{
    private string $baseUrl;
    private array $headers;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('kae.qdrant.url'), '/');
        $apiKey = config('kae.qdrant.api_key');

        $this->headers = array_filter([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'api-key'       => $apiKey ?: null,
        ]);
    }

    /**
     * Scroll through a collection with optional payload filter.
     *
     * @param  string  $collection   Qdrant collection name
     * @param  array   $filter       Qdrant filter DSL (e.g. ['must' => [...]])
     * @param  int     $limit        Max points per page
     * @param  mixed   $offset       Pagination offset (point ID or null)
     * @param  bool    $withPayload  Include payload fields
     * @param  bool    $withVectors  Include vectors (expensive — default false)
     * @return array{points: array, next_page_offset: mixed}|null
     */
    public function scroll(
        string $collection,
        array $filter = [],
        int $limit = 100,
        mixed $offset = null,
        bool $withPayload = true,
        bool $withVectors = false,
    ): ?array {
        $body = [
            'limit'        => $limit,
            'with_payload' => $withPayload,
            'with_vector'  => $withVectors,
        ];

        if (! empty($filter)) {
            $body['filter'] = $filter;
        }

        if ($offset !== null) {
            $body['offset'] = $offset;
        }

        $response = $this->post("/collections/{$collection}/points/scroll", $body);

        return $response ? $response['result'] : null;
    }

    /**
     * Fetch a single point from a collection. Useful for contract tests.
     *
     * @return array{id: mixed, payload: array, vector: mixed}|null
     */
    public function scrollOne(string $collection): ?array
    {
        $result = $this->scroll($collection, limit: 1);

        return $result['points'][0] ?? null;
    }

    /**
     * Get metadata for a collection (point count, vector config, etc.).
     *
     * @return array|null  Qdrant collection info result object, or null on error
     */
    public function getCollection(string $collection): ?array
    {
        $response = $this->get("/collections/{$collection}");

        return $response ? $response['result'] : null;
    }

    /**
     * List all collections.
     *
     * @return array<string>  Collection names
     */
    public function listCollections(): array
    {
        $response = $this->get('/collections');

        if (! $response) {
            return [];
        }

        return array_column($response['result']['collections'] ?? [], 'name');
    }

    // ── Stubs for Phase 2 ────────────────────────────────────────────────────

    /**
     * Upsert points into a collection.
     * @stub Phase 2 — sync jobs will implement this
     */
    public function upsert(string $collection, array $points): bool
    {
        throw new \RuntimeException('QdrantService::upsert() not implemented until Phase 2');
    }

    /**
     * Delete points by filter or IDs.
     * @stub Phase 2
     */
    public function delete(string $collection, array $filter): bool
    {
        throw new \RuntimeException('QdrantService::delete() not implemented until Phase 2');
    }

    /**
     * Vector similarity search.
     * @stub Phase 3 — Curator synthesis will implement this
     */
    public function search(string $collection, array $vector, int $topK = 10, array $filter = []): array
    {
        throw new \RuntimeException('QdrantService::search() not implemented until Phase 3');
    }

    // ── Private HTTP helpers ─────────────────────────────────────────────────

    private function get(string $path): ?array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->timeout(10)
                ->get($this->baseUrl . $path);

            if ($response->failed()) {
                Log::warning('QdrantService GET failed', [
                    'path'   => $path,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return null;
            }

            return $response->json();
        } catch (ConnectionException $e) {
            Log::error('QdrantService connection error', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function post(string $path, array $body): ?array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->timeout(30)
                ->post($this->baseUrl . $path, $body);

            if ($response->failed()) {
                Log::warning('QdrantService POST failed', [
                    'path'   => $path,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return null;
            }

            return $response->json();
        } catch (ConnectionException $e) {
            Log::error('QdrantService connection error', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
