<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ربط المبيعات بباقي الجداول:
 * - sale_items: وحدة البيع (علبة/شريط) ومعامل التحويل، وربط الدفعة (batch)
 * - sales: الخزينة التي دخل لها المال، والوصفة الطبية
 * - inventory_transactions: مرجع الحركة (أي فاتورة/عنصر سببها) لعكسها عند الحذف
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_items', 'product_unit_id')) {
                $table->foreignId('product_unit_id')->nullable()->after('product_id')
                    ->constrained('product_units')->nullOnDelete();
            }
            if (!Schema::hasColumn('sale_items', 'unit_name')) {
                $table->string('unit_name')->nullable()->after('product_unit_id');
            }
            if (!Schema::hasColumn('sale_items', 'unit_factor')) {
                $table->decimal('unit_factor', 10, 4)->default(1)->after('unit_name');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'cash_register_id')) {
                $table->foreignId('cash_register_id')->nullable()->after('branch_id')
                    ->constrained('cash_registers')->nullOnDelete();
            }
            if (!Schema::hasColumn('sales', 'prescription_id')) {
                $table->foreignId('prescription_id')->nullable()->after('customer_id')
                    ->constrained('prescriptions')->nullOnDelete();
            }
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_transactions', 'reference_type')) {
                $table->string('reference_type')->nullable()->after('type');
                $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
                $table->index(['reference_type', 'reference_id']);
            }
        });

        // ربط batch_id بجدول الدفعات (بعد تنظيف أي قيم غير موجودة)
        if (DB::getDriverName() === 'mysql') {
            DB::statement('UPDATE sale_items SET batch_id = NULL WHERE batch_id IS NOT NULL AND batch_id NOT IN (SELECT id FROM product_batches)');
            DB::statement('UPDATE inventory_transactions SET batch_id = NULL WHERE batch_id IS NOT NULL AND batch_id NOT IN (SELECT id FROM product_batches)');

            foreach (['sale_items', 'inventory_transactions'] as $tableName) {
                $exists = DB::selectOne(
                    'SELECT 1 AS x FROM information_schema.KEY_COLUMN_USAGE
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
                    [$tableName, 'batch_id']
                );
                if (!$exists) {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->foreign('batch_id')->references('id')->on('product_batches')->nullOnDelete();
                    });
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_transactions', 'reference_type')) {
                $table->dropIndex(['reference_type', 'reference_id']);
                $table->dropColumn(['reference_type', 'reference_id']);
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'prescription_id')) {
                $table->dropConstrainedForeignId('prescription_id');
            }
            if (Schema::hasColumn('sales', 'cash_register_id')) {
                $table->dropConstrainedForeignId('cash_register_id');
            }
        });

        Schema::table('sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_items', 'product_unit_id')) {
                $table->dropConstrainedForeignId('product_unit_id');
            }
            foreach (['unit_name', 'unit_factor'] as $column) {
                if (Schema::hasColumn('sale_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
