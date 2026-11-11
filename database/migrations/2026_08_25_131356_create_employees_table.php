<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // الجدول يُنشأ في migration سابق (2026_08_24_085420)، هنا فقط نضيف الحالة terminated
        if (Schema::hasTable('employees')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE employees MODIFY status ENUM('active','inactive','terminated') NOT NULL DEFAULT 'active'");
            }
            return;
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->decimal('salary', 10, 2)->default(0.00);
            $table->enum('status', ['active', 'inactive', 'terminated'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // الحذف يتم في migration الإنشاء الأصلي
    }
};
