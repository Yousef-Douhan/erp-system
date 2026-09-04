<?php

namespace App\Notifications;

use App\Models\Notification as NotificationModel;

class LowStockNotification
{
    public $product;
    public $transaction;
    public $user;

    public function __construct($user, $product, $transaction)
    {
        $this->user = $user;
        $this->product = $product;
        $this->transaction = $transaction;
    }

    public function send()
    {
        return NotificationModel::create([
            'user_id' => $this->user->id,
            'type' => 'LowStock',
            'title' => 'تنبيه نقص مخزون',
            'message' => "الدواء/المنتج ({$this->product->name}) وصل للحد الأدنى. الرصيد الحالي: {$this->transaction->balance_after}",
            'is_read' => false,
        ]);
    }
}
