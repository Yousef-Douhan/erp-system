<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بيانات المحوِّل لدفعات البنك/المحافظ: الاسم ورقم الحساب ورقم العملية (رقم الجوال موجود: wallet_phone).
 * موظف المطابقة يبحث بها في كشف الحساب.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['sales', 'transfer_checks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'payer_name')) {
                    $table->string('payer_name', 120)->nullable()->after('wallet_phone');
                }
                if (!Schema::hasColumn($tableName, 'payer_account')) {
                    $table->string('payer_account', 60)->nullable()->after('payer_name');
                }
                if (!Schema::hasColumn($tableName, 'transfer_ref')) {
                    $table->string('transfer_ref', 60)->nullable()->after('payer_account');
                }
            });
        }

        Schema::table('transfer_checks', function (Blueprint $table) {
            $table->index('wallet_phone', 'transfer_checks_wallet_phone_index');
            $table->index('payer_account', 'transfer_checks_payer_account_index');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_checks', function (Blueprint $table) {
            $table->dropIndex('transfer_checks_wallet_phone_index');
            $table->dropIndex('transfer_checks_payer_account_index');
        });
        foreach (['sales', 'transfer_checks'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $t) => $t->dropColumn(['payer_name', 'payer_account', 'transfer_ref']));
        }
    }
};
