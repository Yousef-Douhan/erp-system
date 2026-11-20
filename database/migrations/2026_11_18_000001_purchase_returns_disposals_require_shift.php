<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - مرتجعات المشتريات للمورد (purchase_returns + أسطرها) + قيمة المرتجع على فاتورة الشراء والكمية المرتجعة على السطر
 * - إتلاف المخزون (منتهي الصلاحية / تالف) مع سبب وتكلفة
 * - خيار «إلزام فتح وردية قبل البيع» لكل صيدلية
 *
 * ملاحظة: بدون Foreign Keys على مستوى القاعدة، لأن بعض القواعد القديمة فيها جداول أساسية
 * بمحرك MyISAM أو أنواع أعمدة مختلفة فيفشل إنشاء القيد (خطأ 1215). الربط مضمون من الكود والفهارس موجودة.
 * وإذا فشل تشغيل سابق في المنتصف، الجداول الفارغة التي أُنشئت جزئياً تُحذف وتُنشأ من جديد.
 */
return new class extends Migration
{
    public function up(): void
    {
        // تنظيف تشغيل سابق فشل في المنتصف (الجداول الجديدة فقط، وفقط إذا كانت فارغة)
        foreach (['purchase_return_items', 'stock_disposals', 'purchase_returns'] as $t) {
            if (Schema::hasTable($t) && \Illuminate\Support\Facades\DB::table($t)->count() === 0) {
                Schema::drop($t);
            }
        }
        Schema::table('purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases', 'returned_amount')) {
                $table->decimal('returned_amount', 12, 2)->default(0)->after('paid_amount');
            }
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_items', 'returned_quantity')) {
                $table->integer('returned_quantity')->default(0)->after('quantity');
            }
        });

        Schema::table('organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('organizations', 'require_shift')) {
                $table->boolean('require_shift')->default(false)->after('status');
            }
        });

        if (!Schema::hasTable('purchase_returns')) {
            Schema::create('purchase_returns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('purchase_id')->index();
                $table->unsignedBigInteger('supplier_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('cash_register_id')->nullable()->index();
                $table->string('return_number')->unique();
                $table->decimal('total', 12, 2);
                $table->decimal('debt_reduction', 12, 2)->default(0);   // خُصم من المتبقي علينا للمورد
                $table->decimal('cash_refund', 12, 2)->default(0);      // رجع نقداً من المورد للخزينة
                $table->decimal('supplier_credit', 12, 2)->default(0);  // رصيد لنا عند المورد
                $table->text('reason')->nullable();
                $table->timestamps();
                $table->index('created_at');
            });
        }

        if (!Schema::hasTable('purchase_return_items')) {
            Schema::create('purchase_return_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_return_id')->index();
                $table->unsignedBigInteger('purchase_item_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('batch_id')->nullable()->index();
                $table->integer('quantity');
                $table->decimal('unit_cost', 12, 4);
                $table->decimal('total', 12, 2);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('stock_disposals')) {
            Schema::create('stock_disposals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('batch_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->integer('quantity');
                $table->decimal('unit_cost', 12, 4)->default(0);
                $table->decimal('total_cost', 12, 2)->default(0);
                $table->string('reason', 30);       // expired | damaged | other
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_disposals');
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');

        foreach (['purchases' => 'returned_amount', 'purchase_items' => 'returned_quantity', 'organizations' => 'require_shift'] as $table => $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
