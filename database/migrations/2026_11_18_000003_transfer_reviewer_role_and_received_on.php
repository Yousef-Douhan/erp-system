<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * - تاريخ وصول الحوالة (قد تصل في يوم لاحق) + المستند الذي ظهرت فيه
 * - فصل الصلاحيات: رفع كشوف الحساب وصور الحوالات (صاحب الصيدلية) عن المطابقة (موظف المطابقة)
 * - دور جاهز «مراجع حوالات» فيه المطابقة فقط
 */
return new class extends Migration
{
    public const REVIEWER_ROLE = 'مراجع حوالات';

    public function up(): void
    {
        Schema::table('transfer_checks', function (Blueprint $table) {
            if (!Schema::hasColumn('transfer_checks', 'received_on')) {
                $table->date('received_on')->nullable()->after('checked_at');
            }
        });

        // التأكيدات السابقة: تاريخ الوصول = يوم التأكيد
        \Illuminate\Support\Facades\DB::table('transfer_checks')->whereNotNull('checked_at')->where('status', 'confirmed')->whereNull('received_on')
            ->update(['received_on' => \Illuminate\Support\Facades\DB::raw('DATE(checked_at)')]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        Permission::findOrCreate('verify_transfers', 'web');
        Permission::findOrCreate('upload_transfer_docs', 'web');

        // صاحب الصيدلية: يرفع ويطابق
        Role::where('name', \App\Support\PermissionCatalog::OWNER_ROLE)->first()?->givePermissionTo(['verify_transfers', 'upload_transfer_docs']);

        // موظف المطابقة: يرى المستندات ويطابق فقط
        $reviewer = Role::findOrCreate(self::REVIEWER_ROLE, 'web');
        $reviewer->syncPermissions(['verify_transfers']);

        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasColumn('transfer_checks', 'received_on')) {
            Schema::table('transfer_checks', fn (Blueprint $t) => $t->dropColumn('received_on'));
        }
    }
};
