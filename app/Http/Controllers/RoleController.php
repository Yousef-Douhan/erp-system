<?php

namespace App\Http\Controllers;

use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index()
    {
        PermissionCatalog::ensureExists();

        $roles = Role::with('permissions:id,name')
            ->withCount('users')
            ->orderByRaw('name = ? DESC', [PermissionCatalog::ADMIN_ROLE])
            ->orderBy('name')
            ->get();

        $permissions = Permission::where('guard_name', 'web')->orderBy('name')->get();
        $groups = PermissionCatalog::grouped($permissions);
        $totalPermissions = $permissions->count();

        return view('roles.index', compact('roles', 'groups', 'totalPermissions'));
    }

    public function create()
    {
        PermissionCatalog::ensureExists();

        return view('roles.create', [
            'role'     => null,
            'groups'   => PermissionCatalog::grouped(),
            'selected' => old('permissions', []),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRole($request);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('roles.index')->with('success', 'تم إنشاء الدور «' . $role->name . '» بنجاح.');
    }

    public function show(Role $role)
    {
        return redirect()->route('roles.edit', $role);
    }

    public function edit(Role $role)
    {
        PermissionCatalog::ensureExists();
        $role->loadCount('users');

        return view('roles.edit', [
            'role'     => $role,
            'groups'   => PermissionCatalog::grouped(),
            'selected' => old('permissions', $role->permissions->pluck('name')->all()),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $isAdmin = $role->name === PermissionCatalog::ADMIN_ROLE;
        $validated = $this->validateRole($request, $role);

        // دور المدير محمي: لا يُعاد تسميته، ويملك كل الصلاحيات دائماً
        if (!$isAdmin) {
            $role->update(['name' => $validated['name']]);
            $role->syncPermissions($validated['permissions'] ?? []);
        } else {
            $role->syncPermissions(Permission::where('guard_name', 'web')->get());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', 'تم حفظ صلاحيات الدور «' . $role->name . '».');
    }

    public function destroy(Role $role)
    {
        if ($role->name === PermissionCatalog::ADMIN_ROLE) {
            return redirect()->route('roles.index')->with('error', 'لا يمكن حذف دور مدير النظام.');
        }

        $usersCount = $role->users()->count();
        if ($usersCount > 0) {
            return redirect()->route('roles.index')
                ->with('error', "لا يمكن حذف الدور «{$role->name}» لأنه مُسنَد إلى {$usersCount} مستخدم. انقلهم إلى دور آخر أولاً.");
        }

        $name = $role->name;
        $role->delete();

        return redirect()->route('roles.index')->with('success', "تم حذف الدور «{$name}».");
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name'          => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ], [
            'name.required' => 'اكتب اسم الدور.',
            'name.unique'   => 'يوجد دور بنفس الاسم.',
        ]);
    }
}
