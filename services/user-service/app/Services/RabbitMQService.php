<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

class RabbitMQService
{
    private ?AMQPStreamConnection $connection = null;
    private $channel = null;

    /**
     * Connect to RabbitMQ
     */
    private function connect(): void
    {
        if ($this->connection && $this->connection->isConnected()) {
            return;
        }

        try {
            $this->connection = new AMQPStreamConnection(
                host: env('RABBITMQ_HOST', 'rabbitmq'),
                port: env('RABBITMQ_PORT', 5672),
                user: env('RABBITMQ_USER', 'admin'),
                password: env('RABBITMQ_PASSWORD', 'secret'),
                vhost: env('RABBITMQ_VHOST', '/')
            );

            $this->channel = $this->connection->channel();

            Log::info('Connected to RabbitMQ successfully');
        } catch (\Exception $e) {
            Log::error('Failed to connect to RabbitMQ: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Publish event to RabbitMQ
     */
    public function publish(string $exchange, string $routingKey, string $message): void
    {
        try {
            $this->connect();

            // Declare exchange (durable, auto-delete: false)
            $this->channel->exchange_declare(
                exchange: $exchange,
                type: 'topic',
                passive: false,
                durable: true,
                auto_delete: false
            );

            // Create message
            $msg = new AMQPMessage($message, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
                'timestamp' => time(),
            ]);

            // Publish message
            $this->channel->basic_publish(
                msg: $msg,
                exchange: $exchange,
                routing_key: $routingKey
            );

            Log::info("Published message to RabbitMQ", [
                'exchange' => $exchange,
                'routing_key' => $routingKey,
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to publish message to RabbitMQ: ' . $e->getMessage(), [
                'exchange' => $exchange,
                'routing_key' => $routingKey,
            ]);
            throw $e;
        }
    }

    /**
     * Close connection
     */
    public function close(): void
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }

            if ($this->connection && $this->connection->isConnected()) {
                $this->connection->close();
            }

            Log::info('Closed RabbitMQ connection');
        } catch (\Exception $e) {
            Log::error('Error closing RabbitMQ connection: ' . $e->getMessage());
        }
    }

    /**
     * Destructor - close connection
     */
    public function __destruct()
    {
        $this->close();
    }
}
