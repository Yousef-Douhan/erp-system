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
        Schema::create('drug_alerts', function (Blueprint $table) {
            $table->id();
            // افترضنا أن جدول المنتجات/الأدوية اسمه products، عدله حسب جدولك إن اختلف
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('severity')->default('medium'); // low, medium, high, critical
            $table->text('message');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drug_alerts');
    }
};
