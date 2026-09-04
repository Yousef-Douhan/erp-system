<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::latest()->paginate(10);
        return view('units.index', compact('units'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ]);

        Unit::create([
            'name' => $validated['name'],
            'short_name' => $validated['short_name'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $request->has('is_active'),
            'organization_id' => 1, // أو auth()->user()->organization_id حسب نظامك
        ]);

        return redirect()->route('units.index')->with('success', 'تم إضافة الوحدة بنجاح.');
    }

    public function update(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ]);

        $unit->update([
            'name' => $validated['name'],
            'short_name' => $validated['short_name'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('units.index')->with('success', 'تم تحديث الوحدة بنجاح.');
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();
        return redirect()->route('units.index')->with('success', 'تم حذف الوحدة بنجاح.');
    }
}
