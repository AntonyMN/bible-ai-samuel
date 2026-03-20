<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class VectorStoreService
{
    protected $baseUrl;
    protected $tenant = 'default_tenant';
    protected $database = 'default_database';

    public function __construct()
    {
        $this->baseUrl = env('CHROMADB_URL', 'http://localhost:8000');
    }

    protected function getCollectionId(string $name)
    {
        return Cache::remember("chroma_collection_id_{$name}", 3600, function () use ($name) {
            $response = Http::throw()->get("{$this->baseUrl}/api/v2/tenants/{$this->tenant}/databases/{$this->database}/collections", [
                'name' => $name
            ]);
            
            $collections = $response->json();
            if (empty($collections) || !is_array($collections)) return null;
            
            foreach ($collections as $c) {
                if (isset($c['name']) && $c['name'] === $name) {
                    return $c['id'];
                }
            }
            
            return null;
        });
    }

    public function query(string $collection, array $queryEmbeddings, int $nResults = 5)
    {
        $id = $this->getCollectionId($collection);
        if (!$id) return [];

        $response = Http::timeout(10)->post("{$this->baseUrl}/api/v2/tenants/{$this->tenant}/databases/{$this->database}/collections/{$id}/query", [
            'query_embeddings' => $queryEmbeddings,
            'n_results' => $nResults,
        ]);

        return $response->json();
    }

    public function addDocuments(string $collection, array $documents, array $metadatas, array $ids, array $embeddings)
    {
        $id = $this->getCollectionId($collection);
        
        if (!$id) {
            $this->createCollection($collection);
            Cache::forget("chroma_collection_id_{$collection}");
            $id = $this->getCollectionId($collection);
        }

        if (!$id) return null;

        $response = Http::timeout(180)->post("{$this->baseUrl}/api/v2/tenants/{$this->tenant}/databases/{$this->database}/collections/{$id}/upsert", [
            'documents' => $documents,
            'metadatas' => $metadatas,
            'ids' => $ids,
            'embeddings' => $embeddings,
        ]);

        return $response;
    }

    public function createCollection(string $name)
    {
        return Http::throw()->post("{$this->baseUrl}/api/v2/tenants/{$this->tenant}/databases/{$this->database}/collections", [
            'name' => $name,
        ]);
    }

    public function count(string $collection = 'bible_verses')
    {
        try {
            $id = $this->getCollectionId($collection);
            if (!$id) return 0;

            $response = Http::throw()->get("{$this->baseUrl}/api/v2/tenants/{$this->tenant}/databases/{$this->database}/collections/{$id}/count");
            return $response->json();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
