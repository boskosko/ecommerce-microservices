<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Events\ProductCreatedEvent;
use App\Events\ProductUpdatedEvent;
use App\Events\ProductDeletedEvent;
use App\Services\RabbitMQService;

class ProductController extends Controller
{
    /**
     * Get all products with pagination, search, and filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        // Search by name or description
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        // Filter by stock availability
        if ($request->has('in_stock')) {
            $inStock = filter_var($request->input('in_stock'), FILTER_VALIDATE_BOOLEAN);
            if ($inStock) {
                $query->where('stock', '>', 0);
            }
        }

        // Filter active products only
        if ($request->has('active_only')) {
            $query->where('is_active', true);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->input('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products->map(function ($product) {
                return [
                    'id' => (string) $product->_id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'stock' => $product->stock,
                    'category' => $product->category,
                    'sku' => $product->sku,
                    'images' => $product->images ?? [],
                    'is_active' => $product->is_active,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ];
            }),
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ]);
    }

    /**
     * Create a new product
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category' => 'required|string|max:100',
            'sku' => 'nullable|string|unique:products,sku',
            'images' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['sku'])) {
            $validated['sku'] = 'PROD-' . strtoupper(Str::random(8));
        }

        $product = Product::create($validated);

        // Publish event to RabbitMQ
        try {
            $rabbitMQ = new RabbitMQService();
            $event = new ProductCreatedEvent($product);
            $rabbitMQ->publish('product.events', 'product.created', $event->toArray());
        } catch (\Exception $e) {
            Log::error('Failed to publish product.created event: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'product' => [
                'id' => (string) $product->_id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'stock' => $product->stock,
                'category' => $product->category,
                'sku' => $product->sku,
                'images' => $product->images ?? [],
                'is_active' => $product->is_active,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ],
        ], 201);
    }

    /**
     * Get a single product
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'product' => [
                'id' => (string) $product->_id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'stock' => $product->stock,
                'category' => $product->category,
                'sku' => $product->sku,
                'images' => $product->images ?? [],
                'is_active' => $product->is_active,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ],
        ]);
    }

    /**
     * Update a product
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'category' => 'sometimes|string|max:100',
            'sku' => 'sometimes|string|unique:products,sku,' . $id,
            'images' => 'nullable|array',
            'images.*' => 'string',
            'is_active' => 'boolean',
        ]);

        $product->update($validated);
        $product = $product->fresh();

        // Publish event to RabbitMQ
        try {
            $rabbitMQ = new RabbitMQService();
            $event = new ProductUpdatedEvent($product);
            $rabbitMQ->publish('product.events', 'product.updated', $event->toArray());
        } catch (\Exception $e) {
            Log::error('Failed to publish product.updated event: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'product' => [
                'id' => (string) $product->_id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'stock' => $product->stock,
                'category' => $product->category,
                'sku' => $product->sku,
                'images' => $product->images ?? [],
                'is_active' => $product->is_active,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ],
        ]);
    }

    /**
     * Delete a product
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        // Save data before deleting
        $productId = (string) $product->_id;
        $sku = $product->sku;

        $product->delete();

        // Publish event to RabbitMQ
        try {
            $rabbitMQ = new RabbitMQService();
            $event = new ProductDeletedEvent($productId, $sku);
            $rabbitMQ->publish('product.events', 'product.deleted', $event->toArray());
        } catch (\Exception $e) {
            Log::error('Failed to publish product.deleted event: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }
}
