<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * التقارير: الأرباح، مبيعات الموظفين، الأصناف الراكدة. كل تقرير يُصدَّر CSV (يفتح في Excel) ويُطبع PDF.
 */
class ReportController extends Controller
{
    /**
     * تكلفة سطر البيع: التكلفة المسجلة وقت البيع، أو سعر شراء المنتج للفواتير القديمة
     */
    private const COST_SQL = 'COALESCE(sale_items.unit_cost, products.purchase_price * COALESCE(sale_items.unit_factor, 1))';
    private const NET_QTY_SQL = '(sale_items.quantity - COALESCE(sale_items.returned_quantity, 0))';

    public function profit(Request $request)
    {
        [$from, $to] = $this->period($request);
        $user = $request->user();

        // ---------- الملخص ----------
        $sales = $this->salesQuery($user, $from, $to)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total - returned_amount), 0) as revenue, COALESCE(SUM(returned_amount), 0) as returned')
            ->first();

        $cogs = (float) $this->itemsQuery($user, $from, $to)
            ->selectRaw('COALESCE(SUM(' . self::COST_SQL . ' * ' . self::NET_QTY_SQL . '), 0) as cogs')
            ->value('cogs');

        $expenses = (float) $this->expensesQuery($user)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $revenue = (float) $sales->revenue;
        $gross = round($revenue - $cogs, 2);
        $summary = [
            'count'    => (int) $sales->cnt,
            'revenue'  => $revenue,
            'returned' => (float) $sales->returned,
            'cogs'     => round($cogs, 2),
            'gross'    => $gross,
            'margin'   => $revenue > 0 ? round($gross / $revenue * 100, 1) : 0,
            'expenses' => $expenses,
            'net'      => round($gross - $expenses, 2),
        ];

        // ---------- يومياً ----------
        $dailyRevenue = $this->salesQuery($user, $from, $to)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as cnt, SUM(total - returned_amount) as revenue')
            ->groupBy('day')->get()->keyBy('day');
        $dailyCost = $this->itemsQuery($user, $from, $to)
            ->selectRaw('DATE(sales.created_at) as day, SUM(' . self::COST_SQL . ' * ' . self::NET_QTY_SQL . ') as cogs')
            ->groupBy('day')->pluck('cogs', 'day');
        $dailyExpenses = $this->expensesQuery($user)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('DATE(expense_date) as day, SUM(amount) as amount')
            ->groupBy('day')->pluck('amount', 'day');

        $days = collect($dailyRevenue->keys())->merge($dailyExpenses->keys())->unique()->sortDesc()->values();
        $daily = $days->map(function ($day) use ($dailyRevenue, $dailyCost, $dailyExpenses) {
            $rev = (float) ($dailyRevenue[$day]->revenue ?? 0);
            $cost = (float) ($dailyCost[$day] ?? 0);
            $exp = (float) ($dailyExpenses[$day] ?? 0);

            return [
                'day' => $day, 'count' => (int) ($dailyRevenue[$day]->cnt ?? 0), 'revenue' => $rev,
                'cogs' => round($cost, 2), 'gross' => round($rev - $cost, 2), 'expenses' => $exp, 'net' => round($rev - $cost - $exp, 2),
            ];
        });

        // ---------- حسب الصنف ----------
        $byProduct = $this->itemsQuery($user, $from, $to)
            ->groupBy('sale_items.product_id', 'products.name')
            ->selectRaw('products.name,
                SUM(' . self::NET_QTY_SQL . ' * COALESCE(sale_items.unit_factor, 1)) as qty,
                SUM(sale_items.total * ' . self::NET_QTY_SQL . ' / NULLIF(sale_items.quantity, 0)) as revenue,
                SUM(' . self::COST_SQL . ' * ' . self::NET_QTY_SQL . ') as cogs')
            ->havingRaw('SUM(' . self::NET_QTY_SQL . ') > 0')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name, 'qty' => (float) $r->qty, 'revenue' => round((float) $r->revenue, 2), 'cogs' => round((float) $r->cogs, 2),
                'profit' => round((float) $r->revenue - (float) $r->cogs, 2),
                'margin' => (float) $r->revenue > 0 ? round(((float) $r->revenue - (float) $r->cogs) / (float) $r->revenue * 100, 1) : 0,
            ])
            ->sortByDesc('profit')
            ->values();

        if ($request->input('export') === 'csv') {
            $section = $request->input('section', 'daily');
            return $section === 'products'
                ? $this->csv("profit-products-{$from->toDateString()}-{$to->toDateString()}.csv",
                    ['الصنف', 'الكمية (وحدة أساسية)', 'الإيراد', 'التكلفة', 'الربح', 'الهامش %'],
                    $byProduct->map(fn ($r) => [$r['name'], $r['qty'], $r['revenue'], $r['cogs'], $r['profit'], $r['margin']]))
                : $this->csv("profit-daily-{$from->toDateString()}-{$to->toDateString()}.csv",
                    ['اليوم', 'عدد الفواتير', 'صافي المبيعات', 'التكلفة', 'مجمل الربح', 'المصروفات', 'صافي الربح'],
                    $daily->map(fn ($r) => [$r['day'], $r['count'], $r['revenue'], $r['cogs'], $r['gross'], $r['expenses'], $r['net']]));
        }

        $tab = 'profit';
        return view('reports.profit', compact('tab', 'from', 'to', 'summary', 'daily', 'byProduct'));
    }

    public function employees(Request $request)
    {
        [$from, $to] = $this->period($request);
        $user = $request->user();

        $rows = $this->salesQuery($user, $from, $to)
            ->selectRaw('employee_id, COUNT(*) as cnt, SUM(total) as gross, SUM(returned_amount) as returned,
                SUM(total - returned_amount) as net, SUM(paid_amount) as paid, SUM(discount) as discount')
            ->groupBy('employee_id')
            ->get();

        $names = User::whereIn('id', $rows->pluck('employee_id'))->pluck('username', 'id');
        $cogs = $this->itemsQuery($user, $from, $to)
            ->selectRaw('sales.employee_id, SUM(' . self::COST_SQL . ' * ' . self::NET_QTY_SQL . ') as cogs')
            ->groupBy('sales.employee_id')
            ->pluck('cogs', 'employee_id');

        $employees = $rows->map(fn ($r) => [
            'name'     => $names[$r->employee_id] ?? 'مستخدم محذوف',
            'count'    => (int) $r->cnt,
            'gross'    => (float) $r->gross,
            'returned' => (float) $r->returned,
            'net'      => (float) $r->net,
            'paid'     => (float) $r->paid,
            'discount' => (float) $r->discount,
            'avg'      => $r->cnt ? round((float) $r->net / $r->cnt, 2) : 0,
            'profit'   => round((float) $r->net - (float) ($cogs[$r->employee_id] ?? 0), 2),
        ])->sortByDesc('net')->values();

        if ($request->input('export') === 'csv') {
            return $this->csv("employees-{$from->toDateString()}-{$to->toDateString()}.csv",
                ['الموظف', 'عدد الفواتير', 'إجمالي المبيعات', 'المرتجعات', 'صافي المبيعات', 'المحصّل', 'الخصومات', 'متوسط الفاتورة', 'مجمل الربح'],
                $employees->map(fn ($e) => [$e['name'], $e['count'], $e['gross'], $e['returned'], $e['net'], $e['paid'], $e['discount'], $e['avg'], $e['profit']]));
        }

        $tab = 'employees';
        return view('reports.employees', compact('tab', 'from', 'to', 'employees'));
    }

    public function slowMoving(Request $request)
    {
        $days = max(7, min(365, (int) $request->input('days', 60)));
        $since = today()->subDays($days);
        $user = $request->user();

        $lastSold = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')->whereNull('sales.deleted_at')
            ->selectRaw('sale_items.product_id, MAX(sales.created_at) as last_sold')
            ->groupBy('sale_items.product_id');

        // مستخدم الفرع: رصيد فرعه ومبيعات فرعه. غيره: إجمالي الصيدلية
        $branchId = \App\Support\Stock::viewBranchId();
        $qtyCol = $branchId ? 'COALESCE(bs.quantity, 0)' : 'products.stock_quantity';
        if ($branchId) {
            $lastSold->where('sales.branch_id', $branchId);
        }

        $query = Product::query()
            ->where('products.is_active', true)
            ->when($branchId, fn ($q) => $q->leftJoin('branch_stocks as bs', fn ($j) => $j->on('bs.product_id', '=', 'products.id')->where('bs.branch_id', $branchId)))
            ->whereRaw("{$qtyCol} > 0")
            ->leftJoinSub($lastSold, 'ls', 'ls.product_id', '=', 'products.id')
            ->where(fn ($q) => $q->whereNull('ls.last_sold')->orWhere('ls.last_sold', '<', $since))
            ->select('products.id', 'products.name', 'products.purchase_price', 'products.selling_price', 'ls.last_sold')
            ->selectRaw("{$qtyCol} as stock_quantity")
            ->selectRaw("{$qtyCol} * products.purchase_price as stock_value")
            ->orderByDesc('stock_value');

        $totals = (clone $query)->reorder()->toBase()
            ->select(DB::raw('COUNT(*) as cnt'), DB::raw("COALESCE(SUM({$qtyCol} * products.purchase_price), 0) as value"))
            ->first();

        if ($request->input('export') === 'csv') {
            return $this->csv("slow-moving-{$days}d.csv",
                ['الصنف', 'الرصيد', 'سعر الشراء', 'قيمة المخزون', 'آخر بيع'],
                $query->get()->map(fn ($p) => [$p->name, $p->stock_quantity, $p->purchase_price, round((float) $p->stock_value, 2), $p->last_sold ? Carbon::parse($p->last_sold)->toDateString() : 'لم يُبع']));
        }

        $products = $query->paginate(30)->withQueryString();
        $tab = 'slow';

        return view('reports.slow', compact('tab', 'days', 'products', 'totals'));
    }

    // ===================== مساعدات =====================

    private function period(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : today()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : today()->endOfDay();

        return $from->gt($to) ? [$to->copy()->startOfDay(), $from->copy()->endOfDay()] : [$from, $to];
    }

    private function salesQuery(User $user, Carbon $from, Carbon $to)
    {
        return Sale::visibleTo($user)->whereBetween('created_at', [$from, $to]);
    }

    /**
     * أسطر البيع: الفلترة بالصيدلية/الفرع تلقائية (عبر الفاتورة)
     */
    private function itemsQuery(User $user, Carbon $from, Carbon $to)
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')->whereNull('sales.deleted_at')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.created_at', [$from, $to]);
    }

    private function expensesQuery(User $user)
    {
        return Expense::query();
    }

    /**
     * ملف CSV بترميز UTF-8 مع BOM حتى يفتحه Excel بالعربية بشكل صحيح
     */
    private function csv(string $filename, array $headers, $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_values((array) $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
