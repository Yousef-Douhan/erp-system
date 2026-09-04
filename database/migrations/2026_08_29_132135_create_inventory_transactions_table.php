<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedBigInteger('batch_id')->nullable(); // إذا كان لديك جدول للتشغيلات/Batches ربطه هنا
            $table->string('type'); // نوع الحركة (إدخال، إخراج، تحويل، مرتجع...)
            $table->decimal('qty_in', 10, 2)->default(0);
            $table->decimal('qty_out', 10, 2)->default(0);
            $table->decimal('balance_after', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
