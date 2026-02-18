<?php

namespace App\Console\Commands;

use App\Models\ProductCache;
use App\Services\RabbitMQService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;

class ConsumeProductEvents extends Command
{
    protected $signature = 'rabbitmq:consume-product-events';
    protected $description = 'Consume product events from RabbitMQ and sync to product_cache';

    public function handle()
    {
        $this->info('Starting Product Events Consumer...');

        $rabbitMQ = new RabbitMQService();

        // Declare exchange
        $rabbitMQ->declareExchange('product.events', 'topic');

        // Declare queue and bind to exchange
        $queueName = 'order-service.product-events';
        $routingKeys = ['product.created', 'product.updated', 'product.deleted'];
        $rabbitMQ->declareQueueAndBind($queueName, 'product.events', $routingKeys);

        $this->info("Listening on queue: {$queueName}");
        $this->info("Routing keys: " . implode(', ', $routingKeys));

        // Consume messages
        $callback = function (AMQPMessage $msg) {
            try {
                $data = json_decode($msg->body, true);
                $event = $data['event'] ?? null;
                $productData = $data['data'] ?? null;

                $this->info("Received event: {$event}");

                switch ($event) {
                    case 'product.created':
                    case 'product.updated':
                        if ($productData) {
                            ProductCache::syncFromProductService($productData);
                            $this->info("Product synced: {$productData['id']} - {$productData['name']}");
                            Log::info("Product synced from {$event}", $productData);
                        }
                        break;

                    case 'product.deleted':
                        if (isset($productData['id'])) {
                            $product = ProductCache::find($productData['id']);
                            if ($product) {
                                $product->update(['is_active' => false]);
                                $this->info("Product marked as inactive: {$productData['id']}");
                                Log::info("Product marked inactive", $productData);
                            }
                        }
                        break;

                    default:
                        $this->warn("Unknown event: {$event}");
                }

                // Acknowledge message
                $msg->ack();
            } catch (\Exception $e) {
                $this->error('Error processing message: ' . $e->getMessage());
                Log::error('Product event processing error: ' . $e->getMessage());
                // Reject and requeue message
                $msg->nack(true);
            }
        };

        $rabbitMQ->consume($queueName, $callback);
    }
}
