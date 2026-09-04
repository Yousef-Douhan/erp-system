<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::latest()->get();
        return view('organizations.index', compact('organizations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:organizations',
            'currency' => 'required|string|max:10',
            'timezone' => 'required|string|max:50',
            'status' => 'required|in:active,inactive',
        ]);

        Organization::create($validated);

        return redirect()->back()->with('success', 'تم إضافة المنظمة بنجاح.');
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
        $organization->delete();
        return redirect()->back()->with('success', 'تم حذف المنظمة بنجاح.');
    }
}
