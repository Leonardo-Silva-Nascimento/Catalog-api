<?php

namespace App\Services\Elasticsearch;

use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Log;

class ElasticsearchService
{
    private Client $client;
    private string $index = 'products';

    public function __construct()
    {
        $this->client = ClientBuilder::create()
            ->setHosts([config('services.elasticsearch.host')])
            ->build();
        
        $this->createIndex();
    }

    public function createIndex(): void
    {
        $params = [
            'index' => $this->index,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'keyword'],
                        'sku' => ['type' => 'keyword'],
                        'name' => ['type' => 'text'],
                        'description' => ['type' => 'text'],
                        'price' => ['type' => 'float'],
                        'category' => ['type' => 'keyword'],
                        'status' => ['type' => 'keyword'],
                        'image_url' => ['type' => 'keyword'],
                        'created_at' => ['type' => 'date'],
                        'updated_at' => ['type' => 'date'],
                    ]
                ]
            ]
        ];

        try {
            if (!$this->client->indices()->exists(['index' => $this->index])) {
                $this->client->indices()->create($params);
            }
        } catch (\Exception $e) {
            Log::error('Failed to create Elasticsearch index: ' . $e->getMessage());
        }
    }

    public function indexDocument(array $document): void
    {
        $params = [
            'index' => $this->index,
            'id' => $document['id'],
            'body' => $document
        ];

        try {
            $this->client->index($params);
        } catch (\Exception $e) {
            Log::error('Failed to index document: ' . $e->getMessage());
        }
    }

    public function updateDocument(string $id, array $document): void
    {
        try {
            $this->client->update([
                'index' => $this->index,
                'id' => $id,
                'body' => ['doc' => $document]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update document: ' . $e->getMessage());
        }
    }

    public function deleteDocument(string $id): void
    {
        try {
            $this->client->delete([
                'index' => $this->index,
                'id' => $id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete document: ' . $e->getMessage());
        }
    }

    public function search(array $params): array
    {
        $must = [];

        if (!empty($params['q'])) {
            $must[] = [
                'multi_match' => [
                    'query' => $params['q'],
                    'fields' => ['name^3', 'description'],
                    'fuzziness' => 'AUTO'
                ]
            ];
        }

        if (!empty($params['category'])) {
            $must[] = ['term' => ['category' => $params['category']]];
        }

        if (!empty($params['status'])) {
            $must[] = ['term' => ['status' => $params['status']]];
        }

        $filter = [];
        if (!empty($params['min_price'])) {
            $filter[] = ['range' => ['price' => ['gte' => $params['min_price']]]];
        }
        if (!empty($params['max_price'])) {
            $filter[] = ['range' => ['price' => ['lte' => $params['max_price']]]];
        }

        $sortField = $params['sort'] ?? 'created_at';
        $sortOrder = $params['order'] ?? 'desc';

        $searchParams = [
            'index' => $this->index,
            'from' => (($params['page'] ?? 1) - 1) * ($params['per_page'] ?? 15),
            'size' => $params['per_page'] ?? 15,
            'body' => [
                'sort' => [
                    [$sortField => ['order' => $sortOrder]]
                ],
                'query' => [
                    'bool' => [
                        'must' => $must,
                        'filter' => $filter
                    ]
                ]
            ]
        ];

        try {
            $response = $this->client->search($searchParams);
            
            return [
                'total' => $response['hits']['total']['value'],
                'hits' => array_column($response['hits']['hits'], '_source')
            ];
        } catch (\Exception $e) {
            Log::error('Search failed: ' . $e->getMessage());
            return ['total' => 0, 'hits' => []];
        }
    }
}