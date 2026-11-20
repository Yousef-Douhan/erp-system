<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * نوع طريقة الدفع: cash (نقدي) | bank (تحويل بنكي) | wallet (محفظة: جوال بي، بال بي...)
 * - البنك: اسم صاحب الحساب إجباري، والباقي اختياري
 * - المحفظة: الاسم ورقم الجوال إجباريان
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('payment_methods', 'kind')) {
            Schema::table('payment_methods', fn (Blueprint $t) => $t->string('kind', 10)->nullable()->after('code'));
        }

        foreach (DB::table('payment_methods')->whereNull('kind')->get() as $m) {
            DB::table('payment_methods')->where('id', $m->id)->update([
                'kind' => \App\Models\PaymentMethod::guessKind((string) $m->name, (string) $m->code, (bool) $m->requires_wallet),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payment_methods', 'kind')) {
            Schema::table('payment_methods', fn (Blueprint $t) => $t->dropColumn('kind'));
        }
    }
};
