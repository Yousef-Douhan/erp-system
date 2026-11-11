<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::latest()->paginate(25);
        return view('organizations.index', compact('organizations'));
    }

    public function store(Request $request)
    {
        // إضافة صيدلية جديدة لمدير النظام فقط
        abort_unless(\App\Support\Tenancy::isSuperAdmin(), 403, 'إضافة صيدلية جديدة لمدير النظام فقط.');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:organizations',
            'currency' => 'required|string|max:10',
            'timezone' => 'required|string|max:50',
            'status' => 'required|in:active,inactive',
        ]);

        $organization = Organization::create($validated);

        // كل صيدلية تبدأ بفرع رئيسي (ومستودعه يُنشأ تلقائياً)
        \App\Models\Branch::create([
            'organization_id' => $organization->id,
            'name'            => 'الفرع الرئيسي',
            'status'          => 'active',
        ]);

        return redirect()->back()->with('success', 'تم إضافة الصيدلية «' . $organization->name . '» مع فرعها الرئيسي. أضف حساب صاحبها من شاشة المستخدمين بدور «صاحب صيدلية».');
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:organizations,name,' . $organization->id,
            'currency' => 'required|string|max:10',
            'timezone' => 'required|string|max:50',
            'status' => 'required|in:active,inactive',
        ]);

        $organization->update($validated);

        return redirect()->back()->with('success', 'تم تحديث المنظمة بنجاح.');
    }

    public function destroy(Organization $organization)
    {
        abort_unless(\App\Support\Tenancy::isSuperAdmin(), 403, 'حذف صيدلية لمدير النظام فقط.');

        $organization->delete();
        return redirect()->back()->with('success', 'تم حذف المنظمة بنجاح.');
    }
}
