<?php

namespace App\DTOs;

class ProductDTO
{
    public function __construct(
        public readonly string $sku,
        public readonly string $name,
        public readonly ?string $description,
        public readonly float $price,
        public readonly ?string $category,
        public readonly string $status = 'active',
        public readonly ?string $image_url = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            sku: $data['sku'],
            name: $data['name'],
            description: $data['description'] ?? null,
            price: (float) $data['price'],
            category: $data['category'] ?? null,
            status: $data['status'] ?? 'active',
            image_url: $data['image_url'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'category' => $this->category,
            'status' => $this->status,
            'image_url' => $this->image_url,
        ];
    }
}