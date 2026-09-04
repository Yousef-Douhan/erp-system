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
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();

            $table->decimal('conversion_factor', 10, 4)->default(1); // معامل التحويل مقارنة بالوحدة الأساسية
            $table->decimal('purchase_price', 10, 2)->default(0); // سعر الشراء لهذه الوحدة
            $table->decimal('selling_price', 10, 2)->default(0); // سعر البيع لهذه الوحدة
            $table->string('barcode')->nullable()->unique(); // باركود خاص بهذه الوحدة
            $table->boolean('is_default')->default(false); // هل هي الوحدة الافتراضية للبيع/الشراء؟
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
