<?php

namespace App\Contracts;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function create(array $data): Product;
    public function update(string $id, array $data): Product;
    public function delete(string $id): bool;
    public function find(string $id): ?Product;
    public function findAll(array $filters = []): LengthAwarePaginator;
}