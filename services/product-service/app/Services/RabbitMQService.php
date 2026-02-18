<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

class RabbitMQService
{
    protected $connection;
    protected $channel;

    public function __construct()
    {
        $this->connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST', 'rabbitmq'),
            env('RABBITMQ_PORT', 5672),
            env('RABBITMQ_USER', 'admin'),
            env('RABBITMQ_PASSWORD', 'secret'),
            '/',           // vhost
            false,         // insist
            'AMQPLAIN',    // login method
            null,          // login response
            'en_US',       // locale
            3.0,           // connection timeout
            3.0            // read/write timeout
        );
        $this->channel = $this->connection->channel();
    }

    /**
     * Declare exchange
     */
    public function declareExchange($exchangeName, $exchangeType = 'topic')
    {
        $this->channel->exchange_declare(
            $exchangeName,
            $exchangeType,
            false,  // passive
            true,   // durable
            false   // auto_delete
        );
    }

    /**
     * Declare queue and bind to exchange
     */
    public function declareQueueAndBind($queueName, $exchangeName, $routingKeys = [])
    {
        // Declare queue
        $this->channel->queue_declare(
            $queueName,
            false,  // passive
            true,   // durable
            false,  // exclusive
            false   // auto_delete
        );

        // Bind queue to exchange with routing keys
        foreach ($routingKeys as $routingKey) {
            $this->channel->queue_bind($queueName, $exchangeName, $routingKey);
        }

        Log::info("Queue '{$queueName}' bound to '{$exchangeName}' with routing keys: " . implode(', ', $routingKeys));
    }

    /**
     * Publish message to exchange
     */
    public function publish($exchangeName, $routingKey, $message, $autoClose = true)
    {
        $msg = new AMQPMessage(
            json_encode($message),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
            ]
        );

        $this->channel->basic_publish($msg, $exchangeName, $routingKey);

        Log::info("Published to RabbitMQ: {$routingKey}", $message);

        // Immediately close connection after publishing (unless disabled)
        if ($autoClose) {
            $this->closeConnection();
        }
    }

    /**
     * Consume messages from queue
     */
    public function consume($queueName, $callback)
    {
        $this->channel->basic_consume(
            $queueName,
            '',     // consumer_tag
            false,  // no_local
            false,  // no_ack (manual ack)
            false,  // exclusive
            false,  // nowait
            $callback
        );

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    /**
     * Close connection immediately
     */
    public function closeConnection()
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }
            if ($this->connection) {
                $this->connection->close();
            }
        } catch (\Exception $e) {
            // Ignore close errors
            Log::debug('RabbitMQ connection close: ' . $e->getMessage());
        }
    }

    public function __destruct()
    {
        // Do nothing - we close manually in publish()
    }
}
