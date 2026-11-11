<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::with('organization')->latest()->paginate(25);
        $organizations = Organization::where('status', 'active')->get();

        return view('branches.index', compact('branches', 'organizations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id,deleted_at,NULL',
            'name'            => 'required|string|max:255',
            'type'            => 'nullable|string|max:100',
            'phone'           => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:255',
            'status'          => 'required|in:active,inactive',
        ]);

        Branch::create($validated);

        return redirect()->route('branches.index')->with('success', 'تم إضافة الفرع بنجاح.');
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id,deleted_at,NULL',
            'name'            => 'required|string|max:255',
            'type'            => 'nullable|string|max:100',
            'phone'           => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:255',
            'status'          => 'required|in:active,inactive',
        ]);

        $branch->update($validated);

        return redirect()->route('branches.index')->with('success', 'تم تحديث بيانات الفرع بنجاح.');
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();

        return redirect()->route('branches.index')->with('success', 'تم حذف الفرع بنجاح.');
    }
}
