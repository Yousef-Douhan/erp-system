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
        // لا يُحذف فرع فيه بضاعة أو مال أو وردية مفتوحة أو موظفون نشطون
        $stock = (int) \App\Models\BranchStock::where('branch_id', $branch->id)->where('quantity', '>', 0)->sum('quantity');
        if ($stock > 0) {
            return back()->with('error', "لا يمكن حذف فرع فيه مخزون ({$stock} قطعة). انقلها لفرع آخر أولاً.");
        }

        $cash = (float) \App\Models\CashRegister::where('branch_id', $branch->id)->sum('current_balance');
        if (abs($cash) >= 0.01) {
            return back()->with('error', 'لا يمكن حذف فرع في خزائنه ' . number_format($cash, 2) . ' ₪. حوّل المبلغ لخزينة فرع آخر أولاً.');
        }

        $registerIds = \App\Models\CashRegister::where('branch_id', $branch->id)->pluck('id');
        if ($registerIds->isNotEmpty() && \App\Models\CashShift::whereIn('cash_register_id', $registerIds)->where('status', 'open')->exists()) {
            return back()->with('error', 'لا يمكن حذف فرع عليه وردية مفتوحة. أغلق الوردية أولاً.');
        }

        $staff = \App\Models\User::where('branch_id', $branch->id)->where('is_active', true)->count();
        if ($staff > 0) {
            return back()->with('error', "لا يمكن حذف فرع عليه {$staff} موظف نشط. انقلهم لفرع آخر أو أوقفهم أولاً.");
        }

        $branch->delete();

        return redirect()->route('branches.index')->with('success', 'تم حذف الفرع بنجاح.');
    }
}
