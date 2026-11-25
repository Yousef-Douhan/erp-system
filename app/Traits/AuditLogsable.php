<?php

namespace App\Traits;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Organization;
use App\Support\Tenancy;

trait AuditLogsable
{
    /**
     * تفعيل الأحداث تلقائياً عند استخدام الـ Trait في أي موديل
     */
    public static function bootAuditLogsable()
    {
        static::created(function ($model) {
            self::logAudit($model, 'created', $model->getAttributes(), null);
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if (empty($changes)) {
                return;
            }

            // القيم القديمة للحقول التي تغيرت فقط
            $old = array_intersect_key($model->getOriginal(), $changes);
            self::logAudit($model, 'updated', $changes, $old);
        });

        static::deleted(function ($model) {
            self::logAudit($model, 'deleted', null, $model->getAttributes());
        });
    }

    protected static function logAudit($model, $action, $newValues, $oldValues)
    {
        // عدم حفظ الحقول الحساسة (كلمات المرور والتوكنات) في السجل
        $hidden = array_flip(array_merge($model->getHidden(), ['password', 'remember_token']));
        $clean = fn ($values) => $values ? array_diff_key($values, $hidden) : null;

        // الحقول من نوع array في AuditLog تتولى الترميز، لذلك نمرر مصفوفات (بدون json_encode مزدوج)
        AuditLog::create([
            'user_id' => auth()->id(),
            'organization_id' => self::auditOrganizationId($model),
            'action' => $action,
            'table_name' => $model->getTable(),
            'record_id' => $model->getKey(),
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'old_values' => $clean($oldValues),
            'new_values' => $clean($newValues),
        ]);
    }

    /**
     * الصيدلية التي يتبعها السجل: صيدلية السجل المتأثر إن عُرفت، وإلا صيدلية المستخدم الحالي.
     */
    protected static function auditOrganizationId($model): ?int
    {
        if ($model instanceof Organization) {
            return (int) $model->getKey();
        }

        if ($model->getAttribute('organization_id')) {
            return (int) $model->getAttribute('organization_id');
        }

        if ($branchId = $model->getAttribute('branch_id')) {
            $orgId = Tenancy::bypass(fn () => Branch::withTrashed()->whereKey($branchId)->value('organization_id'));
            if ($orgId) {
                return (int) $orgId;
            }
        }

        return Tenancy::orgId();
    }
}
