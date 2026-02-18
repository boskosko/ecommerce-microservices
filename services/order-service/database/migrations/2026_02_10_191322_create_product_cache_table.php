<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_cache', function (Blueprint $table) {
            $table->string('product_id')->primary(); // MongoDB _id
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('stock');
            $table->string('category');
            $table->string('sku')->unique();
            $table->json('images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->useCurrent();
            $table->timestamps();

            $table->index('sku');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_cache');
    }
};
