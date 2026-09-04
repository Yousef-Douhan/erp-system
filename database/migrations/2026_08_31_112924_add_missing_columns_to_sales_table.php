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
        Schema::table('sales', function (Blueprint $table) {
            // السماح بأن يكون customer_id اختيارياً إذا لم يكن مسموحاً من قبل
            try {
                $table->foreignId('customer_id')->nullable()->change();
            } catch (\Exception $e) {
                // تجاهل إذا كان مسموحاً مسبقاً
            }

            // إضافة اسم العميل النصي فقط إذا لم يكن موجوداً
            if (!Schema::hasColumn('sales', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('customer_id');
            }

            // إضافة حقل الخصم إذا لم يكن موجوداً
            if (!Schema::hasColumn('sales', 'discount')) {
                $table->decimal('discount', 10, 2)->default(0);
            }

            // إضافة طريقة الدفع إذا لم تكن موجودة
            if (!Schema::hasColumn('sales', 'payment_method')) {
                $table->string('payment_method')->default('cash');
            }

            // إضافة رقم المحفظة إذا لم يكن موجوداً
            if (!Schema::hasColumn('sales', 'wallet_phone')) {
                $table->string('wallet_phone')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'discount', 'payment_method', 'wallet_phone']);
        });
    }
};
