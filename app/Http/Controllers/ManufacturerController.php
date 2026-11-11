<?php

namespace App\Http\Controllers;

use App\Models\Manufacturer;
use Illuminate\Http\Request;

class ManufacturerController extends Controller
{
    public function index()
    {
        // إذا كنت تستخدم نظام متعدد الشركات، يفضل فلترتها هكذا:
        // $manufacturers = Manufacturer::where('organization_id', auth()->user()->organization_id)->latest()->paginate(10);

        $manufacturers = Manufacturer::latest()->paginate(10);
        return view('manufacturers.index', compact('manufacturers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_details' => 'nullable|string|max:1000',
        ]);

        $manufacturer = Manufacturer::create([
            'name' => $validated['name'],
            'contact_details' => $validated['contact_details'] ?? null,
            'organization_id' => $this->currentOrganizationId(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة المصنع بنجاح',
            'manufacturer' => $manufacturer
        ]);
    }

    public function update(Request $request, Manufacturer $manufacturer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_details' => 'nullable|string|max:1000',
        ]);

        $manufacturer->update([
            'name' => $validated['name'],
            'contact_details' => $validated['contact_details'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المصنع بنجاح'
        ]);
    }

    public function destroy(Manufacturer $manufacturer)
    {
        $manufacturer->delete();
        return response()->json([
            'success' => true,
            'message' => 'تم حذف المصنع بنجاح'
        ]);
    }
}
