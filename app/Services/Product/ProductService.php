<?php

namespace App\Services\Product;

use App\Contracts\ProductRepositoryInterface;
use App\DTOs\ProductDTO;
use App\Models\Product;
use App\Services\Elasticsearch\ElasticsearchService;
use App\Jobs\SyncProductToElasticsearch;
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
        $product = $this->repository->create($dto->toArray());
        
        // Sincronizar com Elasticsearch (async)
        SyncProductToElasticsearch::dispatch($product->toArray(), 'index');
        
        // Publicar mensagem SQS
        $this->publishToSQS($product, 'created');
        
        Log::info('Product created', ['id' => $product->id, 'sku' => $product->sku]);
        
        return $product;
    }

    public function update(string $id, ProductDTO $dto): Product
    {
        $product = $this->repository->update($id, $dto->toArray());
        
        // Invalidar cache
        Cache::forget("product.{$id}");
        Cache::tags(['product_search'])->flush();
        
        // Sincronizar com Elasticsearch
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
        // Não cachear paginação alta
        $page = $params['page'] ?? 1;
        $cacheKey = 'search_' . md5(json_encode($params));
        
        if ($page <= 50) {
            return Cache::tags(['product_search'])->remember($cacheKey, 60, function () use ($params) {
                return $this->elasticsearch->search($params);
            });
        }
        
        return $this->elasticsearch->search($params);
    }

    public function uploadImage(string $id, $image): ?string
    {
        $product = $this->find($id);
        
        if (!$product) {
            return null;
        }

        $path = $image->store('products/' . $id, 's3');
        $url = Storage::disk('s3')->url($path);
        
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
                    'event' => $event,
                    'product_id' => $product->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to publish SQS message: ' . $e->getMessage());
        }
    }
}