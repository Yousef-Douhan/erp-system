<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الجداول الجديدة: مرتجعات المبيعات، الورديات، الجرد، دفعات الموردين، نقل المخزون بين المستودعات.
 * + أعمدة: تكلفة الصنف وقت البيع والكمية المرتجعة، قيمة المرتجع على الفاتورة، فرع المستودع.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------- أعمدة على جداول قائمة ----------
        Schema::table('sale_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_items', 'unit_cost')) {
                $table->decimal('unit_cost', 12, 4)->nullable()->after('unit_price');
            }
            if (!Schema::hasColumn('sale_items', 'returned_quantity')) {
                $table->decimal('returned_quantity', 10, 2)->default(0)->after('quantity');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'returned_amount')) {
                $table->decimal('returned_amount', 12, 2)->default(0)->after('paid_amount');
            }
        });

        Schema::table('warehouses', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouses', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
        });

        // ---------- مرتجعات المبيعات ----------
        if (!Schema::hasTable('sale_returns')) {
            Schema::create('sale_returns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('sale_id')->constrained()->restrictOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('cash_register_id')->nullable()->constrained('cash_registers')->nullOnDelete();
                $table->string('return_number')->unique();
                $table->decimal('total', 12, 2);
                $table->decimal('refund_amount', 12, 2)->default(0);   // نقداً/تحويلاً للزبون
                $table->decimal('debt_reduction', 12, 2)->default(0);  // تخفيض من دين العميل
                $table->boolean('restocked')->default(true);           // أُعيدت للمخزون أم تالفة
                $table->text('reason')->nullable();
                $table->timestamps();
                $table->index('created_at');
            });
        }

        if (!Schema::hasTable('sale_return_items')) {
            Schema::create('sale_return_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->decimal('quantity', 10, 2);
                $table->decimal('unit_price', 12, 4);
                $table->decimal('total', 12, 2);
                $table->timestamps();
            });
        }

        // ---------- الورديات ----------
        if (!Schema::hasTable('cash_shifts')) {
            Schema::create('cash_shifts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cash_register_id')->constrained('cash_registers')->restrictOnDelete();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('opened_at');
                $table->timestamp('closed_at')->nullable();
                $table->decimal('opening_balance', 15, 2)->default(0);   // رصيد الخزينة لحظة الفتح
                $table->decimal('expected_balance', 15, 2)->nullable();  // رصيد النظام لحظة الإغلاق
                $table->decimal('actual_balance', 15, 2)->nullable();    // ما عدّه الكاشير فعلاً
                $table->decimal('difference', 15, 2)->nullable();        // الفعلي − المتوقع
                $table->string('status')->default('open');               // open | closed
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['cash_register_id', 'status']);
            });
        }

        // ---------- الجرد ----------
        if (!Schema::hasTable('stock_counts')) {
            Schema::create('stock_counts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('count_number')->unique();
                $table->string('scope')->nullable();                     // وصف النطاق (تصنيف/كل الأصناف)
                $table->string('status')->default('draft');              // draft | posted
                $table->text('notes')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('stock_count_items')) {
            Schema::create('stock_count_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->integer('system_qty');
                $table->integer('counted_qty')->nullable();
                $table->integer('difference')->nullable();
                $table->decimal('unit_cost', 12, 4)->default(0);
                $table->timestamps();
                $table->unique(['stock_count_id', 'product_id']);
            });
        }

        // ---------- دفعات الموردين ----------
        if (!Schema::hasTable('purchase_payments')) {
            Schema::create('purchase_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
                $table->foreignId('cash_register_id')->nullable()->constrained('cash_registers')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('payment_method')->default('cash');
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // ---------- نقل المخزون بين المستودعات ----------
        if (!Schema::hasTable('stock_transfers')) {
            Schema::create('stock_transfers', function (Blueprint $table) {
                $table->id();
                $table->string('transfer_number')->unique();
                $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
                $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('stock_transfer_items')) {
            Schema::create('stock_transfer_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->foreignId('from_batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
                $table->foreignId('to_batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
                $table->integer('quantity');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['stock_transfer_items', 'stock_transfers', 'purchase_payments', 'stock_count_items', 'stock_counts', 'cash_shifts', 'sale_return_items', 'sale_returns'] as $t) {
            Schema::dropIfExists($t);
        }

        if (Schema::hasColumn('warehouses', 'branch_id')) {
            Schema::table('warehouses', fn (Blueprint $t) => $t->dropConstrainedForeignId('branch_id'));
        }
        if (Schema::hasColumn('sales', 'returned_amount')) {
            Schema::table('sales', fn (Blueprint $t) => $t->dropColumn('returned_amount'));
        }
        Schema::table('sale_items', function (Blueprint $t) {
            foreach (['unit_cost', 'returned_quantity'] as $c) {
                if (Schema::hasColumn('sale_items', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
