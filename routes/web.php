<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdvancedAccountingController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BarcodeLabelController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CashShiftController;
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
use App\Http\Controllers\ManufacturerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ProductBarcodeController;
use App\Http\Controllers\ProductBatchController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProductStockSettingController;
use App\Http\Controllers\ProductUnitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseItemController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleItemController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierAccountController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| كل مجموعة محمية بصلاحية من Spatie Permission.
| دور Admin يتجاوز كل الفحوصات (Gate::before في AuthServiceProvider).
*/

// توجيه الزائر تلقائياً لصفحة تسجيل الدخول
Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {

    // لوحة التحكم
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/analytics', [DashboardController::class, 'analytics'])->name('analytics')->middleware('permission:view_dashboard');

    // الملف الشخصي
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // الإشعارات
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');

    // ================= الإدارة: المستخدمين والأدوار =================
    Route::resource('roles', RoleController::class)->middleware('permission:manage_roles');
    Route::resource('users', UserController::class)->middleware('permission:manage_users');

    // ================= إعدادات المنشأة =================
    Route::middleware('permission:manage_settings')->group(function () {
        Route::resource('organizations', OrganizationController::class)->except(['create', 'show', 'edit']);
        Route::resource('branches', BranchController::class)->except(['create', 'show', 'edit']);
        Route::resource('employees', EmployeeController::class)->except(['create', 'show', 'edit']);
        Route::resource('employee-branches', EmployeeBranchController::class)->except(['create', 'show', 'edit']);
        Route::resource('payment-methods', PaymentMethodController::class)->except(['create', 'show', 'edit']);
    });

    // ================= الصيدلية والمخزون =================
    Route::middleware('permission:view_pharmacy')->group(function () {
        // الحركات المخزنية (قبل الـ Resource لتجنب التعارض مع inventory/{inventory})
        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/transactions', [InventoryTransactionController::class, 'index'])->name('transactions.index');
            Route::get('/stock-in', [InventoryTransactionController::class, 'createStockIn'])->name('transactions.stock-in');
            Route::post('/stock-in', [InventoryTransactionController::class, 'storeStockIn'])->name('transactions.store-in');
        });

        Route::resource('inventory', InventoryController::class)->except(['show']);
        Route::resource('categories', CategoryController::class)->except(['create', 'show', 'edit']);
        Route::resource('brands', BrandController::class)->except(['create', 'show', 'edit']);
        Route::resource('units', UnitController::class)->except(['create', 'show', 'edit']);
        // استيراد الأدوية من Excel (قبل الـ resource حتى لا يتعارض مع products/{product})
        Route::get('products/import', [ProductImportController::class, 'index'])->name('products.import');
        Route::get('products/import/template', [ProductImportController::class, 'template'])->name('products.import.template');
        Route::post('products/import/preview', [ProductImportController::class, 'preview'])->name('products.import.preview');
        Route::post('products/import', [ProductImportController::class, 'store'])->name('products.import.store');
        Route::resource('products', ProductController::class)->except(['create', 'show', 'edit']);
        Route::resource('manufacturers', ManufacturerController::class)->except(['create', 'show', 'edit']);
        Route::resource('products.units', ProductUnitController::class);
        Route::resource('products.units.barcodes', ProductBarcodeController::class)->except(['create', 'show', 'edit']);
        Route::resource('stock-settings', ProductStockSettingController::class)->except(['create', 'show', 'edit']);
        Route::resource('inventory-transactions', InventoryTransactionController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('prescriptions', PrescriptionController::class)->except(['create', 'show', 'edit']);
        Route::resource('drug-alerts', DrugAlertController::class)->except(['create', 'show', 'edit']);

        // الدفعات (Batches)
        Route::get('batches', [ProductBatchController::class, 'indexAll'])->name('batches.index');
        Route::post('batches', [ProductBatchController::class, 'storeAll'])->name('batches.store');
        Route::put('batches/{batch}', [ProductBatchController::class, 'updateAll'])->name('batches.update');
        Route::delete('batches/{batch}', [ProductBatchController::class, 'destroyAll'])->name('batches.destroy');
    });

    // ================= المبيعات ونقطة البيع =================
    Route::middleware('permission:manage_sales')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('/pos/store', [PosController::class, 'store'])->name('pos.store');
        Route::post('/pos/customer', [PosController::class, 'storeCustomer'])->name('pos.customer.store');

        Route::resource('sales', SaleController::class)->except(['create', 'edit']);
        Route::post('/sales/{sale}/pay', [SaleController::class, 'pay'])->name('sales.pay');
        Route::get('/sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
        Route::post('sales/{sale}/items', [SaleItemController::class, 'store'])->name('sale-items.store');
        Route::put('sale-items/{saleItem}', [SaleItemController::class, 'update'])->name('sale-items.update');
        Route::delete('sale-items/{saleItem}', [SaleItemController::class, 'destroy'])->name('sale-items.destroy');
    });

    // ================= العملاء والديون =================
    Route::middleware('permission:view_customers|manage_sales')->group(function () {
        Route::resource('customers', CustomerController::class)->except(['create', 'show', 'edit']);
        Route::get('/debts', [CustomerDebtController::class, 'index'])->name('debts.index');
        Route::put('/customers/debts/{id}', [CustomerDebtController::class, 'update'])->name('customers.debts.update');
        Route::get('/customers/{id}/ledger', [CustomerDebtController::class, 'ledger'])->name('customers.debts.ledger');
    });

    // ================= المشتريات والموردين =================
    Route::middleware('permission:view_purchases')->group(function () {
        Route::resource('suppliers', SupplierController::class)->except(['create', 'show', 'edit']);
        Route::resource('purchases', PurchaseController::class)->except(['create', 'show', 'edit']);
        Route::post('/purchases/{purchase}/items', [PurchaseItemController::class, 'store'])->name('purchase-items.store');
        Route::post('/purchases/{purchase}/pay', [PurchaseController::class, 'pay'])->name('purchases.pay');
        Route::put('/purchase-items/{purchaseItem}', [PurchaseItemController::class, 'update'])->name('purchase-items.update');
        Route::delete('/purchase-items/{purchaseItem}', [PurchaseItemController::class, 'destroy'])->name('purchase-items.destroy');
    });

    // ================= المحاسبة =================
    Route::middleware('permission:manage_accounting')->group(function () {
        Route::resource('accounts', AccountController::class);
        Route::resource('journal-entries', JournalEntryController::class);
        Route::resource('customer-accounts', CustomerAccountController::class)->only(['index', 'store', 'show']);
        Route::resource('supplier-accounts', SupplierAccountController::class)->only(['index', 'store', 'show']);
        Route::resource('cash-registers', CashRegisterController::class)->except(['create', 'show', 'edit']);
        Route::resource('expenses', ExpenseController::class)->except(['create', 'show', 'edit']);

        // سندات القبض والصرف
        Route::post('receipt-vouchers', [AdvancedAccountingController::class, 'storeReceiptVoucher'])->name('receipt-vouchers.store');
        Route::post('payment-vouchers', [AdvancedAccountingController::class, 'storePaymentVoucher'])->name('payment-vouchers.store');

        // التقارير المالية
        Route::get('reports/income-statement', [AdvancedAccountingController::class, 'incomeStatement'])->name('reports.income-statement');
        Route::get('reports/balance-sheet', [AdvancedAccountingController::class, 'balanceSheet'])->name('reports.balance-sheet');
    });

    // ================= مرتجعات المبيعات =================
    Route::middleware('permission:return_sales')->group(function () {
        Route::get('/returns', [SaleReturnController::class, 'index'])->name('returns.index');
        Route::get('/returns/{return}', [SaleReturnController::class, 'show'])->name('returns.show');
        Route::get('/sales/{sale}/return', [SaleReturnController::class, 'create'])->name('returns.create');
        Route::post('/sales/{sale}/return', [SaleReturnController::class, 'store'])->name('returns.store');
    });

    // ================= الورديات =================
    Route::middleware('permission:manage_shifts|manage_accounting')->group(function () {
        Route::get('/shifts', [CashShiftController::class, 'index'])->name('shifts.index');
        Route::post('/shifts', [CashShiftController::class, 'store'])->name('shifts.store');
        Route::get('/shifts/{shift}', [CashShiftController::class, 'show'])->name('shifts.show');
        Route::post('/shifts/{shift}/close', [CashShiftController::class, 'close'])->name('shifts.close');
    });

    // ================= الجرد =================
    Route::middleware('permission:stock_count')->group(function () {
        Route::resource('stock-counts', StockCountController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::post('/stock-counts/{stockCount}/post', [StockCountController::class, 'post'])->name('stock-counts.post');
    });

    // ================= المستودعات ونقل المخزون =================
    Route::middleware('permission:transfer_stock')->group(function () {
        Route::resource('warehouses', WarehouseController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/stock-transfers', [StockTransferController::class, 'index'])->name('stock-transfers.index');
        Route::post('/stock-transfers', [StockTransferController::class, 'store'])->name('stock-transfers.store');
    });

    // ================= ملصقات الباركود =================
    Route::middleware('permission:print_barcodes')->group(function () {
        Route::get('/barcodes', [BarcodeLabelController::class, 'index'])->name('barcodes.index');
        Route::post('/barcodes/print', [BarcodeLabelController::class, 'print'])->name('barcodes.print');
    });

    // ================= التقارير =================
    Route::middleware('permission:view_reports')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/profit', [ReportController::class, 'profit'])->name('profit');
        Route::get('/employees', [ReportController::class, 'employees'])->name('employees');
        Route::get('/slow-moving', [ReportController::class, 'slowMoving'])->name('slow');
    });

    // ================= النسخ الاحتياطية =================
    Route::middleware('permission:manage_backups')->group(function () {
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
        Route::get('/backups/{file}', [BackupController::class, 'download'])->name('backups.download');
        Route::delete('/backups/{file}', [BackupController::class, 'destroy'])->name('backups.destroy');
    });

    // ================= سلة المحذوفات (كل نوع محمي بصلاحية حذفه داخل المتحكم) =================
    Route::get('/trash', [\App\Http\Controllers\TrashController::class, 'index'])->name('trash.index');
    Route::post('/trash/{type}/{id}/restore', [\App\Http\Controllers\TrashController::class, 'restore'])->name('trash.restore')->whereNumber('id');

    // ================= سجل التدقيق =================
    Route::middleware('permission:view_audit_logs')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/audit-logs/{audit_log}', [AuditLogController::class, 'show'])->name('audit-logs.show');
        Route::delete('/audit-logs/{audit_log}', [AuditLogController::class, 'destroy'])->name('audit-logs.destroy')
            ->middleware('role:Admin');
    });
});

// مسارات المصادقة الخاصة بـ Breeze
require __DIR__ . '/auth.php';
