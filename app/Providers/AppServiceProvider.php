<?php

namespace App\Providers;

use App\Contracts\ProductRepositoryInterface;
use App\Models\Product;
use App\Observers\ProductObserver;
use App\Repositories\ProductRepository;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cache;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ProductRepositoryInterface::class,
            ProductRepository::class
        );
    }

    public function boot(): void
    {
        Product::observe(ProductObserver::class);
        
        // Configurar tags para Redis
        Cache::macro('tags', function ($names) {
            return Cache::store('redis')->tags($names);
        });
    }
}