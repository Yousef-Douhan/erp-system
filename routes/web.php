<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdvancedAccountingController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerDebtController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DrugAlertController;
use App\Http\Controllers\EmployeeBranchController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\JournalEntryLineController;
use App\Http\Controllers\ManufacturerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ProductBarcodeController;
use App\Http\Controllers\ProductBatchController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductStockSettingController;
use App\Http\Controllers\ProductUnitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseItemController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleItemController;
use App\Http\Controllers\SupplierAccountController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// توجيه الزائر تلقائياً لصفحة تسجيل الدخول
Route::get('/', function () {
    return redirect()->route('login');
});

// مسار لوحة التحكم المحمي
Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

// المسارات المحمية بتسجيل الدخول
Route::middleware('auth')->group(function () {

    // إدارة الملف الشخصي
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 1. الحركات المخزنية (يجب أن تكون قبل الـ Resource لتجنب التعارض)
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/transactions', [InventoryTransactionController::class, 'index'])->name('transactions.index');
        Route::get('/stock-in', [InventoryTransactionController::class, 'createStockIn'])->name('transactions.stock-in');
        Route::post('/stock-in', [InventoryTransactionController::class, 'storeStockIn'])->name('transactions.store-in');
    });

    // 2. الموارد الأساسية للنظام
    Route::resource('roles', RoleController::class);
    Route::resource('users', UserController::class);
    Route::resource('inventory', InventoryController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('brands', BrandController::class);
    Route::resource('units', UnitController::class);
    Route::resource('products', ProductController::class);
    Route::resource('manufacturers', ManufacturerController::class);
    Route::resource('products.units', ProductUnitController::class);
    // مسارات باركودات وحدات المنتجات
    Route::resource('products.units.barcodes', ProductBarcodeController::class)
        ->except(['create', 'show', 'edit']);
    Route::get('/dashboard', function () {
        $totalSalesCount = Sale::count();
        $totalSalesAmount = Sale::sum('total');
        $totalPaidAmount = Sale::sum('paid_amount');
        $totalUnpaidAmount = $totalSalesAmount - $totalPaidAmount;

        $customersCount = Customer::count();
        $latestSales = Sale::with(['customer', 'organization'])->latest()->take(5)->get();

        return view('dashboard', compact(
            'totalSalesCount',
            'totalSalesAmount',
            'totalPaidAmount',
            'totalUnpaidAmount',
            'customersCount',
            'latestSales'
        ));
    })->middleware(['auth', 'verified'])->name('dashboard');
    Route::resource('stock-settings', ProductStockSettingController::class)->except(['create', 'show', 'edit']);
    Route::resource('customers', CustomerController::class)->except(['create', 'show', 'edit']);
    Route::resource('suppliers', SupplierController::class)->except(['create', 'show', 'edit']);
    Route::resource('sales', SaleController::class)->except(['create', 'show', 'edit']);
    Route::post('sales/{sale}/items', [SaleItemController::class, 'store'])->name('sale-items.store');
    Route::put('sale-items/{saleItem}', [SaleItemController::class, 'update'])->name('sale-items.update');
    Route::delete('sale-items/{saleItem}', [SaleItemController::class, 'destroy'])->name('sale-items.destroy');
    Route::resource('sales', SaleController::class)->except(['create', 'edit']);
    Route::post('/sales/{sale}/pay', [SaleController::class, 'pay'])->name('sales.pay');
    Route::resource('purchases', PurchaseController::class);
    Route::post('/purchases/{purchase}/items', [PurchaseItemController::class, 'store'])->name('purchase-items.store');
    Route::put('/purchase-items/{purchaseItem}', [PurchaseItemController::class, 'update'])->name('purchase-items.update');
    Route::delete('/purchase-items/{purchaseItem}', [PurchaseItemController::class, 'destroy'])->name('purchase-items.destroy');
    // 1. دليل الحسابات (Accounts Chart)
    Route::resource('accounts', AccountController::class);

    // 2. القيود اليومية (Journal Entries)
    Route::resource('journal-entries', JournalEntryController::class);
    Route::resource('journal-entry-lines', JournalEntryLineController::class)->only(['store', 'destroy']);

    // 3. حسابات العملاء والموردين
    Route::resource('customer-accounts', CustomerAccountController::class)->only(['index', 'store', 'show']);
    Route::resource('supplier-accounts', SupplierAccountController::class)->only(['index', 'store', 'show']);

    // 4. الخزائن والصناديق النقدية (Cash Registers)
    Route::resource('cash-registers', CashRegisterController::class);

    // 5. المصروفات التشغيلية (Expenses)
    Route::resource('expenses', ExpenseController::class);

    // 6. العمليات المالية المتقدمة (سندات القبض والصرف)
    Route::post('receipt-vouchers', [AdvancedAccountingController::class, 'storeReceiptVoucher'])->name('receipt-vouchers.store');
    Route::post('payment-vouchers', [AdvancedAccountingController::class, 'storePaymentVoucher'])->name('payment-vouchers.store');

    // 7. التقارير المالية الختامية
    Route::get('reports/income-statement', [AdvancedAccountingController::class, 'incomeStatement'])->name('reports.income-statement');
    Route::get('reports/balance-sheet', [AdvancedAccountingController::class, 'balanceSheet'])->name('reports.balance-sheet');
    Route::resource('expenses', ExpenseController::class);
    Route::resource('prescriptions', PrescriptionController::class);
    Route::resource('drug-alerts', DrugAlertController::class);
    Route::resource('inventory-transactions', InventoryTransactionController::class);
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/{audit_log}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    Route::delete('/audit-logs/{audit_log}', [AuditLogController::class, 'destroy'])->name('audit-logs.destroy');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/store', [PosController::class, 'store'])->name('pos.store');
    Route::post('/pos/customer', [PosController::class, 'storeCustomer'])->name('pos.customer.store');
    Route::resource('payment-methods', PaymentMethodController::class)->except(['create', 'show', 'edit']);
    Route::get('/debts', [CustomerDebtController::class, 'index'])->name('debts.index');
    Route::put('/customers/debts/{id}', [CustomerDebtController::class, 'update'])->name('customers.debts.update');
    Route::get('/customers/{id}/ledger', [CustomerDebtController::class, 'ledger'])->name('customers.debts.ledger');

    // مسارات الدفعات المستقلة (تعرض كل دفعات النظام في صفحة واحدة)
    Route::get('batches', [ProductBatchController::class, 'indexAll'])->name('batches.index');
    Route::post('batches', [ProductBatchController::class, 'storeAll'])->name('batches.store');
    Route::put('batches/{batch}', [ProductBatchController::class, 'updateAll'])->name('batches.update');
    Route::delete('batches/{batch}', [ProductBatchController::class, 'destroyAll'])->name('batches.destroy');
    Route::resource('organizations', OrganizationController::class);
    Route::resource('branches', BranchController::class)->except(['create', 'show', 'edit']);
    Route::resource('employees', EmployeeController::class);
    Route::resource('employee-branches', EmployeeBranchController::class);
    


    // نقطة البيع (POS) - يمكنك تفعيلها لاحقاً هكذا:
    // Route::resource('pos', PosController::class);
});

// مسارات المصادقة الخاصة بـ Breeze
require __DIR__ . '/auth.php';
