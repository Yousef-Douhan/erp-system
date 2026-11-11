<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventoryTransactionItem extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'inventory_transaction_id', 'product_id', 'quantity', 'purchase_price', 'selling_price',
    ];

    public function transaction()
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
