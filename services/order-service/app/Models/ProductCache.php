<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCache extends Model
{
    use HasFactory;

    protected $table = 'product_cache';
    protected $primaryKey = 'product_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'product_id',
        'name',
        'description',
        'price',
        'stock',
        'category',
        'sku',
        'images',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'images' => 'array',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public static function syncFromProductService($productData)
    {
        return static::updateOrCreate(
            ['product_id' => $productData['id']],
            [
                'name' => $productData['name'],
                'description' => $productData['description'] ?? null,
                'price' => $productData['price'],
                'stock' => $productData['stock'],
                'category' => $productData['category'],
                'sku' => $productData['sku'],
                'images' => $productData['images'] ?? [],
                'is_active' => $productData['is_active'] ?? true,
                'last_synced_at' => now(),
            ]
        );
    }
}
