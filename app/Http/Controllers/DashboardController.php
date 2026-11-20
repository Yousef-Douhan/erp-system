<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** صافي الفاتورة بعد المرتجعات */
    private const NET_SALE = 'total - COALESCE(returned_amount, 0)';

    /**
     * الصفحة الرئيسية: أرقام اليوم، ما يحتاج انتباهاً، وبطاقات الأقسام.
     * كل رقم يُحسب فقط إذا كان المستخدم يملك صلاحية القسم المرتبط به.
     */
    public function index()
    {
        /** @var User $user */
        $user = auth()->user();
        $today = today();

        $canSales     = $user->can('manage_sales');
        $canCustomers = $canSales || $user->can('view_customers');
        $canPharmacy  = $user->can('view_pharmacy');
        $canPurchases = $user->can('view_purchases');
        $canAccounts  = $user->can('manage_accounting');
        $canFigures   = $user->can('view_dashboard');

        // ---------- أرقام اليوم ----------
        $todayFigures = null;
        if ($canFigures) {
            $sales = Sale::visibleTo($user)->whereDate('created_at', $today)
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total - COALESCE(returned_amount, 0)), 0) as total, COALESCE(SUM(paid_amount), 0) as paid')
                ->first();
            $purchases = Purchase::visibleTo($user)->whereDate('created_at', $today)
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as total')
                ->first();
            $expenses = $this->expensesQuery($user)->whereDate('expense_date', $today)
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total')
                ->first();

            // المحصّل اليوم = كل مال دخل فعلاً اليوم: حركات الخزائن (بيع نقدي، دفعات على فواتير، تحصيل ديون، سندات قبض)
            // ناقص الرد النقدي للمرتجعات وإلغاء الفواتير، زائد المبيعات غير النقدية (محفظة/بنك) لأنها لا تمر على خزينة
            $cash = \App\Models\CashMovement::whereDate('created_at', $today)
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' AND type IN ('sale', 'sale_payment', 'debt_collection', 'receipt_voucher') THEN amount ELSE 0 END), 0) AS cash_in")
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' AND type IN ('sale_refund', 'sale_cancel') THEN amount ELSE 0 END), 0) AS cash_out")
                ->selectRaw("COALESCE(SUM(CASE WHEN type IN ('sale_payment', 'debt_collection', 'receipt_voucher') THEN amount ELSE 0 END), 0) AS debts")
                ->first();
            $nonCash = (float) Sale::visibleTo($user)->whereDate('created_at', $today)->whereNull('cash_register_id')->sum('paid_amount');
            // سداد ديون بمحفظة/بنك (مع فاتورة أو على فاتورة قديمة): لا يمر على خزينة فيُؤخذ من دفتر العملاء
            $nonCashDebts = (float) \App\Models\CustomerTransaction::whereDate('created_at', $today)
                ->where('type', 'subtract')->where('source', 'payment_noncash')->sum('amount');

            $todayFigures = [
                'sales'           => (float) $sales->total,
                'sales_count'     => (int) $sales->cnt,
                'collected'       => round((float) $cash->cash_in - (float) $cash->cash_out + $nonCash + $nonCashDebts, 2),
                'debt_collected'  => round((float) $cash->debts + $nonCashDebts, 2),
                'credit'          => round(max(0, (float) $sales->total - (float) $sales->paid), 2),
                'purchases'       => (float) $purchases->total,
                'purchases_count' => (int) $purchases->cnt,
                'expenses'        => (float) $expenses->total,
                'expenses_count'  => (int) $expenses->cnt,
            ];
        }

        // ---------- مؤشرات الأقسام (تظهر كشارات على البطاقات) ----------
        $stats = [
            'today_sales'      => $todayFigures['sales_count'] ?? ($canSales ? Sale::visibleTo($user)->whereDate('created_at', $today)->count() : 0),
            'unpaid_sales'     => $canSales ? Sale::visibleTo($user)->where('status', '!=', 'paid')->count() : 0,
            'debtors'          => $canCustomers ? Customer::visibleTo($user)->where('balance', '>', 0)->count() : 0,
            'over_limit'       => $canCustomers ? Customer::visibleTo($user)->where('credit_limit', '>', 0)->whereColumn('balance', '>', 'credit_limit')->count() : 0,
            'low_stock'        => $canPharmacy ? \App\Support\Stock::lowStockQuery()->count() : 0,
            'products'         => $canPharmacy ? Product::visibleTo($user)->where('is_active', true)->count() : 0,
            'expired'          => $canPharmacy ? ProductBatch::where('quantity', '>', 0)->whereDate('expiry_date', '<', $today)->count() : 0,
            'expiring'         => $canPharmacy ? ProductBatch::where('quantity', '>', 0)->whereDate('expiry_date', '>=', $today)->whereDate('expiry_date', '<=', $today->copy()->addDays(30))->count() : 0,
            'today_purchases'  => $todayFigures['purchases_count'] ?? ($canPurchases ? Purchase::visibleTo($user)->whereDate('created_at', $today)->count() : 0),
            'unpaid_purchases' => $canPurchases ? Purchase::visibleTo($user)->where('status', '!=', 'paid')->count() : 0,
            'suppliers'        => $canPurchases ? Supplier::count() : 0,
            'today_expenses'   => $todayFigures['expenses_count'] ?? ($canAccounts ? $this->expensesQuery($user)->whereDate('expense_date', $today)->count() : 0),
            'cash'             => $canAccounts ? (float) CashRegister::where('status', 'active')->sum('current_balance') : 0,
            'users'            => $user->can('manage_users') ? \App\Support\Tenancy::constrain(User::where('is_active', true), \App\Support\Tenancy::active() ? 'organization_id' : null, 'branch_id')->count() : 0,
        ];

        // ---------- يحتاج انتباهك ----------
        $attention = collect([
            ['show' => $canPharmacy,  'count' => $stats['expired'],          'tone' => 'red',   'icon' => 'alert',  'route' => 'batches.index',   'text' => 'دفعات منتهية الصلاحية ما زالت في المخزون'],
            ['show' => $canPharmacy,  'count' => $stats['low_stock'],        'tone' => 'amber', 'icon' => 'pill',   'route' => 'products.index',  'text' => 'أصناف قاربت على النفاد'],
            ['show' => $canPharmacy,  'count' => $stats['expiring'],         'tone' => 'amber', 'icon' => 'layers', 'route' => 'batches.index',   'text' => 'دفعات تنتهي خلال 30 يوماً'],
            ['show' => $canCustomers, 'count' => $stats['over_limit'],       'tone' => 'red',   'icon' => 'debt',   'route' => 'debts.index',     'text' => 'عملاء تجاوزوا حد الدين'],
            ['show' => $user->can('verify_transfers'), 'count' => $user->can('verify_transfers') ? \App\Models\TransferCheck::where('status', 'pending')->whereDate('created_at', '<', $today)->count() : 0,
                'tone' => 'amber', 'icon' => 'wallet', 'route' => 'transfers.index', 'text' => 'حوالات بنك/محفظة من أيام سابقة لم يُتأكد من وصولها'],
            ['show' => $user->can('verify_transfers'), 'count' => $user->can('verify_transfers') ? \App\Models\TransferCheck::where('status', 'rejected')->count() : 0,
                'tone' => 'red', 'icon' => 'wallet', 'route' => 'transfers.index', 'text' => 'حوالات سُجّلت «لم تصل» — تابعها مع الزبائن'],
            ['show' => $user->can('manage_accounting'), 'count' => $user->can('manage_accounting') && \Illuminate\Support\Facades\Schema::hasColumn('sales', 'needs_review')
                    ? \App\Models\Sale::where('needs_review', true)->whereNull('reviewed_at')->count() : 0,
                'tone' => 'amber', 'icon' => 'receipt', 'route' => 'offline-sales.index', 'text' => 'فواتير بيع بدون إنترنت بحاجة مراجعة'],
            ['show' => $canPurchases, 'count' => $stats['unpaid_purchases'], 'tone' => 'blue',  'icon' => 'truck',  'route' => 'purchases.index', 'text' => 'فواتير شراء لم تُسدَّد بالكامل'],
        ])->filter(fn ($a) => $a['show'] && $a['count'] > 0)->values();

        $greeting = (int) now()->format('H') < 12 ? 'صباح الخير' : 'مساء الخير';
        $todayLabel = now()->locale('ar')->translatedFormat('l، j F Y');
        $roleName = $user->getRoleNames()->first();
        $orgName = $user->organization->name ?? null;

        return view('dashboard', compact(
            'user', 'todayFigures', 'stats', 'attention', 'greeting', 'todayLabel', 'roleName', 'orgName'
        ));
    }

    /**
     * صفحة التحليلات: الرسوم والملخصات التفصيلية
     */
    public function analytics()
    {
        $user = auth()->user();
        $today = today();

        // ---------- المبيعات ----------
        $todaySales = (float) Sale::visibleTo($user)->whereDate('created_at', $today)->sum(DB::raw(self::NET_SALE));
        $todayCount = Sale::visibleTo($user)->whereDate('created_at', $today)->count();
        $yesterdaySales = (float) Sale::visibleTo($user)->whereDate('created_at', $today->copy()->subDay())->sum(DB::raw(self::NET_SALE));
        $monthSales = (float) Sale::visibleTo($user)->where('created_at', '>=', $today->copy()->startOfMonth())->sum(DB::raw(self::NET_SALE));
        $monthCount = Sale::visibleTo($user)->where('created_at', '>=', $today->copy()->startOfMonth())->count();

        // آخر 14 يوماً للرسم
        $from = $today->copy()->subDays(13);
        $daily = Sale::visibleTo($user)->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, SUM(total - COALESCE(returned_amount, 0)) as total, COUNT(*) as count')
            ->groupBy('day')
            ->pluck('total', 'day');
        $series = collect(range(0, 13))->map(function ($i) use ($from, $daily) {
            $d = $from->copy()->addDays($i);
            return ['date' => $d, 'total' => (float) ($daily[$d->toDateString()] ?? 0)];
        });

        // ---------- الديون والنقدية ----------
        $receivables = (float) Customer::visibleTo($user)->sum('balance');
        $debtorsCount = Customer::visibleTo($user)->where('balance', '>', 0)->count();
        $cashOnHand = (float) CashRegister::where('status', 'active')->sum('current_balance');
        $registersCount = CashRegister::where('status', 'active')->count();

        // ---------- المخزون ----------
        $lowStock = \App\Support\Stock::lowStockQuery()
            ->orderBy('stock_quantity')
            ->limit(6)
            ->get();
        $lowStockCount = \App\Support\Stock::lowStockQuery()->count();

        $expiring = ProductBatch::with('product:id,name')
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $today->copy()->addDays(60))
            ->orderBy('expiry_date')
            ->limit(6)
            ->get();
        $expiredCount = ProductBatch::where('quantity', '>', 0)->whereDate('expiry_date', '<', $today)->count();

        // ---------- الأكثر مبيعاً هذا الشهر ----------
        $topProducts = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')->whereNull('sales.deleted_at')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.created_at', '>=', $today->copy()->startOfMonth())
            ->groupBy('sale_items.product_id', 'products.name')
            ->select('products.name', DB::raw('SUM(sale_items.quantity * COALESCE(sale_items.unit_factor, 1)) as qty'), DB::raw('SUM(sale_items.total) as revenue'))
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        $latestSales = Sale::visibleTo($user)->with('customer:id,name')->latest()->take(7)->get();

        return view('analytics', compact(
            'user', 'todaySales', 'todayCount', 'yesterdaySales', 'monthSales', 'monthCount', 'series',
            'receivables', 'debtorsCount', 'cashOnHand', 'registersCount',
            'lowStock', 'lowStockCount', 'expiring', 'expiredCount', 'topProducts', 'latestSales'
        ));
    }

    /**
     * المصروفات مرتبطة بالفرع، فتُحصر في فروع منظمة المستخدم (إلا للمدير)
     */
    private function expensesQuery(User $user)
    {
        // الفلترة بالصيدلية/الفرع تلقائية
        return Expense::query();
    }
}
