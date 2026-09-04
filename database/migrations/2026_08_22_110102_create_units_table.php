<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // اسم الوحدة (مثل: علبة، شريط، حبة)
            $table->string('short_name')->nullable(); // الاختصار (مثل: box, strip, pcs)
            $table->text('description')->nullable(); // الوصف
            $table->boolean('is_active')->default(true); // الحالة
            $table->foreignId('organization_id')->constrained(); // ربط بالمنشأة
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
