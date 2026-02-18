<?php

namespace App\Console\Commands;

use App\Mail\OrderCreatedMail;
use App\Mail\OrderCancelledMail;
use App\Services\RabbitMQService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PhpAmqpLib\Message\AMQPMessage;

class ConsumeOrderEvents extends Command
{
    protected $signature = 'rabbitmq:consume-order-events';
    protected $description = 'Consume order events from RabbitMQ and send email notifications';

    public function handle()
    {
        $this->info('Starting Order Events Consumer...');

        $rabbitMQ = new RabbitMQService();

        // Declare exchange
        $rabbitMQ->declareExchange('order.events', 'topic');

        // Declare queue and bind to exchange
        $queueName = 'notification-service.order-events';
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
        try {
            // Send email notification
            Mail::to('customer@example.com')->send(new OrderCreatedMail($orderData));

            $this->info("Email sent: Order Created - " . ($orderData['order_number'] ?? 'N/A'));
            Log::info('Order created email sent', [
                'order_id' => $orderData['id'] ?? null,
                'order_number' => $orderData['order_number'] ?? null,
            ]);
        } catch (\Exception $e) {
            $this->error('Failed to send order created email: ' . $e->getMessage());
            Log::error('Order created email failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderData['id'] ?? null,
            ]);
            throw $e;
        }
    }

    private function handleOrderCancelled($orderData)
    {
        try {
            // Send email notification
            Mail::to('customer@example.com')->send(new OrderCancelledMail($orderData));

            $this->info("Email sent: Order Cancelled - " . ($orderData['order_number'] ?? 'N/A'));
            Log::info('Order cancelled email sent', [
                'order_id' => $orderData['id'] ?? null,
                'order_number' => $orderData['order_number'] ?? null,
            ]);
        } catch (\Exception $e) {
            $this->error('Failed to send order cancelled email: ' . $e->getMessage());
            Log::error('Order cancelled email failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderData['id'] ?? null,
            ]);
            throw $e;
        }
    }
}
