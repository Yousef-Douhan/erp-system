<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * دليل الصلاحيات: أسماء عربية وشرح لكل صلاحية، مقسمة حسب أقسام النظام.
 * أي صلاحية في قاعدة البيانات غير موجودة هنا تظهر تحت "صلاحيات أخرى".
 */
class PermissionCatalog
{
    public const ADMIN_ROLE = 'Admin';
    public const OWNER_ROLE = 'صاحب صيدلية';
    public const BRANCH_MANAGER_ROLE = 'مدير فرع';
    public const CASHIER_ROLE = 'كاشير';

    public static function groups(): array
    {
        return [
            'general' => [
                'title' => 'عام',
                'hint'  => 'الدخول للنظام ولوحة التحكم',
                'icon'  => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5z',
                'items' => [
                    'view_dashboard' => ['عرض الأرقام والتحليلات', 'أرقام اليوم في الصفحة الرئيسية وصفحة التحليلات'],
                ],
            ],
            'sales' => [
                'title' => 'المبيعات والعملاء',
                'hint'  => 'نقطة البيع والفواتير والديون',
                'icon'  => 'M4 4h16v10H4zM8 18h8M12 14v4',
                'items' => [
                    'manage_sales'     => ['البيع وعرض الفواتير', 'نقطة البيع، عرض الفواتير، تسجيل الدفعات، ودفتر الديون'],
                    'edit_sales'       => ['تعديل بيانات الفواتير', 'ربط أي فاتورة بعميل وتعديل اسمه ورقم المحفظة (المبالغ لا تُعدَّل أبداً؛ بدونها يعدّل البائع فواتيره من اليوم فقط)'],
                    'delete_sales'     => ['حذف الفواتير', 'حذف فاتورة وإرجاع كمياتها للمخزون'],
                    'return_sales'     => ['مرتجعات المبيعات', 'إرجاع أصناف من فاتورة ورد المبلغ أو تخفيضه من دين العميل'],
                    'manage_shifts'    => ['فتح وإغلاق الوردية', 'فتح وردية على خزينة وإغلاقها بعدّ الصندوق'],
                    'view_customers'   => ['عرض العملاء', 'قائمة العملاء ودفتر الديون'],
                    'create_customers' => ['إضافة عملاء', 'إنشاء عميل جديد'],
                    'edit_customers'   => ['تعديل العملاء والديون', 'تعديل بيانات العميل وحده الائتماني، وإضافة دين يدوي'],
                    'delete_customers' => ['حذف العملاء', 'حذف عميل ليس عليه دين'],
                ],
            ],
            'pharmacy' => [
                'title' => 'الصيدلية والمخزون',
                'hint'  => 'المنتجات والدفعات والوصفات',
                'icon'  => 'M10.5 20.5 3.5 13.5a5 5 0 0 1 7-7l7 7a5 5 0 0 1-7 7zM8.5 8.5l7 7',
                'items' => [
                    'view_pharmacy'   => ['الدخول لقسم الصيدلية', 'المنتجات، الدفعات، الحركات المخزنية، الوصفات والتنبيهات'],
                    'create_products' => ['إضافة منتجات', 'تعريف منتج أو دواء جديد'],
                    'edit_products'   => ['تعديل المنتجات', 'الأسعار، الحد الأدنى، والبيانات'],
                    'delete_products' => ['حذف المنتجات', 'حذف منتج ليس له مبيعات'],
                    'stock_count'     => ['الجرد والإتلاف', 'ورقة الجرد وإدخال الكميات الفعلية، وإتلاف المنتهي والتالف (ترحيل الجرد يحتاج «تعديل المنتجات»)'],
                    'transfer_stock'  => ['المستودعات ونقل المخزون', 'إدارة المستودعات ونقل الدفعات بينها'],
                    'print_barcodes'  => ['طباعة الباركود', 'طباعة ملصقات باركود للأصناف'],
                ],
            ],
            'purchases' => [
                'title' => 'المشتريات',
                'hint'  => 'فواتير الشراء والموردون',
                'icon'  => 'M3 3h2l2.4 12.2a2 2 0 0 0 2 1.8h8.8a2 2 0 0 0 2-1.6L22 7H6',
                'items' => [
                    'view_purchases'   => ['عرض المشتريات والموردين', 'قائمة فواتير الشراء والموردين'],
                    'create_purchases' => ['تسجيل مشتريات ومرتجعاتها', 'إنشاء فاتورة شراء وإدخال بضاعة، وإرجاع أصناف للمورد'],
                    'edit_purchases'   => ['تعديل المشتريات', 'تعديل فاتورة شراء وأصنافها'],
                    'delete_purchases' => ['حذف المشتريات', 'حذف فاتورة شراء وسحب كمياتها من المخزون'],
                    'pay_suppliers'    => ['دفعات الموردين', 'تسجيل دفعة على فاتورة شراء من الخزينة أو البنك'],
                ],
            ],
            'accounting' => [
                'title' => 'المحاسبة',
                'hint'  => 'الخزائن والقيود والتقارير',
                'icon'  => 'M3 3v18h18M8 17V9m5 8V5m5 12v-6',
                'items' => [
                    'manage_accounting' => ['إدارة الحسابات', 'الخزائن، المصروفات، القيود، السندات، والتقارير المالية، وكل الورديات'],
                    'view_reports'      => ['التقارير', 'الأرباح، مبيعات الموظفين، الأصناف الراكدة، والتصدير'],
                    'verify_transfers'     => ['مطابقة الحوالات', 'رؤية دفعات البنك والمحافظ ومستنداتها، وتأكيد وصولها أو أنها لم تصل، وتعديل ذلك لاحقاً'],
                    'upload_transfer_docs' => ['رفع كشوف الحساب وصور الحوالات', 'رفع مستندات البنك/المحافظ ليطابق عليها موظف المطابقة، وحذف المستند غير المستخدم'],
                ],
            ],
            'settings' => [
                'title' => 'إعدادات المنشأة',
                'hint'  => 'المنظمات والفروع والموظفون',
                'icon'  => 'M4 21V3h11v18M15 9h5v12M2 21h20',
                'items' => [
                    'manage_settings' => ['إدارة الإعدادات', 'المنظمات، الفروع، الموظفون، وطرق الدفع'],
                ],
            ],
            'admin' => [
                'title' => 'إدارة النظام',
                'hint'  => 'المستخدمون والصلاحيات والرقابة',
                'icon'  => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
                'items' => [
                    'manage_users'    => ['إدارة المستخدمين', 'إضافة المستخدمين وتعديلهم وإيقافهم'],
                    'manage_roles'    => ['إدارة الأدوار والصلاحيات', 'إنشاء الأدوار وتحديد صلاحياتها'],
                    'view_audit_logs' => ['عرض سجل التدقيق', 'من غيّر ماذا ومتى'],
                    'manage_backups'  => ['النسخ الاحتياطية', 'أخذ نسخة من قاعدة البيانات وتنزيلها وحذفها'],
                ],
            ],
        ];
    }

    /**
     * التأكد من وجود كل صلاحيات الدليل في قاعدة البيانات
     */
    public static function ensureExists(): void
    {
        $names = collect(self::groups())->flatMap(fn ($g) => array_keys($g['items']));
        $existing = Permission::where('guard_name', 'web')->pluck('name');

        foreach ($names->diff($existing) as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /**
     * المجموعات مع الصلاحيات الفعلية من قاعدة البيانات (وأي صلاحية إضافية تحت "أخرى")
     */
    public static function grouped(?Collection $permissions = null): array
    {
        $permissions ??= Permission::where('guard_name', 'web')->orderBy('name')->get();
        $known = [];
        $groups = [];

        foreach (self::groups() as $key => $group) {
            $items = [];
            foreach ($group['items'] as $name => [$label, $hint]) {
                if ($permissions->contains('name', $name)) {
                    $items[] = ['name' => $name, 'label' => $label, 'hint' => $hint];
                    $known[] = $name;
                }
            }
            if ($items) {
                $groups[$key] = $group + ['permissions' => $items];
            }
        }

        $others = $permissions->whereNotIn('name', $known)->map(fn ($p) => [
            'name' => $p->name, 'label' => str_replace('_', ' ', $p->name), 'hint' => null,
        ])->values()->all();

        if ($others) {
            $groups['other'] = [
                'title' => 'صلاحيات أخرى', 'hint' => 'صلاحيات أُضيفت يدوياً',
                'icon' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zM12 8v4m0 4h.01', 'permissions' => $others,
            ];
        }

        return $groups;
    }

    public static function label(string $name): string
    {
        foreach (self::groups() as $group) {
            if (isset($group['items'][$name])) {
                return $group['items'][$name][0];
            }
        }

        return str_replace('_', ' ', $name);
    }
}
