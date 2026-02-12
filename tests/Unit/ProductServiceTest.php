<?php

namespace Tests\Unit;

use App\Contracts\ProductRepositoryInterface;
use App\DTOs\ProductDTO;
use App\Models\Product;
use App\Services\Elasticsearch\ElasticsearchService;
use App\Services\Product\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductService $service;
    private $repository;
    private $elasticsearch;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repository = Mockery::mock(ProductRepositoryInterface::class);
        $this->elasticsearch = Mockery::mock(ElasticsearchService::class);
        
        $this->service = new ProductService(
            $this->repository,
            $this->elasticsearch
        );
    }

    /** @test */
    public function it_should_create_a_product()
    {
        // Arrange
        $dto = new ProductDTO(
            sku: 'SKU-123',
            name: 'Produto Teste',
            description: 'Descrição',
            price: 99.90,
            category: 'Eletrônicos'
        );

        $product = new Product([
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'sku' => 'SKU-123',
            'name' => 'Produto Teste',
            'description' => 'Descrição',
            'price' => 99.90,
            'category' => 'Eletrônicos',
            'status' => 'active'
        ]);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with($dto->toArray())
            ->andReturn($product);

        $this->elasticsearch
            ->shouldReceive('indexDocument')
            ->once();

        // Act
        $result = $this->service->create($dto);

        // Assert
        $this->assertEquals($product->id, $result->id);
        $this->assertEquals($product->sku, $result->sku);
    }

    /** @test */
    public function it_should_cache_product_when_finding_by_id()
    {
        // Arrange
        $id = '123e4567-e89b-12d3-a456-426614174000';
        $product = Product::factory()->make(['id' => $id]);

        Cache::shouldReceive('remember')
            ->once()
            ->with("product.{$id}", 120, Mockery::on(function ($callback) use ($product) {
                return $callback() === $product;
            }))
            ->andReturn($product);

        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($product);

        // Act
        $result = $this->service->find($id);

        // Assert
        $this->assertEquals($product, $result);
    }

    /** @test */
    public function it_should_invalidate_cache_when_updating_product()
    {
        // Arrange
        $id = '123e4567-e89b-12d3-a456-426614174000';
        $dto = new ProductDTO(
            sku: 'SKU-456',
            name: 'Produto Atualizado',
            description: 'Nova descrição',
            price: 149.90,
            category: 'Casa'
        );

        $product = new Product(['id' => $id]);
        $updatedProduct = new Product([
            'id' => $id,
            'sku' => 'SKU-456',
            'name' => 'Produto Atualizado'
        ]);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($id, $dto->toArray())
            ->andReturn($updatedProduct);

        Cache::shouldReceive('forget')
            ->once()
            ->with("product.{$id}");

        Cache::shouldReceive('tags')
            ->once()
            ->with(['product_search'])
            ->andReturnSelf();
        
        Cache::shouldReceive('flush')
            ->once();

        $this->elasticsearch
            ->shouldReceive('updateDocument')
            ->once();

        // Act
        $result = $this->service->update($id, $dto);

        // Assert
        $this->assertEquals($updatedProduct, $result);
    }
}