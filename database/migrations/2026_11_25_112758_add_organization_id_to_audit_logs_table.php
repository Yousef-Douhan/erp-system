<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ربط سجل التدقيق بالصيدلية حتى لا ترى صيدلية سجلات صيدلية أخرى.
 * السجلات القديمة تُنسب لصيدلية السجل المتأثر إن وُجدت، وإلا لصيدلية المستخدم الذي نفّذ العملية.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('audit_logs', 'organization_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->after('user_id')
                    ->constrained('organizations')->nullOnDelete();
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        if (Schema::hasColumn('audit_logs', 'organization_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('organization_id');
            });
        }
    }

    private function backfill(): void
    {
        $tables = DB::table('audit_logs')->whereNull('organization_id')->distinct()->pluck('table_name');

        foreach ($tables as $table) {
            if ($table === 'organizations') {
                DB::table('audit_logs')->whereNull('organization_id')->where('table_name', $table)
                    ->whereExists(fn ($q) => $q->from('organizations')->whereColumn('organizations.id', 'audit_logs.record_id'))
                    ->update(['organization_id' => DB::raw('record_id')]);

                continue;
            }

            if ($table === 'audit_logs' || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_id')) {
                continue;
            }

            DB::table('audit_logs')->whereNull('organization_id')->where('table_name', $table)
                ->update(['organization_id' => $this->subquery($table, 'record_id')]);
        }

        DB::table('audit_logs')->whereNull('organization_id')->whereNotNull('user_id')
            ->update(['organization_id' => $this->subquery('users', 'user_id')]);
    }

    /**
     * (select organization_id from {table} where {table}.id = audit_logs.{column} limit 1)
     * مع تجاهل الصيدليات غير الموجودة (بيانات قديمة تشير لصيدلية محذوفة) فيبقى العمود فارغاً.
     */
    private function subquery(string $table, string $column)
    {
        $grammar = DB::getQueryGrammar();
        $wrapped = $grammar->wrapTable($table);

        return DB::raw("(select {$wrapped}.organization_id from {$wrapped}"
            ." inner join organizations on organizations.id = {$wrapped}.organization_id"
            ." where {$wrapped}.id = audit_logs.{$grammar->wrap($column)} limit 1)");
    }
};
