<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * سلامة قاعدة البيانات نفسها (تُفحص على قاعدة الاختبار بعد تشغيل كل الـ migrations).
 * php artisan test --filter=DatabaseHealthTest
 */
class DatabaseHealthTest extends TestCase
{
    use RefreshDatabase;

    private function tables()
    {
        return collect(DB::select(
            'SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_COLLATION AS collation
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = "BASE TABLE"'
        ));
    }

    /**
     * MyISAM لا يدعم العمليات المترابطة (transactions): لو فشلت فاتورة في المنتصف
     * يُحفظ جزء منها (مثلاً ينقص المخزون بدون فاتورة)، ولا تعمل المفاتيح الأجنبية.
     */
    public function test_all_tables_use_innodb(): void
    {
        $bad = $this->tables()->filter(fn ($t) => strtolower((string) $t->engine) !== 'innodb')
            ->map(fn ($t) => "{$t->name} ({$t->engine})")->values()->all();

        $this->assertSame([], $bad, "جداول ليست InnoDB:\n" . implode("\n", $bad)
            . "\nالحل: في config/database.php ضع 'engine' => 'InnoDB' لاتصال mysql، أو default-storage-engine=InnoDB في my.ini");
    }

    /** العربية والرموز تحتاج utf8mb4 حتى لا تتحول لعلامات ؟؟؟ */
    public function test_all_tables_use_utf8mb4(): void
    {
        $bad = $this->tables()->filter(fn ($t) => !str_starts_with((string) $t->collation, 'utf8mb4'))
            ->map(fn ($t) => "{$t->name} ({$t->collation})")->values()->all();

        $this->assertSame([], $bad, "جداول بترميز غير utf8mb4:\n" . implode("\n", $bad));
    }

    public function test_transactions_really_roll_back(): void
    {
        $name = 'rollback-check-' . uniqid();

        try {
            DB::transaction(function () use ($name) {
                DB::table('organizations')->insert([
                    'name' => $name, 'currency' => 'ILS', 'timezone' => 'Asia/Hebron', 'status' => 'active',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                throw new \RuntimeException('فشل متعمد');
            });
        } catch (\RuntimeException) {
        }

        $this->assertFalse(DB::table('organizations')->where('name', $name)->exists(), 'التراجع عن العملية لم يحدث: الجداول لا تدعم transactions');
    }

    public function test_core_tables_have_soft_deletes(): void
    {
        foreach (['users', 'products', 'customers', 'suppliers', 'sales', 'purchases', 'branches', 'organizations'] as $table) {
            $this->assertTrue(\Schema::hasColumn($table, 'deleted_at'), "الجدول {$table} بدون deleted_at: شغّل php artisan migrate");
        }
    }
}
