<?php

namespace App\Http\Controllers;

use App\Models\EmployeeBranch;
use App\Models\Employee;
use App\Models\Branch;
use Illuminate\Http\Request;

class EmployeeBranchController extends Controller
{
    public function index()
    {
        $employeeBranches = EmployeeBranch::with(['employee', 'branch'])->latest()->get();
        $employees = Employee::all();
        $branches = Branch::all();

        return view('employee_branches.index', compact('employeeBranches', 'employees', 'branches'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'branch_id'   => 'required|exists:branches,id',
            'is_primary'  => 'nullable|boolean',
        ]);

        $validated['is_primary'] = $request->has('is_primary');

        // إذا تم تعيين هذا الفرع كفرع أساسي، نقوم بإلغاء التعيين الأساسي عن باقي فروع هذا الموظف
        if ($validated['is_primary']) {
            EmployeeBranch::where('employee_id', $validated['employee_id'])->update(['is_primary' => false]);
        }

        EmployeeBranch::create($validated);

        return redirect()->back()->with('success', 'تم ربط الموظف بالفرع بنجاح.');
    }

    public function update(Request $request, EmployeeBranch $employeeBranch)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'branch_id'   => 'required|exists:branches,id',
            'is_primary'  => 'nullable|boolean',
        ]);

        $validated['is_primary'] = $request->has('is_primary');

        if ($validated['is_primary']) {
            EmployeeBranch::where('employee_id', $validated['employee_id'])->where('id', '!=', $employeeBranch->id)->update(['is_primary' => false]);
        }

        $employeeBranch->update($validated);

        return redirect()->back()->with('success', 'تم تحديث ربط الفروع بنجاح.');
    }

    public function destroy(EmployeeBranch $employeeBranch)
    {
        $employeeBranch->delete();
        return redirect()->back()->with('success', 'تم إلغاء ربط الموظف بالفرع بنجاح.');
    }
}
