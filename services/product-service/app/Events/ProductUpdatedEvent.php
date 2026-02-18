<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductUpdatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $product;

    public function __construct($product)
    {
        $this->product = $product;
    }

    public function toArray()
    {
        return [
            'event' => 'product.updated',
            'data' => [
                'id' => (string) $this->product->_id,
                'name' => $this->product->name,
                'description' => $this->product->description,
                'price' => $this->product->price,
                'stock' => $this->product->stock,
                'category' => $this->product->category,
                'sku' => $this->product->sku,
                'images' => $this->product->images ?? [],
                'is_active' => $this->product->is_active,
                'updated_at' => $this->product->updated_at->toIso8601String(),
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
