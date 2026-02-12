<?php

namespace App\Jobs;

use App\Services\Elasticsearch\ElasticsearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProductToElasticsearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private array $product,
        private string $action
    ) {}

    public function handle(ElasticsearchService $elasticsearch): void
    {
        switch ($this->action) {
            case 'index':
            case 'update':
                $elasticsearch->indexDocument($this->product);
                break;
            case 'delete':
                $elasticsearch->deleteDocument($this->product['id']);
                break;
        }
    }
}