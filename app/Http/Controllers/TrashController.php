<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/**
 * سلة المحذوفات: لا شيء يُمسح من قاعدة البيانات، والحذف يمكن التراجع عنه من هنا.
 * كل نوع يظهر فقط لمن يملك صلاحية حذفه، وضمن صيدليته/فرعه فقط.
 *
 * الفواتير والمشتريات والمصروفات المحذوفة تبقى محفوظة في قاعدة البيانات، لكن لا تُسترجع من هنا
 * لأن حذفها يعكس أثرها على المخزون والحسابات، واسترجاعها بدون إعادة الأثر يفسد الأرصدة.
 */
class TrashController extends Controller
{
    private function types(): array
    {
        return [
            'products'       => ['model' => Product::class,      'label' => 'الأصناف',       'can' => 'delete_products',  'title' => fn ($m) => $m->name, 'sub' => fn ($m) => $m->barcode],
            'customers'      => ['model' => Customer::class,     'label' => 'العملاء',       'can' => 'delete_customers', 'title' => fn ($m) => $m->name, 'sub' => fn ($m) => $m->phone],
            'suppliers'      => ['model' => Supplier::class,     'label' => 'الموردون',      'can' => 'delete_purchases', 'title' => fn ($m) => $m->name, 'sub' => fn ($m) => $m->phone ?? null],
            'users'          => ['model' => User::class,         'label' => 'المستخدمون',    'can' => 'manage_users',     'title' => fn ($m) => $m->username . ($m->full_name ? ' — ' . $m->full_name : ''), 'sub' => fn ($m) => $m->email],
            'categories'     => ['model' => Category::class,     'label' => 'التصنيفات',     'can' => 'delete_products',  'title' => fn ($m) => $m->name, 'sub' => null],
            'brands'         => ['model' => Brand::class,        'label' => 'العلامات',      'can' => 'delete_products',  'title' => fn ($m) => $m->name, 'sub' => null],
            'units'          => ['model' => Unit::class,         'label' => 'الوحدات',       'can' => 'delete_products',  'title' => fn ($m) => $m->name, 'sub' => null],
            'branches'       => ['model' => Branch::class,       'label' => 'الفروع',        'can' => 'manage_settings',  'title' => fn ($m) => $m->name, 'sub' => fn ($m) => $m->phone],
            'warehouses'     => ['model' => Warehouse::class,    'label' => 'المستودعات',    'can' => 'manage_settings',  'title' => fn ($m) => $m->name, 'sub' => null],
            'cash_registers' => ['model' => CashRegister::class, 'label' => 'الخزائن',       'can' => 'manage_settings',  'title' => fn ($m) => $m->name, 'sub' => null],
            'accounts'       => ['model' => Account::class,      'label' => 'الحسابات',      'can' => 'manage_accounting', 'title' => fn ($m) => $m->name, 'sub' => fn ($m) => $m->code ?? null],
            'organizations'  => ['model' => Organization::class, 'label' => 'الصيدليات',     'can' => null,               'title' => fn ($m) => $m->name, 'sub' => null],
        ];
    }

    /** الأنواع المسموحة للمستخدم الحالي */
    private function allowed(Request $request): array
    {
        $user = $request->user();

        return array_filter($this->types(), function ($t) use ($user) {
            return $t['can'] === null ? Tenancy::isSuperAdmin($user) : $user->can($t['can']);
        });
    }

    /** استعلام المحذوفات لنوع معيّن (فلترة الصيدلية/الفرع تلقائية عبر الـ global scope) */
    private function trashed(string $type)
    {
        $model = $this->types()[$type]['model'];
        $query = $model::onlyTrashed();

        // المستخدمون ليس عليهم نطاق تلقائي، فنقيدهم يدوياً بصيدلية/فرع المستخدم الحالي
        if ($model === User::class) {
            $query = Tenancy::constrain($query, Tenancy::active() ? 'organization_id' : null, 'branch_id');
        }

        return $query;
    }

    public function index(Request $request)
    {
        $types = $this->allowed($request);
        abort_if(!$types, 403);

        $counts = collect($types)->map(fn ($t, $key) => $this->trashed($key)->count());
        $type = $request->query('type');
        if (!isset($types[$type])) {
            $type = $counts->filter()->keys()->first() ?? array_key_first($types);
        }

        $search = trim((string) $request->query('q'));
        $items = $this->trashed($type)
            ->when($search !== '', function ($q) use ($type, $search) {
                $col = match ($type) {
                    'users'     => 'username',
                    default     => 'name',
                };
                $q->where($col, 'like', "%{$search}%");
            })
            ->orderByDesc('deleted_at')
            ->paginate(25)
            ->withQueryString();

        $def = $types[$type];
        $rows = $items->getCollection()->map(fn ($m) => [
            'id'         => $m->id,
            'title'      => ($def['title'])($m),
            'sub'        => $def['sub'] ? ($def['sub'])($m) : null,
            'deleted_at' => $m->deleted_at,
        ]);

        return view('trash.index', compact('types', 'counts', 'type', 'items', 'rows', 'search'));
    }

    public function restore(Request $request, string $type, int $id)
    {
        $types = $this->allowed($request);
        abort_unless(isset($types[$type]), 403);

        $record = $this->trashed($type)->findOrFail($id);
        $record->restore();

        if ($record instanceof User && !$record->is_active) {
            $record->forceFill(['is_active' => true])->save();
        }

        $name = ($types[$type]['title'])($record);

        return redirect()->route('trash.index', ['type' => $type])
            ->with('success', "تم استرجاع «{$name}» بنجاح.");
    }
}
