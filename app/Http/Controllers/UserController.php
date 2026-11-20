<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Models\Organization;
use App\Support\PermissionCatalog;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', User::class);

        // صاحب الصيدلية يرى مستخدمي صيدليته، ومدير الفرع يرى موظفي فرعه، ومدير النظام يرى الكل
        $users = $this->scopedUsers()->with(['organization', 'branch', 'roles'])->latest()->paginate(25);
        $organizations = Organization::orderBy('name')->get();
        $branches = Branch::with('organization:id,name')->orderBy('name')->get();
        // الأدوار القابلة للمنح + أدوار المستخدمين الظاهرين (حتى يُعرض الدور الحالي عند التعديل)
        $roles = $this->assignableRoles()
            ->merge(Role::whereIn('name', $users->getCollection()->flatMap->getRoleNames()->unique())->get())
            ->unique('name')->values();

        return view('users.index', compact('users', 'organizations', 'branches', 'roles'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'full_name' => 'nullable|string|max:255',
            'salary' => 'nullable|numeric|min:0|max:999999',
            'password' => ['required', 'confirmed', $this->passwordRule()],
            'organization_id' => 'nullable|exists:organizations,id,deleted_at,NULL',
            'branch_id' => 'nullable|exists:branches,id,deleted_at,NULL',
            'role' => 'required|exists:roles,name',
            'is_active' => 'boolean',
        ]);

        $this->ensureCanAssignRole($request->role);
        $validated['salary'] = round((float) ($validated['salary'] ?? 0), 2);
        $validated = $this->applyScope($validated, $request->role);

        $validated['password'] = Hash::make($request->password);
        $validated['is_active'] = $request->has('is_active');

        $user = User::create($validated);
        $user->assignRole($request->role);

        return redirect()->route('users.index')->with('success', 'تم إضافة المستخدم بنجاح.');
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'full_name' => 'nullable|string|max:255',
            'salary' => 'nullable|numeric|min:0|max:999999',
            'password' => ['nullable', 'confirmed', $this->passwordRule()],
            'organization_id' => 'nullable|exists:organizations,id,deleted_at,NULL',
            'branch_id' => 'nullable|exists:branches,id,deleted_at,NULL',
            'role' => 'required|exists:roles,name',
        ]);

        // إبقاء الدور الحالي مسموح دائماً، وتغييره يخضع لقيود المنح
        if (!$user->hasRole($request->role)) {
            $this->ensureCanAssignRole($request->role);
        }
        $validated['salary'] = round((float) ($validated['salary'] ?? 0), 2);
        $validated = $this->applyScope($validated, $request->role);

        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->password);
        } else {
            unset($validated['password']);
        }

        $validated['is_active'] = $request->has('is_active');

        // حماية من إغلاق النظام على نفسه
        if ($user->id === auth()->id()) {
            if (!$validated['is_active']) {
                return back()->withErrors('لا يمكنك إيقاف حسابك الحالي.')->withInput();
            }
            if (!$user->hasRole($request->role)) {
                return back()->withErrors('لا يمكنك تغيير دورك بنفسك. اطلب ذلك من مدير نظام آخر.')->withInput();
            }
        }
        if ($user->hasRole('Admin') && ($request->role !== 'Admin' || !$validated['is_active']) && $this->activeAdminsCount() <= 1) {
            return back()->withErrors('هذا آخر مدير نظام نشط. أضف مديراً آخر قبل تغيير دوره أو إيقافه.')->withInput();
        }

        $user->update($validated);
        $user->syncRoles([$request->role]);

        return redirect()->route('users.index')->with('success', 'تم تحديث بيانات المستخدم بنجاح.');
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        if (auth()->id() === $user->id) {
            return redirect()->route('users.index')->with('error', 'لا يمكنك حذف حسابك الحالي!');
        }

        if ($user->hasRole('Admin') && $this->activeAdminsCount() <= 1) {
            return redirect()->route('users.index')->with('error', 'لا يمكن حذف آخر مدير نظام نشط.');
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'تم حذف المستخدم بنجاح.');
    }

    /**
     * منع تصعيد الصلاحيات: كل مستخدم يمنح فقط الأدوار المسموحة له
     */
    private function ensureCanAssignRole(?string $role): void
    {
        if (!$this->assignableRoles()->contains('name', $role)) {
            abort(403, 'لا يمكنك منح هذا الدور.');
        }
    }

    /**
     * الأدوار التي يستطيع المستخدم الحالي منحها:
     * - مدير النظام: كل الأدوار
     * - صاحب الصيدلية: كل الأدوار ما عدا مدير النظام وصاحب صيدلية
     * - مستخدم فرع (مدير فرع): الأدوار التي لا تملك إدارة المستخدمين (كاشير، صيدلي...)
     */
    private function assignableRoles()
    {
        $roles = Role::with('permissions:id,name')->orderBy('name')->get();

        if (Tenancy::isSuperAdmin()) {
            return $roles;
        }

        $roles = $roles->reject(fn ($r) => in_array($r->name, [PermissionCatalog::ADMIN_ROLE, PermissionCatalog::OWNER_ROLE], true));

        if (Tenancy::branchId()) {
            $roles = $roles->reject(fn ($r) => $r->permissions->contains('name', 'manage_users'));
        }

        return $roles->values();
    }

    /**
     * الصيدلية والفرع للمستخدم الجديد/المعدَّل حسب صلاحية من يضيفه
     */
    private function applyScope(array $data, string $role): array
    {
        if (Tenancy::isSuperAdmin()) {
            // مدير النظام: الفرع يجب أن يتبع الصيدلية المختارة
            if (!empty($data['branch_id'])) {
                $branchOrg = Branch::whereKey($data['branch_id'])->value('organization_id');
                if (!empty($data['organization_id']) && (int) $branchOrg !== (int) $data['organization_id']) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['branch_id' => 'الفرع المختار لا يتبع الصيدلية المختارة.']);
                }
                $data['organization_id'] = $branchOrg;
            }
            if ($role !== PermissionCatalog::ADMIN_ROLE && empty($data['organization_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['organization_id' => 'اختر الصيدلية التي يتبعها المستخدم.']);
            }
        } else {
            $data['organization_id'] = Tenancy::orgId();
            if (Tenancy::branchId()) {
                $data['branch_id'] = Tenancy::branchId();
            } elseif (!empty($data['branch_id']) && !in_array((int) $data['branch_id'], Tenancy::branchIds(), true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['branch_id' => 'الفرع المختار لا يتبع صيدليتك.']);
            }
        }

        // صاحب الصيدلية يعمل على مستوى الصيدلية كلها (بدون فرع)
        if ($role === PermissionCatalog::OWNER_ROLE || $role === PermissionCatalog::ADMIN_ROLE) {
            $data['branch_id'] = null;
        }

        return $data;
    }

    /**
     * المستخدمون الظاهرون للمستخدم الحالي
     */
    private function scopedUsers()
    {
        $query = User::query();

        if (!Tenancy::isSuperAdmin()) {
            $query->where('organization_id', Tenancy::orgId() ?? 0)
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', PermissionCatalog::ADMIN_ROLE));
            if (Tenancy::branchId()) {
                $query->where('branch_id', Tenancy::branchId());
            }
        }

        return $query;
    }

    /**
     * كلمة مرور قوية: 8 أحرف على الأقل وتحتوي حروفاً وأرقاماً
     */
    private function passwordRule(): Password
    {
        return Password::min(8)->letters()->numbers();
    }

    private function activeAdminsCount(): int
    {
        return User::role('Admin')->where('is_active', true)->count();
    }
}
