<?php

namespace Tests\Feature\Product;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function it_can_create_a_product()
    {
        $payload = [
            'sku' => 'SKU-123',
            'name' => 'Notebook Gamer',
            'description' => 'Notebook de alta performance',
            'price' => 4999.90,
            'category' => 'Eletrônicos',
            'status' => 'active'
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'sku', 'name', 'price'],
                'message'
            ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'SKU-123',
            'name' => 'Notebook Gamer'
        ]);
    }

    /** @test */
    public function it_validates_sku_uniqueness()
    {
        Product::factory()->create(['sku' => 'SKU-123']);

        $payload = [
            'sku' => 'SKU-123',
            'name' => 'Outro Produto',
            'price' => 99.90
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    /** @test */
    public function it_validates_name_minimum_length()
    {
        $payload = [
            'sku' => 'SKU-123',
            'name' => 'AB',
            'price' => 99.90
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function it_can_update_a_product()
    {
        $product = Product::factory()->create();

        $payload = [
            'name' => 'Nome Atualizado',
            'price' => 299.90
        ];

        $response = $this->putJson("/api/products/{$product->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Nome Atualizado')
            ->assertJsonPath('data.price', 299.90);
    }

    /** @test */
    public function it_can_delete_a_product()
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(204);
        
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    /** @test */
    public function it_caches_product_on_show()
    {
        $product = Product::factory()->create();

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn($product);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $product->id);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_product()
    {
        $response = $this->getJson('/api/products/non-existent-id');

        $response->assertStatus(404);
    }
}