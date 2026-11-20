<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * سجل حركات الخزائن: كل قرش يدخل أو يخرج من أي خزينة يُسجل هنا (من أين، لماذا، من سجّله، والرصيد بعده).
 */
return new class extends Migration
{
    public function up(): void
    {
        // تشغيل سابق أنشأ الجدول ثم توقف قبل التسجيل: إن كان فارغاً يُعاد إنشاؤه، وإن كانت فيه حركات يُترك
        if (Schema::hasTable('cash_movements')) {
            if (DB::table('cash_movements')->exists()) {
                return;
            }
            Schema::drop('cash_movements');
        }

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained('cash_registers');
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('cash_shift_id')->nullable()->constrained('cash_shifts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->enum('direction', ['in', 'out']);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->index(['cash_register_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        // الخزائن الموجودة: رصيدها الحالي يُسجل كرصيد مُرحّل حتى يطابق السجل الرصيد
        DB::table('cash_registers')->whereNull('deleted_at')->orderBy('id')->get()->each(function ($r) {
            DB::table('cash_movements')->insert([
                'cash_register_id' => $r->id,
                'branch_id'        => $r->branch_id,
                'type'             => 'carried_forward',
                'direction'        => (float) $r->current_balance >= 0 ? 'in' : 'out',
                'amount'           => abs((float) $r->current_balance),
                'balance_after'    => (float) $r->current_balance,
                'description'      => 'الرصيد المُرحّل عند تفعيل سجل حركات الخزينة',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
