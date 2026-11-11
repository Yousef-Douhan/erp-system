<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\ProductBatch;
use App\Models\User;
use App\Support\Stock;
use Illuminate\Console\Command;

/**
 * ملخص يومي لكل فرع: الدفعات المنتهية، القريبة من الانتهاء، النواقص، والعملاء المتجاوزون لحد الدين.
 * يصل لموظفي الفرع، ولمستخدمي الصيدلية (صاحبها) لكل فرع من فروعها. لا تختلط بيانات الصيدليات.
 */
class DailyStockAlerts extends Command
{
    protected $signature = 'alerts:daily {--days=30 : عدد الأيام لتنبيه قرب انتهاء الصلاحية}';
    protected $description = 'إرسال تنبيه يومي بالنواقص والدفعات المنتهية والقريبة من الانتهاء لكل فرع';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $today = today();
        $sent = 0;

        $users = User::where('is_active', true)->get()->filter(fn (User $u) => $u->can('view_pharmacy'));

        foreach (Branch::withoutGlobalScope('tenant')->with('organization:id,name')->get() as $branch) {
            $warehouses = Stock::warehouseIdsOfBranch($branch->id) ?: [0];

            $batches = fn () => ProductBatch::withoutGlobalScopes()->with('product:id,name')
                ->whereIn('warehouse_id', $warehouses)->where('quantity', '>', 0);
            $expired = $batches()->whereDate('expiry_date', '<', $today)->orderBy('expiry_date')->get();
            $expiring = $batches()->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', $today->copy()->addDays($days))->orderBy('expiry_date')->get();
            $low = Stock::lowStockQuery($branch->id)
                ->where('products.organization_id', $branch->organization_id)
                ->orderBy('stock_quantity')->get();
            $overLimit = Customer::withoutGlobalScope('tenant')->where('branch_id', $branch->id)
                ->where('credit_limit', '>', 0)->whereColumn('balance', '>', 'credit_limit')->count();

            if ($expired->isEmpty() && $expiring->isEmpty() && $low->isEmpty() && !$overLimit) {
                continue;
            }

            $names = fn ($c, $get) => $c->take(5)->map($get)->implode('، ') . ($c->count() > 5 ? '، وغيرها' : '');
            $parts = [];
            if ($expired->isNotEmpty()) {
                $parts[] = "• {$expired->count()} دفعة منتهية الصلاحية ما زالت في المخزون: " . $names($expired, fn ($b) => ($b->product->name ?? '—') . " ({$b->batch_number})");
            }
            if ($expiring->isNotEmpty()) {
                $parts[] = "• {$expiring->count()} دفعة تنتهي خلال {$days} يوماً: " . $names($expiring, fn ($b) => ($b->product->name ?? '—') . ' ' . $b->expiry_date);
            }
            if ($low->isNotEmpty()) {
                $parts[] = "• {$low->count()} صنف وصل للحد الأدنى: " . $names($low, fn ($p) => "{$p->name} ({$p->stock_quantity})");
            }
            if ($overLimit) {
                $parts[] = "• {$overLimit} عميل تجاوز حد الدين المسموح.";
            }

            $title = 'ملخص المخزون اليومي — ' . $branch->name;
            $recipients = $users->filter(fn (User $u) => (int) $u->organization_id === (int) $branch->organization_id
                && (!$u->branch_id || (int) $u->branch_id === (int) $branch->id));

            foreach ($recipients as $u) {
                $exists = Notification::where('user_id', $u->id)->where('type', 'DailyStock')
                    ->where('title', $title)->whereDate('created_at', $today)->exists();
                if ($exists) {
                    continue;
                }

                Notification::create([
                    'user_id' => $u->id,
                    'type'    => 'DailyStock',
                    'title'   => $title,
                    'message' => implode("\n", $parts),
                    'is_read' => false,
                ]);
                \Illuminate\Support\Facades\Cache::forget('layout.unread.' . $u->id);
                $sent++;
            }
        }

        $this->info($sent ? "تم إرسال {$sent} تنبيه." : 'لا توجد تنبيهات اليوم.');

        return self::SUCCESS;
    }
}
