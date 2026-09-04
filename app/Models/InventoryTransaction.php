<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Notifications\LowStockNotification;

class InventoryTransaction extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'batch_id',
        'type',
        'qty_in',
        'qty_out',
        'balance_after',
    ];

    protected static function booted()
    {
        static::created(function ($transaction) {
            if ($transaction->qty_out > 0) {
                $product = $transaction->product;

                if ($product) {
                    // خصم الكمية مباشرة من حقل stock_quantity في جدول products
                    $product->decrement('stock_quantity', $transaction->qty_out);

                    // فحص ما إذا كانت الكمية الحالية وصلت أو قلّت عن حد التنبيه alert_quantity
                    if ($product->stock_quantity <= $product->alert_quantity) {

                        $admins = User::where('is_active', true)->get();

                        foreach ($admins as $admin) {
                            (new LowStockNotification($admin, $product, $transaction))->send();
                        }
                    }
                }
            }
        });
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}