<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('parent')->latest()->paginate(10);
        $parentCategories = Category::whereNull('parent_id')->get();
        return view('categories.index', compact('categories', 'parentCategories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'is_active' => 'required|in:0,1'
        ]);

        $category = Category::create([
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'is_active' => $validated['is_active'],
            'organization_id' => 1
        ]);

        return response()->json(['success' => true, 'message' => 'تم إضافة الفئة بنجاح', 'category' => $category]);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'is_active' => 'required|in:0,1'
        ]);

        $category->update([
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        return response()->json(['success' => true, 'message' => 'تم تحديث الفئة بنجاح']);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json(['success' => true, 'message' => 'تم حذف الفئة بنجاح']);
    }
}