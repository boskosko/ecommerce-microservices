<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\RabbitMQService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;

class ConsumeOrderEvents extends Command
{
    protected $signature = 'rabbitmq:consume-order-events';
    protected $description = 'Consume order events from RabbitMQ and update product stock';

    public function handle()
    {
        $this->info('Starting Order Events Consumer...');

        $rabbitMQ = new RabbitMQService();

        // Declare exchange
        $rabbitMQ->declareExchange('order.events', 'topic');

        // Declare queue and bind to exchange
        $queueName = 'product-service.order-events';
        $routingKeys = ['order.created', 'order.cancelled'];
        $rabbitMQ->declareQueueAndBind($queueName, 'order.events', $routingKeys);

        $this->info("Listening on queue: {$queueName}");
        $this->info("Routing keys: " . implode(', ', $routingKeys));

        // Consume messages
        $callback = function (AMQPMessage $msg) {
            try {
                $data = json_decode($msg->body, true);
                $event = $data['event'] ?? null;
                $orderData = $data['data'] ?? null;

                $this->info("Received event: {$event}");

                switch ($event) {
                    case 'order.created':
                        $this->handleOrderCreated($orderData);
                        break;

                    case 'order.cancelled':
                        $this->handleOrderCancelled($orderData);
                        break;

                    default:
                        $this->warn("Unknown event: {$event}");
                }

                // Acknowledge message
                $msg->ack();
            } catch (\Exception $e) {
                $this->error('Error processing message: ' . $e->getMessage());
                Log::error('Order event processing error: ' . $e->getMessage());
                // Reject and requeue message
                $msg->nack(true);
            }
        };

        $rabbitMQ->consume($queueName, $callback);
    }

    private function handleOrderCreated($orderData)
    {
        if (!isset($orderData['items']) || !is_array($orderData['items'])) {
            $this->error('Order items not found in event data');
            return;
        }

        foreach ($orderData['items'] as $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = $item['quantity'] ?? 0;

            if (!$productId || $quantity <= 0) {
                continue;
            }

            $product = Product::find($productId);

            if (!$product) {
                $this->warn("Product not found: {$productId}");
                Log::warning("Product not found for stock decrement", ['product_id' => $productId]);
                continue;
            }

            // Decrease stock
            $oldStock = $product->stock;
            $product->stock -= $quantity;

            if ($product->stock < 0) {
                $this->warn("Stock went negative for product: {$product->name} (ID: {$productId})");
                Log::warning('Stock went negative', [
                    'product_id' => $productId,
                    'product_name' => $product->name,
                    'old_stock' => $oldStock,
                    'quantity' => $quantity,
                    'new_stock' => $product->stock
                ]);
            }

            $product->save();

            $this->info("Stock updated: {$product->name} ({$oldStock} → {$product->stock})");
            Log::info('Stock decreased', [
                'product_id' => $productId,
                'product_name' => $product->name,
                'order_id' => $orderData['id'] ?? null,
                'quantity' => $quantity,
                'old_stock' => $oldStock,
                'new_stock' => $product->stock
            ]);
        }
    }

    private function handleOrderCancelled($orderData)
    {
        if (!isset($orderData['items']) || !is_array($orderData['items'])) {
            $this->error('Order items not found in event data');
            return;
        }

        foreach ($orderData['items'] as $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = $item['quantity'] ?? 0;

            if (!$productId || $quantity <= 0) {
                continue;
            }

            $product = Product::find($productId);

            if (!$product) {
                $this->warn("Product not found: {$productId}");
                Log::warning("Product not found for stock restore", ['product_id' => $productId]);
                continue;
            }

            // Restore stock
            $oldStock = $product->stock;
            $product->stock += $quantity;
            $product->save();

            $this->info("Stock restored: {$product->name} ({$oldStock} → {$product->stock})");
            Log::info('Stock restored', [
                'product_id' => $productId,
                'product_name' => $product->name,
                'order_id' => $orderData['id'] ?? null,
                'quantity' => $quantity,
                'old_stock' => $oldStock,
                'new_stock' => $product->stock
            ]);
        }
    }
}
