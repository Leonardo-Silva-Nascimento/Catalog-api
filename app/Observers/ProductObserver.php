<?php

namespace App\Observers;

use App\Models\Product;
use App\Jobs\SyncProductToElasticsearch;

class ProductObserver
{
    public function created(Product $product): void
    {
        SyncProductToElasticsearch::dispatch($product->toArray(), 'index');
    }

    public function updated(Product $product): void
    {
        SyncProductToElasticsearch::dispatch($product->toArray(), 'update');
    }

    public function deleted(Product $product): void
    {
        if (!$product->isForceDeleting()) {
            SyncProductToElasticsearch::dispatch(['id' => $product->id], 'delete');
        }
    }

    public function restored(Product $product): void
    {
        SyncProductToElasticsearch::dispatch($product->toArray(), 'index');
    }
}