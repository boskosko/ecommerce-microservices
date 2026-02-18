<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCancelledEvent
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
            'event' => 'order.cancelled',
            'data' => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'user_id' => $this->order->user_id,
                'items' => $this->order->items->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                    ];
                })->toArray(),
                'cancelled_at' => $this->order->updated_at->toIso8601String(),
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
