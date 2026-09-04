<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\User;
use App\Notifications\LowStockNotification;

class CheckLowStock extends Command
{
    protected $signature = 'stock:check-low';
    protected $description = 'فحص مخزون الأدوية والمنتجات وإرسال تنبيهات عند الوصول للحد الأدنى';

    public function handle()
    {
        // جلب كل المنتجات التي وصلت أو قلّت عن حد التنبيه (alert_quantity)
        $products = Product::where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'alert_quantity')
            ->get();

        foreach ($products as $product) {
            $admins = User::where('is_active', true)->get();

            foreach ($admins as $admin) {
                // كائن وهمي لتمرير الرصيد الحالي لكلاس الإشعار
                $fakeTransaction = (object) [
                    'warehouse_id' => null,
                    'balance_after' => $product->stock_quantity
                ];

                (new LowStockNotification($admin, $product, $fakeTransaction))->send();
            }
        }

        $this->info('تم فحص مخزون المنتجات وإرسال التنبيهات بنجاح.');
    }
}
