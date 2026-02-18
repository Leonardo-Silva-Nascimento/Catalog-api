# 📦 Catalog API - Laravel Challenge

API REST para gerenciamento de catálogo de produtos com busca avançada, cache e integrações.

## 🚀 Tecnologias

- PHP 8.3
- Laravel 10
- MySQL 8.0
- Redis 7
- Elasticsearch 8
- Docker/Sail
- PHPUnit

## 📋 Pré-requisitos

- Docker
- Docker Compose
- Git

## ⚙️ Instalação

```bash
# 1. Clone o repositório
git clone
cd catalog-api

# 2. Configure o ambiente
cp .env.example .env

# 3. Inicie os containers
./vendor/bin/sail up -d

# 4. Instale as dependências
./vendor/bin/sail composer install

# 5. Gere a key da aplicação
./vendor/bin/sail artisan key:generate

# 6. Execute as migrations
./vendor/bin/sail artisan migrate

# 7. Popule o banco com dados de teste
./vendor/bin/sail artisan db:seed --class=ProductSeeder

# 8. Crie o índice no Elasticsearch
./vendor/bin/sail artisan elastic:create-index

# 9. Sincronize produtos com Elasticsearch
./vendor/bin/sail artisan elastic:sync

# 10. Execute os testes
./vendor/bin/sail artisan test

# 11. Deixe a fila executnado
./vendor/bin/sail exec laravel.test php artisan queue:work redis --tries=3 --verbose