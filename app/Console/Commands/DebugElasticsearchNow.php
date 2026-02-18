<?php
namespace App\Console\Commands;

use App\Models\Product;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;

class DebugElasticsearchNow extends Command
{
    protected $signature   = 'debug:es-now';
    protected $description = 'Debug Elasticsearch immediately';

    public function handle()
    {
        $this->info('=== DEBUG ELASTICSEARCH IMEDIATO ===');

        $this->info('1. Configuração:');
        $this->line("   Host: " . env('ELASTICSEARCH_HOST', 'elasticsearch'));
        $this->line("   Port: " . env('ELASTICSEARCH_PORT', '9200'));

        $product = Product::first();
        if (! $product) {
            $this->error('Nenhum produto encontrado!');
            return 1;
        }
        $this->info("2. Produto encontrado: {$product->id} - {$product->name}");

        $hosts = [
            'elasticsearch:9200',
            'catalog-elastic:9200',
            '172.18.0.5:9200',
            '127.0.0.1:9200',
            'localhost:9200',
        ];

        $workingHost = null;

        foreach ($hosts as $host) {
            $this->line("\n🔍 Testando host: {$host}");

            try {
                $this->line("   📡 Teste curl...");
                $ch = curl_init("http://{$host}/");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error    = curl_error($ch);
                curl_close($ch);

                if ($httpCode == 200) {
                    $this->info("   ✅ Curl OK (HTTP {$httpCode})");
                } else {
                    $this->error("   ❌ Curl falhou: {$error}");
                    continue;
                }

                $this->line("   🔧 Teste cliente Elasticsearch...");
                $client = ClientBuilder::create()
                    ->setHosts([$host])
                    ->build();

                $info = $client->info();
                $this->info("   ✅ Cliente OK - Versão: " . $info['version']['number']);

                $this->line("   📝 Teste indexação...");
                $testIndex = 'test-' . date('Ymd');

                try {
                    $client->indices()->create([
                        'index' => $testIndex,
                        'body'  => [
                            'mappings' => [
                                'properties' => [
                                    'name'      => ['type' => 'text'],
                                    'sku'       => ['type' => 'keyword'],
                                    'tested_at' => ['type' => 'date'],
                                ],
                            ],
                        ],
                    ]);
                } catch (error) {}

                $params = [
                    'index' => $testIndex,
                    'id'    => $product->id,
                    'body'  => [
                        'name'      => $product->name,
                        'sku'       => $product->sku,
                        'tested_at' => now()->toIso8601String(),
                    ],
                ];

                $response = $client->index($params);
                $this->info("   ✅ Indexação OK: " . ($response['result'] ?? 'ok'));

                $this->info("\n✨✨ HOST FUNCIONAL: {$host} ✨✨");
                $workingHost = $host;

                $getParams = [
                    'index' => $testIndex,
                    'id'    => $product->id,
                ];

                $doc = $client->get($getParams);
                $this->info("   📄 Documento recuperado: " . json_encode($doc['_source']));

                try {
                    $client->indices()->delete(['index' => $testIndex]);
                    $this->line("   🧹 Índice de teste removido");
                } catch (\Exception $e) {
                }

                break; // Encontrou um host funcionando, pode parar

            } catch (\Exception $e) {
                $this->error("   ❌ Erro: " . $e->getMessage());
            }
        }

        if ($workingHost) {
            $this->info("\n🎉 SUCESSO! Host que funciona: {$workingHost}");
            $this->warn("\n📝 Use este host no seu Job:");
            $this->line("   \$host = '{$workingHost}';");

            // Teste final com o produto real
            $this->line("\n🔄 Testando indexação do produto real...");
            try {
                $client = ClientBuilder::create()
                    ->setHosts([$workingHost])
                    ->build();

                $params = [
                    'index' => 'products',
                    'id'    => $product->id,
                    'body'  => $product->toArray(),
                ];

                $response = $client->index($params);
                $this->info("   ✅ Produto real indexado: " . ($response['result'] ?? 'ok'));

            } catch (\Exception $e) {
                $this->error("   ❌ Erro ao indexar produto real: " . $e->getMessage());
            }
        } else {
            $this->error("\n❌ Nenhum host funcionou!");
        }

        return 0;
    }
}
