<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['organization', 'roles'])->latest()->get();
        $organizations = Organization::all();
        $roles = Role::all();

        return view('users.index', compact('users', 'organizations', 'roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:6',
            'organization_id' => 'nullable|exists:organizations,id',
            'role' => 'required|exists:roles,name',
            'is_active' => 'boolean',
        ]);

        $validated['password'] = Hash::make($request->password);
        $validated['is_active'] = $request->has('is_active');

        $user = User::create($validated);
        $user->assignRole($request->role);

        return redirect()->route('users.index')->with('success', 'تم إضافة المستخدم بنجاح.');
    }

    public function edit(User $user)
    {
        $organizations = Organization::all();
        $roles = Role::all();
        return view('users.edit', compact('user', 'organizations', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
            'organization_id' => 'nullable|exists:organizations,id',
            'role' => 'required|exists:roles,name',
        ]);

        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->password);
        } else {
            unset($validated['password']);
        }

        $validated['is_active'] = $request->has('is_active');

        $user->update($validated);
        $user->syncRoles([$request->role]);

        return redirect()->route('users.index')->with('success', 'تم تحديث بيانات المستخدم بنجاح.');
    }

    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return redirect()->route('users.index')->with('error', 'لا يمكنك حذف حسابك الحالي!');
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'تم حذف المستخدم بنجاح.');
    }
}
