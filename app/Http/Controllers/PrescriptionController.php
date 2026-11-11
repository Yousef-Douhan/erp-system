<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PrescriptionController extends Controller
{
    public function index()
    {
        $prescriptions = Prescription::with('customer')->latest()->paginate(15);
        $customers = Customer::visibleTo(auth()->user())->orderBy('name')->get(['id', 'name', 'phone']);
        return view('prescriptions.index', compact('prescriptions', 'customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id,deleted_at,NULL',
            'doctor_name' => 'required|string|max:255',
            'diagnosis' => 'nullable|string',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'is_filled' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('prescriptions', 'public');
        }

        $validated['is_filled'] = $request->has('is_filled') ? 1 : 0;

        Prescription::create($validated);

        return redirect()->route('prescriptions.index')->with('success', 'تم حفظ الروشتة بنجاح.');
    }

    public function update(Request $request, Prescription $prescription)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id,deleted_at,NULL',
            'doctor_name' => 'required|string|max:255',
            'diagnosis' => 'nullable|string',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'is_filled' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            // حذف الصورة القديمة إن وجدت
            if ($prescription->image_path) {
                Storage::disk('public')->delete($prescription->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('prescriptions', 'public');
        }

        $validated['is_filled'] = $request->has('is_filled') ? 1 : 0;

        $prescription->update($validated);

        return redirect()->route('prescriptions.index')->with('success', 'تم تحديث الروشتة بنجاح.');
    }

    public function destroy(Prescription $prescription)
    {
        if ($prescription->image_path) {
            Storage::disk('public')->delete($prescription->image_path);
        }
        $prescription->delete();

        return redirect()->route('prescriptions.index')->with('success', 'تم حذف الروشتة بنجاح.');
    }
}
