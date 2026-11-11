<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الحذف الناعم: السجلات المهمة لا تُمسح من قاعدة البيانات عند الحذف،
 * بل تُعلَّم بتاريخ الحذف ويمكن استرجاعها من "سلة المحذوفات".
 */
return new class extends Migration
{
    private array $tables = [
        'users', 'organizations', 'branches', 'warehouses', 'customers', 'suppliers',
        'products', 'categories', 'brands', 'manufacturers', 'units', 'employees',
        'cash_registers', 'payment_methods', 'accounts', 'prescriptions',
        'sales', 'sale_items', 'purchases', 'purchase_items', 'expenses',
        'journal_entries', 'journal_entry_lines', 'audit_logs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes();
                    $t->index('deleted_at');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropIndex(['deleted_at']);
                    $t->dropSoftDeletes();
                });
            }
        }
    }
};
