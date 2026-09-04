<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::latest()->paginate(10);
        return view('brands.index', compact('brands'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'required|in:0,1'
        ]);

        $brand = Brand::create([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'],
            'organization_id' => 1 // أو auth()->user()->organization_id حسب نظامك
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الماركة بنجاح',
            'brand' => $brand
        ]);
    }

    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'required|in:0,1'
        ]);

        $brand->update([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الماركة بنجاح'
        ]);
    }

    public function destroy(Brand $brand)
    {
        $brand->delete();
        return response()->json([
            'success' => true,
            'message' => 'تم حذف الماركة بنجاح'
        ]);
    }
}