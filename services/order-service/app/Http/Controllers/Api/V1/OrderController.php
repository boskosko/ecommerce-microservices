<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\OrderCancelledEvent;
use App\Events\OrderCreatedEvent;
use App\Events\OrderUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductCache;
use App\Services\RabbitMQService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Get all orders with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with('items');

        // Filter by user_id
        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search by order number
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('order_number', 'like', "%{$search}%");
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->input('per_page', 15);
        $orders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'user_id' => $order->user_id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_amount' => $order->total_amount,
                    'shipping_address' => $order->shipping_address,
                    'notes' => $order->notes,
                    'items_count' => $order->items->count(),
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ];
            }),
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
        ]);
    }

    /**
     * Create a new order
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_address' => 'required|array',
            'shipping_address.street' => 'required|string',
            'shipping_address.city' => 'required|string',
            'shipping_address.postal_code' => 'required|string',
            'shipping_address.country' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Validate products and calculate total
            $totalAmount = 0;
            $orderItems = [];

            foreach ($validated['items'] as $item) {
                $product = ProductCache::find($item['product_id']);

                if (!$product) {
                    return response()->json([
                        'success' => false,
                        'message' => "Product {$item['product_id']} not found",
                    ], 404);
                }

                if (!$product->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => "Product {$product->name} is not available",
                    ], 400);
                }

                if ($product->stock < $item['quantity']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for {$product->name}. Available: {$product->stock}",
                    ], 400);
                }

                $subtotal = $product->price * $item['quantity'];
                $totalAmount += $subtotal;

                $orderItems[] = [
                    'product_id' => $product->product_id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'product_price' => $product->price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal,
                ];
            }

            // Create order
            $order = Order::create([
                'user_id' => $validated['user_id'],
                'total_amount' => $totalAmount,
                'shipping_address' => $validated['shipping_address'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create order items
            foreach ($orderItems as $itemData) {
                $order->items()->create($itemData);
            }

            DB::commit();

            // Publish order.created event to RabbitMQ (after commit, before response)
            try {
                $rabbitMQ = new RabbitMQService();
                $rabbitMQ->declareExchange('order.events', 'topic');
                $event = new OrderCreatedEvent($order->load('items'));
                $rabbitMQ->publish('order.events', 'order.created', $event->toArray());
                // Connection is closed automatically in publish() method
            } catch (\Exception $e) {
                Log::error('Failed to publish order.created event: ' . $e->getMessage());
            }

            // TODO: Decrease product stock via Product Service API

            if (ob_get_level()) {
                ob_end_flush();
            }

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'user_id' => $order->user_id,
                    'status' => $order->status,
                    'total_amount' => $order->total_amount,
                    'shipping_address' => $order->shipping_address,
                    'notes' => $order->notes,
                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name,
                            'product_sku' => $item->product_sku,
                            'product_price' => $item->product_price,
                            'quantity' => $item->quantity,
                            'subtotal' => $item->subtotal,
                        ];
                    }),
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single order
     */
    public function show(string $id): JsonResponse
    {
        $order = Order::with('items')->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'user_id' => $order->user_id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'shipping_address' => $order->shipping_address,
                'notes' => $order->notes,
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'product_sku' => $item->product_sku,
                        'product_price' => $item->product_price,
                        'quantity' => $item->quantity,
                        'subtotal' => $item->subtotal,
                    ];
                }),
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ],
        ]);
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
        ]);

        $order->update(['status' => $validated['status']]);

        // Publish order.updated event to RabbitMQ
        try {
            $rabbitMQ = new RabbitMQService();
            $rabbitMQ->declareExchange('order.events', 'topic');
            $event = new OrderUpdatedEvent($order);
            $rabbitMQ->publish('order.events', 'order.updated', $event->toArray());
        } catch (\Exception $e) {
            Log::error('Failed to publish order.updated event: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'updated_at' => $order->updated_at,
            ],
        ]);
    }

    /**
     * Cancel an order
     */
    public function cancel(string $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        if ($order->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Order is already cancelled',
            ], 400);
        }

        if (in_array($order->status, ['shipped', 'delivered'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel order in ' . $order->status . ' status',
            ], 400);
        }

        $order->update(['status' => 'cancelled']);

        // Publish order.cancelled event to RabbitMQ
        try {
            $rabbitMQ = new RabbitMQService();
            $rabbitMQ->declareExchange('order.events', 'topic');
            $event = new OrderCancelledEvent($order->load('items'));
            $rabbitMQ->publish('order.events', 'order.cancelled', $event->toArray());
        } catch (\Exception $e) {
            Log::error('Failed to publish order.cancelled event: ' . $e->getMessage());
        }
        // TODO: Restore product stock via Product Service API

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'updated_at' => $order->updated_at,
            ],
        ]);
    }
}
