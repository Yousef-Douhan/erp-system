<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * نسخة احتياطية كاملة لقاعدة البيانات (هيكل + بيانات) بصيغة SQL مضغوطة.
 * مكتوبة بـ PHP مباشرة فلا تحتاج mysqldump، وتعمل على WAMP كما هي.
 * الملفات تُحفظ في storage/app/backups ويُحتفظ بآخر عدد منها فقط.
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:run {--keep= : عدد النسخ المحفوظة (الافتراضي من الإعدادات)}';
    protected $description = 'أخذ نسخة احتياطية من قاعدة البيانات وحذف النسخ القديمة';

    public function handle(): int
    {
        $dir = self::directory();
        File::ensureDirectoryExists($dir);

        $file = $dir . DIRECTORY_SEPARATOR . 'erp-' . now()->format('Y-m-d_His') . '.sql.gz';
        $gz = gzopen($file, 'wb6');
        if (!$gz) {
            $this->error('تعذر إنشاء ملف النسخة الاحتياطية.');
            return self::FAILURE;
        }

        $pdo = DB::connection()->getPdo();
        $database = DB::connection()->getDatabaseName();

        gzwrite($gz, "-- ERP backup of `{$database}` at " . now()->toDateTimeString() . "\n");
        gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = collect(DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"'))
            ->map(fn ($row) => array_values((array) $row)[0]);

        foreach ($tables as $table) {
            $create = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");
            gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n" . array_values($create)[1] . ";\n\n");

            $batch = [];
            foreach (DB::table($table)->cursor() as $row) {
                $values = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $row);
                $batch[] = '(' . implode(',', $values) . ')';

                if (count($batch) >= 300) {
                    $this->writeInsert($gz, $table, $row, $batch);
                    $batch = [];
                }
            }
            if ($batch) {
                $this->writeInsert($gz, $table, $row, $batch);
            }
            gzwrite($gz, "\n");
        }

        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);

        $removed = $this->prune((int) ($this->option('keep') ?: config('backup.keep', 14)));

        $size = round(filesize($file) / 1024, 1);
        $this->info("تم حفظ النسخة: " . basename($file) . " ({$size} KB) — {$tables->count()} جدول" . ($removed ? "، وحُذفت {$removed} نسخة قديمة" : '') . '.');

        return self::SUCCESS;
    }

    public static function directory(): string
    {
        return storage_path('app' . DIRECTORY_SEPARATOR . 'backups');
    }

    private function writeInsert($gz, string $table, $row, array $batch): void
    {
        $columns = implode(',', array_map(fn ($c) => "`{$c}`", array_keys((array) $row)));
        gzwrite($gz, "INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode(",\n", $batch) . ";\n");
    }

    private function prune(int $keep): int
    {
        $files = collect(File::files(self::directory()))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.sql.gz'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        $old = $files->slice(max(1, $keep));
        $old->each(fn ($f) => File::delete($f->getPathname()));

        return $old->count();
    }
}
