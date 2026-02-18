<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function toArray()
    {
        return [
            'event' => 'order.created',
            'data' => [
                'id' => $this->order->id,
                'user_id' => $this->order->user_id,
                'order_number' => $this->order->order_number,
                'status' => $this->order->status,
                'total_amount' => (float) $this->order->total_amount,
                'items' => $this->order->items->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'price' => (float) $item->product_price,
                        'subtotal' => (float) $item->subtotal,
                    ];
                })->toArray(),
                'created_at' => $this->order->created_at->toIso8601String(),
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
