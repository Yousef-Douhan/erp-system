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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // المستهدف بالإشعار
            $table->string('type')->nullable(); // نوع الإشعار (مثلاً: alert, info, success)
            $table->string('title'); // عنوان الإشعار
            $table->text('message'); // نص الرسالة
            $table->boolean('is_read')->default(false); // هل تم قراءته أم لا
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
