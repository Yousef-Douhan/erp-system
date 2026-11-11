<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\SaleItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * تصحيح الأصناف التي سعر شرائها أعلى من سعر بيعها (بيانات تجريبية أو سعر علبة مُدخل بدل الحبة).
 * سعر الشراء الجديد = سعر البيع × النسبة (افتراضياً 70%)، ويُصحَّح معه:
 * أسعار شراء وحدات البيع، تكلفة الدفعات، وتكلفة أسطر الفواتير المسجلة وقت البيع.
 */
class FixProductCosts extends Command
{
    protected $signature = 'products:fix-costs
        {--ratio=0.70 : سعر الشراء الجديد كنسبة من سعر البيع}
        {--dry-run : عرض ما سيتغير فقط بدون حفظ}
        {--force : التنفيذ بدون سؤال تأكيد}';

    protected $description = 'تصحيح سعر الشراء للأصناف التي سعر شرائها أعلى من سعر بيعها';

    public function handle(): int
    {
        $ratio = (float) $this->option('ratio');
        if ($ratio <= 0 || $ratio >= 1) {
            $this->error('النسبة يجب أن تكون بين 0 و 1، مثل 0.70');
            return self::FAILURE;
        }

        $products = Product::where('selling_price', '>', 0)
            ->whereColumn('purchase_price', '>', 'selling_price')
            ->orderBy('name')
            ->get(['id', 'name', 'purchase_price', 'selling_price']);

        if ($products->isEmpty()) {
            $this->info('لا توجد أصناف سعر شرائها أعلى من سعر بيعها. لا شيء للتصحيح.');
            return self::SUCCESS;
        }

        $ids = $products->pluck('id');
        $rows = $products->map(fn ($p) => [
            $p->id,
            $p->name,
            number_format((float) $p->purchase_price, 2),
            number_format((float) $p->selling_price, 2),
            number_format(round((float) $p->selling_price * $ratio, 2), 2),
            SaleItem::where('product_id', $p->id)->count(),
        ])->all();

        $this->table(['#', 'الصنف', 'سعر الشراء الحالي', 'سعر البيع', 'سعر الشراء الجديد', 'أسطر بيع'], $rows);
        $this->line('سيُصحَّح أيضاً: أسعار شراء وحدات البيع (علبة/شريط)، تكلفة الدفعات، وتكلفة أسطر الفواتير المسجلة.');

        if ($this->option('dry-run')) {
            $this->warn('تجربة فقط (--dry-run): لم يُحفظ أي تغيير.');
            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('تنفيذ التصحيح على ' . $products->count() . ' صنف؟', true)) {
            $this->warn('أُلغي.');
            return self::SUCCESS;
        }

        $counts = DB::transaction(function () use ($products, $ids, $ratio) {
            $c = ['units' => 0, 'batches' => 0, 'items' => 0];

            foreach ($products as $p) {
                $newCost = round((float) $p->selling_price * $ratio, 2);
                $p->update(['purchase_price' => $newCost]);

                // الدفعات: التكلفة للحبة، فأي تكلفة أعلى من سعر بيع الحبة تُصحَّح
                $c['batches'] += ProductBatch::where('product_id', $p->id)
                    ->where('cost_price', '>', (float) $p->selling_price)
                    ->update(['cost_price' => $newCost]);

                // أسطر الفواتير التي سُجلت تكلفتها وقت البيع أعلى من سعر بيعها
                foreach (SaleItem::where('product_id', $p->id)->whereNotNull('unit_cost')->whereColumn('unit_cost', '>', 'unit_price')->get() as $item) {
                    $item->unit_cost = round($newCost * (float) ($item->unit_factor ?: 1), 4);
                    $item->saveQuietly();
                    $c['items']++;
                }
            }

            // وحدات البيع (علبة/شريط): سعر شرائها أعلى من سعر بيعها
            foreach (ProductUnit::whereIn('product_id', $ids)->where('selling_price', '>', 0)->whereColumn('purchase_price', '>', 'selling_price')->get() as $unit) {
                $unit->update(['purchase_price' => round((float) $unit->selling_price * $ratio, 2)]);
                $c['units']++;
            }

            return $c;
        });

        $this->info("تم تصحيح {$products->count()} صنف، و{$counts['units']} وحدة بيع، و{$counts['batches']} دفعة، و{$counts['items']} سطر فاتورة.");
        $this->line('تقرير الأرباح يُحسب مباشرة من هذه القيم، فافتحه من جديد.');

        return self::SUCCESS;
    }
}
