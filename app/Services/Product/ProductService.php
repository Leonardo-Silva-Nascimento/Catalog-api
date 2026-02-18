<?php
namespace App\Services\Product;

use App\Contracts\ProductRepositoryInterface;
use App\DTOs\ProductDTO;
use App\Jobs\SyncProductToElasticsearch;
use App\Models\Product;
use App\Services\Elasticsearch\ElasticsearchService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function __construct(
        private ProductRepositoryInterface $repository,
        private ElasticsearchService $elasticsearch
    ) {}

    public function create(ProductDTO $dto): Product
    {
        Log::info('📝 ProductService::create - Iniciando', ['dto' => $dto->toArray()]);

        $product = $this->repository->create($dto->toArray());

        Log::info('✅ Produto criado no banco', ['product_id' => $product->id]);

        try {
            Log::info('🚀 Tentando dispatch do job', ['product_id' => $product->id]);

            SyncProductToElasticsearch::dispatch($product->toArray(), 'index');

            Log::info('✅ Job dispatchado com sucesso', ['product_id' => $product->id]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao dispatchar job', [
                'product_id' => $product->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return $product;
    }

    public function update(string $id, ProductDTO $dto): Product
    {
        $product = $this->repository->update($id, $dto->toArray());

        Cache::forget("product.{$id}");

        SyncProductToElasticsearch::dispatch($product->toArray(), 'update');

        Log::info('Product updated', ['id' => $product->id]);

        return $product;
    }

    public function delete(string $id): bool
    {
        $result = $this->repository->delete($id);

        if ($result) {
            Cache::forget("product.{$id}");
            Cache::tags(['product_search'])->flush();

            SyncProductToElasticsearch::dispatch(['id' => $id], 'delete');

            Log::info('Product deleted', ['id' => $id]);
        }

        return $result;
    }

    public function find(string $id): ?Product
    {
        return Cache::remember("product.{$id}", 120, function () use ($id) {
            return $this->repository->find($id);
        });
    }

    public function findAll(array $filters = []): LengthAwarePaginator
    {
        return $this->repository->findAll($filters);
    }

    public function search(array $params): array
    {
        $page     = $params['page'] ?? 1;
        $cacheKey = 'search_' . md5(json_encode($params));

        $must = [];

        if (! empty($params['q'])) {
            $must[] = [
                'multi_match' => [
                    'query'     => $params['q'],
                    'fields'    => ['name^3', 'description'],
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        if (! empty($params['category'])) {
            $must[] = ['term' => ['category' => $params['category']]];
        }

        if (! empty($params['status'])) {
            $must[] = ['term' => ['status' => $params['status']]];
        }

        $filter = [];
        if (! empty($params['min_price'])) {
            $filter[] = ['range' => ['price' => ['gte' => $params['min_price']]]];
        }
        if (! empty($params['max_price'])) {
            $filter[] = ['range' => ['price' => ['lte' => $params['max_price']]]];
        }

        $sortField = $params['sort'] ?? 'created_at';
        $sortOrder = $params['order'] ?? 'desc';

        $searchParams = [
            'index' => 'products',
            'from'  => (($params['page'] ?? 1) - 1) * ($params['per_page'] ?? 15),
            'size'  => $params['per_page'] ?? 15,
            'body'  => [
                'sort'  => [
                    [$sortField => ['order' => $sortOrder]],
                ],
                'query' => [
                    'bool' => [
                        'must'   => $must,
                        'filter' => $filter,
                    ],
                ],
            ],
        ];

        return $this->elasticsearch->search($searchParams);
    }

    public function uploadImage(string $id, $image): ?string
    {
        $product = $this->find($id);

        if (! $product) {
            return null;
        }

        $path = $image->store('products/' . $id, 's3');
        $url  = Storage::disk('s3')->url($path);

        $product->update(['image_url' => $url]);

        Cache::forget("product.{$id}");

        return $url;
    }

    private function publishToSQS(Product $product, string $event): void
    {
        try {
            if (config('queue.default') === 'sqs') {
                // Implementação real SQS
                // dispatch(new PublishProductEvent($product, $event));
            } else {
                // Simulação para desenvolvimento
                Log::info('SQS Message published', [
                    'event'      => $event,
                    'product_id' => $product->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to publish SQS message: ' . $e->getMessage());
        }
    }
}
