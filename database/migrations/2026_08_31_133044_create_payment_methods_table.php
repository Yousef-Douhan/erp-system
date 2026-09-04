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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // الاسم للعرض (مثلاً: جوال بي)
            $table->string('code')->unique(); // الكود البرمجي (مثل: jawwal_pay, cash)
            $table->string('icon')->nullable(); // أيقونة أو إيموجي (مثل: 📱)
            $table->string('color')->default('emerald'); // لون الزر (emerald, cyan, orange, etc)
            $table->boolean('requires_wallet')->default(false); // هل تحتاج رقم محفظة/حساب؟
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
