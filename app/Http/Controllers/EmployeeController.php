<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Organization;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = Employee::with('organization')->latest()->paginate(25);
        $organizations = Organization::all(); // لجلب المنظمات في قوائم الاختيار (Select)
        return view('employees.index', compact('employees', 'organizations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id,deleted_at,NULL',
            'first_name'      => 'required|string|max:255',
            'last_name'       => 'required|string|max:255',
            'salary'          => 'required|numeric|min:0',
            'status'          => 'required|in:active,inactive,terminated',
            'branch_id'       => 'nullable|integer',
            'user_id'         => 'nullable|integer',
        ]);

        Employee::create($validated);

        return redirect()->back()->with('success', 'تم إضافة الموظف بنجاح.');
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id,deleted_at,NULL',
            'first_name'      => 'required|string|max:255',
            'last_name'       => 'required|string|max:255',
            'salary'          => 'required|numeric|min:0',
            'status'          => 'required|in:active,inactive,terminated',
            'branch_id'       => 'nullable|integer',
            'user_id'         => 'nullable|integer',
        ]);

        $employee->update($validated);

        return redirect()->back()->with('success', 'تم تحديث بيانات الموظف بنجاح.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return redirect()->back()->with('success', 'تم حذف الموظف بنجاح.');
    }
}
