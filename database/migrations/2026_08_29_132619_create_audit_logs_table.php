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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // نوع الحركة: created, updated, deleted
            $table->string('table_name'); // اسم الجدول المستهدف
            $table->unsignedBigInteger('record_id')->nullable(); // معرف السجل
            $table->longText('old_values')->nullable(); // القيم القديمة (JSON)
            $table->longText('new_values')->nullable(); // القيم الجديدة (JSON)
            $table->string('ip_address')->nullable(); // عنوان الـ IP
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
