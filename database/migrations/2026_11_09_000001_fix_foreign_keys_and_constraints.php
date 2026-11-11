<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إصلاح القيود (Foreign Keys) في قاعدة البيانات:
 * 1. إضافة القيود التي كانت تفشل بسبب ترتيب إنشاء الجداول.
 * 2. منع الحذف المتسلسل (Cascade) الذي كان يمسح سجل المبيعات والمشتريات
 *    عند حذف عميل أو منتج أو مورد أو فرع.
 * 3. حذف عمود stock المكرر من جدول المنتجات (المعتمد هو stock_quantity).
 *
 * الـ migration آمن للتشغيل على قاعدة بيانات موجودة أو جديدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // 1) القيود الناقصة بسبب ترتيب الجداول
        $this->addForeign('units', 'organization_id', 'organizations', 'cascade');
        $this->addForeign('products', 'organization_id', 'organizations', 'cascade');
        $this->addForeign('inventory_transaction_items', 'inventory_transaction_id', 'inventory_transactions', 'cascade');
        $this->addForeign('product_batches', 'warehouse_id', 'warehouses', 'set null');

        // 2) حماية السجل التاريخي من الحذف المتسلسل
        $this->replaceForeign('sales', 'customer_id', 'customers', 'set null');
        $this->replaceForeign('sales', 'branch_id', 'branches', 'restrict');
        $this->replaceForeign('sale_items', 'product_id', 'products', 'restrict');
        $this->replaceForeign('purchases', 'supplier_id', 'suppliers', 'restrict');
        $this->replaceForeign('purchases', 'branch_id', 'branches', 'restrict');
        $this->replaceForeign('purchase_items', 'product_id', 'products', 'restrict');

        // 3) عمود stock المكرر
        if (Schema::hasColumn('products', 'stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('stock');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->replaceForeign('sales', 'customer_id', 'customers', 'cascade');
        $this->replaceForeign('sales', 'branch_id', 'branches', 'cascade');
        $this->replaceForeign('sale_items', 'product_id', 'products', 'cascade');
        $this->replaceForeign('purchases', 'supplier_id', 'suppliers', 'cascade');
        $this->replaceForeign('purchases', 'branch_id', 'branches', 'cascade');
        $this->replaceForeign('purchase_items', 'product_id', 'products', 'cascade');
    }

    /**
     * اسم القيد الحالي على العمود (إن وجد)
     */
    private function foreignKeyName(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$table, $column]
        );

        return $row->name ?? null;
    }

    private function addForeign(string $table, string $column, string $references, string $onDelete): void
    {
        if (!Schema::hasTable($table) || !Schema::hasTable($references) || $this->foreignKeyName($table, $column)) {
            return;
        }

        // تنظيف القيم اليتيمة قبل إضافة القيد حتى لا يفشل
        if ($onDelete === 'set null') {
            DB::statement("UPDATE `$table` SET `$column` = NULL WHERE `$column` IS NOT NULL AND `$column` NOT IN (SELECT id FROM `$references`)");
        }

        $this->createForeign($table, $column, $references, $onDelete);
    }

    private function replaceForeign(string $table, string $column, string $references, string $onDelete): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $existing = $this->foreignKeyName($table, $column);
        if ($existing) {
            DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `$existing`");
        }

        $this->createForeign($table, $column, $references, $onDelete);
    }

    private function createForeign(string $table, string $column, string $references, string $onDelete): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($column, $references, $onDelete) {
                $t->foreign($column)->references('id')->on($references)->onDelete($onDelete);
            });
        } catch (\Throwable $e) {
            // غالباً بسبب بيانات قديمة تشير لسجلات غير موجودة؛ لا نوقف باقي الإصلاحات
            echo "  ! تعذر إضافة القيد {$table}.{$column} -> {$references}: " . $e->getMessage() . PHP_EOL;
        }
    }
};
