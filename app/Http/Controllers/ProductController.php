<?php

namespace App\Http\Controllers;

use App\DTOs\ProductDTO;
use App\Http\Requests\ProductRequest;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\ProductResource;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService
    ) {}

    public function index(SearchRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $products = $this->productService->findAll($filters);
        
        return response()->json([
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ]);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $dto = ProductDTO::fromRequest($request->validated());
        $product = $this->productService->create($dto);
        
        return response()->json([
            'data' => new ProductResource($product),
            'message' => 'Produto criado com sucesso'
        ], Response::HTTP_CREATED);
    }

    public function show(string $id): JsonResponse
    {
        $product = $this->productService->find($id);
        
        if (!$product) {
            return response()->json([
                'error' => 'Produto não encontrado'
            ], Response::HTTP_NOT_FOUND);
        }
        
        return response()->json([
            'data' => new ProductResource($product)
        ]);
    }

    public function update(ProductRequest $request, string $id): JsonResponse
    {
        $product = $this->productService->find($id);
        
        if (!$product) {
            return response()->json([
                'error' => 'Produto não encontrado'
            ], Response::HTTP_NOT_FOUND);
        }
        
        $dto = ProductDTO::fromRequest($request->validated());
        $product = $this->productService->update($id, $dto);
        
        return response()->json([
            'data' => new ProductResource($product),
            'message' => 'Produto atualizado com sucesso'
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $product = $this->productService->find($id);
        
        if (!$product) {
            return response()->json([
                'error' => 'Produto não encontrado'
            ], Response::HTTP_NOT_FOUND);
        }
        
        $this->productService->delete($id);
        
        return response()->json([
            'message' => 'Produto removido com sucesso'
        ], Response::HTTP_NO_CONTENT);
    }

    public function search(SearchRequest $request): JsonResponse
    {
        $params = $request->validated();
        $params['page'] = $request->get('page', 1);
        $params['per_page'] = $request->get('per_page', 15);
        
        $results = $this->productService->search($params);
        
        return response()->json([
            'data' => $results['hits'],
            'meta' => [
                'total' => $results['total'],
                'page' => (int) $params['page'],
                'per_page' => (int) $params['per_page'],
            ]
        ]);
    }

    public function uploadImage(ProductRequest $request, string $id): JsonResponse
    {
        $product = $this->productService->find($id);
        
        if (!$product) {
            return response()->json([
                'error' => 'Produto não encontrado'
            ], Response::HTTP_NOT_FOUND);
        }
        
        if (!$request->hasFile('image')) {
            return response()->json([
                'error' => 'Nenhuma imagem enviada'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $url = $this->productService->uploadImage($id, $request->file('image'));
        
        return response()->json([
            'data' => ['image_url' => $url],
            'message' => 'Imagem enviada com sucesso'
        ]);
    }
}