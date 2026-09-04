<?php

namespace App\Traits;

use App\Models\AuditLog;

trait AuditLogsable
{
    /**
     * تفعيل الأحداث تلقائياً عند استخدام الـ Trait في أي موديل
     */
    public static function bootAuditLogsable()
    {
        static::created(function ($model) {
            self::logAudit($model, 'created', $model->toArray(), null);
        });

        static::updated(function ($model) {
            self::logAudit($model, 'updated', $model->getChanges(), $model->getOriginal());
        });

        static::deleted(function ($model) {
            self::logAudit($model, 'deleted', null, $model->toArray());
        });
    }

    protected static function logAudit($model, $action, $newValues, $oldValues)
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => $action,
            'table_name' => $model->getTable(),
            'record_id'  => $model->id,
            'ip_address' => request()->ip(),
            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null,
            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null,
        ]);
    }
}
