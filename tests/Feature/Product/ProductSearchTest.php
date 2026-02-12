<?php

namespace Tests\Feature\Product;

use App\Models\Product;
use App\Services\Elasticsearch\ElasticsearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_search_products_with_filters()
    {
        // Criar produtos no MySQL para sincronização
        Product::factory()->create([
            'name' => 'Smartphone Samsung',
            'category' => 'Eletrônicos',
            'price' => 1999.90,
            'status' => 'active'
        ]);

        Product::factory()->create([
            'name' => 'Smartphone Apple',
            'category' => 'Eletrônicos',
            'price' => 4999.90,
            'status' => 'active'
        ]);

        Product::factory()->create([
            'name' => 'Livro PHP',
            'category' => 'Livros',
            'price' => 89.90,
            'status' => 'active'
        ]);

        // Mock do Elasticsearch para teste
        $mockElastic = Mockery::mock(ElasticsearchService::class);
        $mockElastic->shouldReceive('search')
            ->once()
            ->andReturn([
                'total' => 2,
                'hits' => [
                    ['id' => 1, 'name' => 'Smartphone Samsung'],
                    ['id' => 2, 'name' => 'Smartphone Apple']
                ]
            ]);

        $this->app->instance(ElasticsearchService::class, $mockElastic);

        // Testar busca
        $response = $this->getJson('/api/search/products?q=smartphone&category=Eletrônicos&min_price=1000&max_price=5000&sort=price&order=asc');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['total', 'page', 'per_page']
            ]);
    }

    /** @test */
    public function it_returns_empty_results_for_no_matches()
    {
        $mockElastic = Mockery::mock(ElasticsearchService::class);
        $mockElastic->shouldReceive('search')
            ->once()
            ->andReturn([
                'total' => 0,
                'hits' => []
            ]);

        $this->app->instance(ElasticsearchService::class, $mockElastic);

        $response = $this->getJson('/api/search/products?q=xyz123notfound');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }
}