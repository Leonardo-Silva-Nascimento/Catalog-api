<?php

namespace App\Repositories;

use App\Contracts\ProductRepositoryInterface;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository implements ProductRepositoryInterface
{
    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(string $id, array $data): Product
    {
        $product = $this->find($id);
        $product->update($data);
        return $product->fresh();
    }

    public function delete(string $id): bool
    {
        $product = $this->find($id);
        return $product->delete();
    }

    public function find(string $id): ?Product
    {
        return Product::find($id);
    }

    public function findAll(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query();

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        $sortField = $filters['sort'] ?? 'created_at';
        $sortOrder = $filters['order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        return $query->paginate($filters['per_page'] ?? 15);
    }
}