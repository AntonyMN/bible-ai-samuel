<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class VectorStoreService
{
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('CHROMADB_URL', 'http://localhost:8000');
    }

    public function query(string $collection, array $queryEmbeddings, int $nResults = 5)
    {
        $response = Http::timeout(3)->post("{$this->baseUrl}/api/v1/collections/{$collection}/query", [
            'query_embeddings' => $queryEmbeddings,
            'n_results' => $nResults,
        ]);

        return $response->json();
    }

    public function addDocuments(string $collection, array $documents, array $metadatas, array $ids, array $embeddings)
    {
        $response = Http::post("{$this->baseUrl}/api/v1/collections/{$collection}/add", [
            'documents' => $documents,
            'metadatas' => $metadatas,
            'ids' => $ids,
            'embeddings' => $embeddings,
        ]);

        return $response;
    }

    public function createCollection(string $name)
    {
        return Http::post("{$this->baseUrl}/api/v1/collections", [
            'name' => $name,
        ]);
    }
}
