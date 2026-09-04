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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // اسم المسؤول أو الشخص التواصل
            $table->string('company_name')->nullable(); // اسم الشركة أو المؤسسة الموردة
            $table->decimal('balance', 10, 2)->default(0); // رصيد المورد (له/عليه)
            $table->decimal('credit_limit', 10, 2)->default(0); // الحد الائتماني
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
