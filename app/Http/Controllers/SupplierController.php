<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\Organization;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::with('organization')->latest()->paginate(10);
        $organizations = Organization::all();

        return view('suppliers.index', compact('suppliers', 'organizations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id,deleted_at,NULL',
            'name'            => 'required|string|max:255',
            'company_name'    => 'nullable|string|max:255',
            'balance'         => 'required|numeric|min:0',
            'credit_limit'    => 'required|numeric|min:0',
        ]);

        Supplier::create($validated);

        return redirect()->route('suppliers.index')
            ->with('success', 'تم إضافة المورد بنجاح.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id,deleted_at,NULL',
            'name'            => 'required|string|max:255',
            'company_name'    => 'nullable|string|max:255',
            'balance'         => 'required|numeric|min:0',
            'credit_limit'    => 'required|numeric|min:0',
        ]);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')
            ->with('success', 'تم تحديث بيانات المورد بنجاح.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', 'تم حذف المورد بنجاح.');
    }
}
