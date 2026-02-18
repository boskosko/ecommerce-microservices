<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductDeletedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $productId;
    public $sku;

    public function __construct($productId, $sku)
    {
        $this->productId = $productId;
        $this->sku = $sku;
    }

    public function toArray()
    {
        return [
            'event' => 'product.deleted',
            'data' => [
                'id' => $this->productId,
                'sku' => $this->sku,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
