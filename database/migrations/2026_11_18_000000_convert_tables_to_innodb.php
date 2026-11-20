<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تحويل أي جدول قديم من MyISAM إلى InnoDB.
 * MyISAM لا يدعم المعاملات (Transactions) ولا القيود: لو حدث خطأ في منتصف بيع أو مرتجع
 * يبقى نصف العملية محفوظاً (مثلاً المخزون نقص والفاتورة لم تُحفظ). InnoDB يلغي العملية كلها.
 * التحويل لا يغيّر البيانات. خذ نسخة احتياطية قبل التشغيل (php artisan backup:run).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $tables = DB::select(
            "SELECT TABLE_NAME AS name FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND ENGINE <> 'InnoDB'"
        );

        foreach ($tables as $t) {
            DB::statement('ALTER TABLE `' . str_replace('`', '', $t->name) . '` ENGINE=InnoDB');
        }
    }

    public function down(): void
    {
        // لا رجوع: InnoDB هو الصحيح
    }
};
