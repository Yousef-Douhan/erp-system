<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::with('organization')->latest()->get();
        $organizations = Organization::where('status', 'active')->get();

        return view('branches.index', compact('branches', 'organizations'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name'            => 'required|string|max:255',
            'type'            => 'nullable|string|max:100',
            'phone'           => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:255',
            'status'          => 'required|in:active,inactive',
        ]);

        Branch::create($request->all());

        return redirect()->route('branches.index')->with('success', 'تم إضافة الفرع بنجاح.');
    }

    public function update(Request $request, Branch $branch)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name'            => 'required|string|max:255',
            'type'            => 'nullable|string|max:100',
            'phone'           => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:255',
            'status'          => 'required|in:active,inactive',
        ]);

        $branch->update($request->all());

        return redirect()->route('branches.index')->with('success', 'تم تحديث بيانات الفرع بنجاح.');
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();

        return redirect()->route('branches.index')->with('success', 'تم حذف الفرع بنجاح.');
    }
}
