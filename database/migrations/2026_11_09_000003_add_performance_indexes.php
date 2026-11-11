<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * فهارس للاستعلامات الأكثر استخداماً: تقارير المبيعات بالتاريخ، تنبيهات المخزون،
 * صرف الدفعات حسب تاريخ الانتهاء، وعداد الإشعارات غير المقروءة.
 * كل فهرس يُضاف فقط إذا كان الجدول والأعمدة موجودة ولم يكن الفهرس مضافاً من قبل.
 */
return new class extends Migration
{
    private array $indexes = [
        'sales'           => ['sales_created_at_index' => ['created_at'], 'sales_status_index' => ['status']],
        'products'        => ['products_active_stock_index' => ['is_active', 'stock_quantity']],
        'product_batches' => ['product_batches_product_expiry_index' => ['product_id', 'expiry_date']],
        'notifications'   => ['notifications_user_read_index' => ['user_id', 'is_read']],
        'customer_transactions' => ['customer_transactions_customer_created_index' => ['customer_id', 'created_at']],
        'journal_entries' => ['journal_entries_ref_index' => ['ref_type', 'ref_id']],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->indexes as $table => $list) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($list as $name => $columns) {
                if (!Schema::hasColumns($table, $columns) || $this->indexExists($table, $name)) {
                    continue;
                }
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->indexes as $table => $list) {
            foreach (array_keys($list) as $name) {
                if (Schema::hasTable($table) && $this->indexExists($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();
    }
};
