<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * البيع بدون إنترنت:
 * - client_uuid: رمز الفاتورة الذي ولّده جهاز البيع (يمنع حفظ نفس الفاتورة مرتين عند إعادة الرفع)
 * - offline_at: وقت البيع الفعلي على الجهاز
 * - offline_number: الرقم المؤقت المطبوع للزبون (OFF-...)
 * - needs_review / review_note: فاتورة رُفعت لكن فيها شيء يحتاج نظر المدير (سعر تغيّر، مخزون، حد دين...)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'client_uuid')) {
                $table->string('client_uuid', 64)->nullable()->unique();
            }
            if (!Schema::hasColumn('sales', 'offline_number')) {
                $table->string('offline_number', 40)->nullable()->index();
            }
            if (!Schema::hasColumn('sales', 'offline_at')) {
                $table->timestamp('offline_at')->nullable();
            }
            if (!Schema::hasColumn('sales', 'needs_review')) {
                $table->boolean('needs_review')->default(false)->index();
                $table->text('review_note')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            foreach (['client_uuid', 'offline_number', 'offline_at', 'needs_review', 'review_note', 'reviewed_by', 'reviewed_at'] as $c) {
                if (Schema::hasColumn('sales', $c)) {
                    if ($c === 'client_uuid') {
                        $table->dropUnique(['client_uuid']);
                    }
                    if ($c === 'offline_number') {
                        $table->dropIndex(['offline_number']);
                    }
                    if ($c === 'needs_review') {
                        $table->dropIndex(['needs_review']);
                    }
                    $table->dropColumn($c);
                }
            }
        });
    }
};
