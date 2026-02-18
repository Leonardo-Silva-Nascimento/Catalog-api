<?php
namespace App\Services\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\Facades\Log;

class ElasticsearchService
{
    private Client $client;
    private string $index = 'products';

    public function __construct()
    {
        $host = 'elasticsearch:9200';

        Log::info('🔧 Inicializando ElasticsearchService', [
            'host'     => $host,
            'host_env' => env('ELASTICSEARCH_HOST'),
            'port_env' => env('ELASTICSEARCH_PORT'),
        ]);

        try {
            $this->client = ClientBuilder::create()
                ->setHosts([$host])
                ->build();

            $info = $this->client->info();
            Log::info('✅ Conexão Elasticsearch OK', [
                'version' => $info['version']['number'],
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Falha ao conectar Elasticsearch', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function createIndex(): void
    {
        $params = [
            'index' => $this->index,
            'body'  => [
                'settings' => [
                    'number_of_shards'   => 1,
                    'number_of_replicas' => 0,
                ],
                'mappings' => [
                    'properties' => [
                        'id'          => ['type' => 'keyword'],
                        'sku'         => ['type' => 'keyword'],
                        'name'        => ['type' => 'text'],
                        'description' => ['type' => 'text'],
                        'price'       => ['type' => 'float'],
                        'category'    => ['type' => 'keyword'],
                        'status'      => ['type' => 'keyword'],
                        'image_url'   => ['type' => 'keyword'],
                        'created_at'  => ['type' => 'date'],
                        'updated_at'  => ['type' => 'date'],
                    ],
                ],
            ],
        ];

        try {
            if (! $this->client->indices()->exists(['index' => $this->index])) {
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
            'id'    => $document['id'],
            'body'  => $document,
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
                'id'    => $id,
                'body'  => ['doc' => $document],
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
                'id'    => $id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete document: ' . $e->getMessage());
        }
    }

    public function search(array $params): array
    {
        try {
            Log::info('📦 Parâmetros da busca:', $params);

            $response = $this->client->search($params);

            return [
                'total' => $response['hits']['total']['value'],
                'hits'  => array_column($response['hits']['hits'], '_source'),
            ];

        } catch (\Exception $e) {
            Log::error('❌ Erro no Elasticsearch:', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return ['total' => 0, 'hits' => []];
        }
    }
}
