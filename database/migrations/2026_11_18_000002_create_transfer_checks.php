<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * مطابقة الحوالات: كل مبلغ دخل (أو خرج) عن طريق بنك/محفظة يُسجَّل «بانتظار التأكيد»
 * حتى يتأكد المسؤول آخر اليوم أنه وصل فعلاً، مع صورة الحوالة أو كشف الحساب.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transfer_statements')) {
            Schema::create('transfer_statements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('payment_method', 50)->nullable();
                $table->date('statement_date');
                $table->string('file_path');
                $table->string('original_name')->nullable();
                $table->string('note', 500)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('transfer_checks')) {
            Schema::create('transfer_checks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('sale_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();            // الكاشير الذي سجّل الدفعة
                $table->string('source', 30);                                  // sale | sale_payment | refund
                $table->string('direction', 3)->default('in');                 // in = وصلنا ، out = حوّلناه نحن
                $table->string('payment_method', 50);
                $table->string('wallet_phone', 30)->nullable();
                $table->decimal('amount', 12, 2);
                $table->string('status', 12)->default('pending')->index();    // pending | confirmed | rejected
                $table->unsignedBigInteger('checked_by')->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->string('note', 500)->nullable();
                $table->string('proof_path')->nullable();                      // صورة الحوالة
                $table->unsignedBigInteger('statement_id')->nullable()->index(); // كشف الحساب الذي أُكّدت به
                $table->timestamps();
                $table->index('created_at');
            });

            // الدفعات غير النقدية في آخر 30 يوماً تدخل القائمة «بانتظار التأكيد»
            $walletCodes = DB::table('payment_methods')->where('requires_wallet', true)->pluck('code');
            if ($walletCodes->isNotEmpty()) {
                DB::table('sales')
                    ->whereNull('deleted_at')
                    ->whereNull('cash_register_id')
                    ->whereIn('payment_method', $walletCodes)
                    ->where('paid_amount', '>', 0)
                    ->where('created_at', '>=', now()->subDays(30))
                    ->orderBy('id')
                    ->get(['id', 'organization_id', 'branch_id', 'customer_id', 'employee_id', 'payment_method', 'wallet_phone', 'paid_amount', 'created_at'])
                    ->chunk(500)
                    ->each(fn ($rows) => DB::table('transfer_checks')->insert($rows->map(fn ($s) => [
                        'organization_id' => $s->organization_id, 'branch_id' => $s->branch_id, 'sale_id' => $s->id,
                        'customer_id' => $s->customer_id, 'user_id' => $s->employee_id, 'source' => 'sale', 'direction' => 'in',
                        'payment_method' => $s->payment_method, 'wallet_phone' => $s->wallet_phone, 'amount' => $s->paid_amount,
                        'status' => 'pending', 'created_at' => $s->created_at, 'updated_at' => now(),
                    ])->all()));
            }
        }

        // صلاحية التأكيد: لصاحب الصيدلية ومدير الفرع
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('verify_transfers', 'web');
        foreach ([\App\Support\PermissionCatalog::OWNER_ROLE, \App\Support\PermissionCatalog::BRANCH_MANAGER_ROLE] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo('verify_transfers');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_checks');
        Schema::dropIfExists('transfer_statements');
    }
};
