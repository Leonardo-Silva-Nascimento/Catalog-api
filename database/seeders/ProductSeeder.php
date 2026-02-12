<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::factory()
            ->count(50)
            ->active()
            ->create();

        Product::factory()
            ->count(10)
            ->inactive()
            ->create();
    }
}