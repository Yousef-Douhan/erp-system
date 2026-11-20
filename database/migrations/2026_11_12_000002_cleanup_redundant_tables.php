<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تنظيف قاعدة البيانات من الجداول المكررة أو غير المستعملة:
 *  - دفتر العميل كان يُكتب في جدولين (customer_accounts و customer_transactions) => جدول واحد customer_transactions
 *  - الموظفون (employees + employee_branches) => الاسم والراتب صارا في جدول المستخدمين
 *  - manufacturers (غير مربوط بالأصناف)، product_stock_settings (لا يُقرأ)، inventory_transaction_items (غير مستعمل)
 *
 * لا رجوع عنها: خذ نسخة احتياطية قبل التشغيل (php artisan backup:run).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->mergeCustomerLedger();
        $this->moveEmployeesToUsers();

        foreach (['inventory_transaction_items', 'product_stock_settings', 'manufacturers'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function mergeCustomerLedger(): void
    {
        Schema::table('customer_transactions', function (Blueprint $t) {
            if (!Schema::hasColumn('customer_transactions', 'source')) {
                $t->string('source', 50)->nullable()->after('type');
            }
            if (!Schema::hasColumn('customer_transactions', 'balance_after')) {
                $t->decimal('balance_after', 14, 2)->nullable()->after('amount');
            }
        });

        if (Schema::hasTable('customer_accounts')) {
            // الحركات الموجودة في customer_accounts فقط (قديمة) تُنقل، والمتطابقة يُؤخذ منها نوعها
            DB::table('customer_accounts')->orderBy('id')->each(function ($a) {
                $isAdd = (float) $a->debit > 0;
                $amount = $isAdd ? (float) $a->debit : (float) $a->credit;
                if ($amount <= 0) {
                    return;
                }

                $match = DB::table('customer_transactions')
                    ->where('customer_id', $a->customer_id)
                    ->where('type', $isAdd ? 'add' : 'subtract')
                    ->where('amount', $amount)
                    ->whereNull('source')
                    ->whereBetween('created_at', [date('Y-m-d H:i:s', strtotime($a->created_at) - 5), date('Y-m-d H:i:s', strtotime($a->created_at) + 5)])
                    ->orderBy('id')->value('id');

                if ($match) {
                    DB::table('customer_transactions')->where('id', $match)->update(['source' => $a->transaction_type]);
                } else {
                    DB::table('customer_transactions')->insert([
                        'customer_id' => $a->customer_id,
                        'type'        => $isAdd ? 'add' : 'subtract',
                        'source'      => $a->transaction_type,
                        'amount'      => $amount,
                        'notes'       => 'منقول من حسابات العملاء: ' . $a->transaction_type,
                        'created_at'  => $a->created_at,
                        'updated_at'  => $a->updated_at,
                    ]);
                }
            });

            Schema::drop('customer_accounts');
        }

        // الرصيد بعد كل حركة: يُحسب رجوعاً من الرصيد الحالي للعميل
        DB::table('customers')->select('id', 'balance')->orderBy('id')->each(function ($c) {
            $balance = (float) $c->balance;
            DB::table('customer_transactions')->where('customer_id', $c->id)
                ->orderByDesc('created_at')->orderByDesc('id')->get(['id', 'type', 'amount'])
                ->each(function ($tx) use (&$balance) {
                    DB::table('customer_transactions')->where('id', $tx->id)->update(['balance_after' => round($balance, 2)]);
                    $balance += $tx->type === 'add' ? -(float) $tx->amount : (float) $tx->amount;
                });
        });
    }

    private function moveEmployeesToUsers(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'full_name')) {
                $t->string('full_name')->nullable()->after('username');
            }
            if (!Schema::hasColumn('users', 'salary')) {
                $t->decimal('salary', 10, 2)->default(0)->after('phone');
            }
        });

        Schema::dropIfExists('employee_branches');

        if (!Schema::hasTable('employees')) {
            return;
        }

        $live = DB::table('employees')->when(Schema::hasColumn('employees', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'));

        (clone $live)->whereNotNull('user_id')->orderBy('id')->each(function ($e) {
            $name = trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? ''));
            DB::table('users')->where('id', $e->user_id)->update(['salary' => (float) $e->salary]);
            if ($name !== '') {
                DB::table('users')->where('id', $e->user_id)->whereNull('full_name')->update(['full_name' => $name]);
            }
            // الفرع: يُؤخذ من ملف الموظف فقط إذا لم يكن للمستخدم فرع
            if ($e->branch_id) {
                DB::table('users')->where('id', $e->user_id)->whereNull('branch_id')->update(['branch_id' => $e->branch_id]);
            }
        });

        // موظفون بلا حساب مستخدم لا يمكن نقلهم: يُحفظ الجدول باسم أرشيف بدل حذفه حتى لا تضيع بياناتهم
        if ((clone $live)->whereNull('user_id')->exists()) {
            Schema::rename('employees', 'employees_archive');
        } else {
            Schema::drop('employees');
        }
    }

    public function down(): void
    {
        // لا رجوع تلقائي: استرجع النسخة الاحتياطية إذا احتجت الجداول القديمة
    }
};
