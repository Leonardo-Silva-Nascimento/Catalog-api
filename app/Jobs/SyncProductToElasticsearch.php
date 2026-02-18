<?php
namespace App\Jobs;

use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductToElasticsearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 3;
    public $backoff = [2, 5, 10];

    protected $productData;
    protected $action;

    public function __construct(array $productData, string $action)
    {
        $this->productData = $productData;
        $this->action      = $action;
    }

    public function handle(): void
    {
        $host = 'elasticsearch:9200';

        Log::info('🚀 Job SyncProductToElasticsearch INICIADO', [
            'product_id' => $this->productData['id'],
            'host'       => $host,
            'attempt'    => $this->attempts(),
        ]);

        try {
            $client = ClientBuilder::create()
                ->setHosts([$host])
                ->build();

            $this->ensureIndexExists($client);

            $params = [
                'index' => 'products',
                'id'    => $this->productData['id'],
                'body'  => $this->productData,
            ];

            $response = $client->index($params);

            Log::info('✅ Produto indexado com sucesso no Elasticsearch', [
                'product_id' => $this->productData['id'],
                'result'     => $response['result'] ?? 'ok',
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao indexar produto no Elasticsearch', [
                'product_id' => $this->productData['id'],
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function ensureIndexExists($client): void
    {
        try {
            $client->indices()->exists(['index' => 'products']);
        } catch (\Exception $e) {
            $params = [
                'index' => 'products',
                'body'  => [
                    'mappings' => [
                        'properties' => [
                            'id'          => ['type' => 'keyword'],
                            'name'        => ['type' => 'text'],
                            'description' => ['type' => 'text'],
                            'price'       => ['type' => 'float'],
                            'sku'         => ['type' => 'keyword'],
                            'category'    => ['type' => 'keyword'],
                            'status'      => ['type' => 'keyword'],
                            'image_url'   => ['type' => 'keyword'],
                            'created_at'  => ['type' => 'date'],
                            'updated_at'  => ['type' => 'date'],
                            'deleted_at'  => ['type' => 'date'],
                        ],
                    ],
                ],
            ];

            $client->indices()->create($params);
            Log::info('Índice products criado no Elasticsearch');
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('❌ Job SyncProductToElasticsearch FALHOU PERMANENTEMENTE', [
            'product_id' => $this->productData['id'] ?? null,
            'error'      => $e->getMessage(),
        ]);
    }
}
