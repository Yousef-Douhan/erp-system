<?php

return [
    // عدد النسخ الاحتياطية التي يُحتفظ بها (الأقدم يُحذف تلقائياً)
    'keep' => (int) env('BACKUP_KEEP', 14),

    // وقت النسخ اليومي وتنبيهات المخزون (بتوقيت التطبيق)
    'time'        => env('BACKUP_TIME', '23:30'),
    'alerts_time' => env('ALERTS_TIME', '07:30'),
];
