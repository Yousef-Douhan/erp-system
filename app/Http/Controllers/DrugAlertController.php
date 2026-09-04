<?php

namespace App\Http\Controllers;

use App\Models\DrugAlert;
use App\Models\Product; // أو Model الأدوية لديك
use Illuminate\Http\Request;

class DrugAlertController extends Controller
{
    public function index()
    {
        $alerts = DrugAlert::with('product')->latest()->paginate(15);
        $products = Product::all(); // جلب قائمة الأدوية/المنتجات للقائمة المنسدلة
        return view('drug_alerts.index', compact('alerts', 'products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'severity'   => 'required|string|in:low,medium,high,critical',
            'message'    => 'required|string',
        ]);

        DrugAlert::create($validated);

        return redirect()->route('drug-alerts.index')->with('success', 'تم إضافة التنبيه الدوائي بنجاح.');
    }

    public function update(Request $request, DrugAlert $drugAlert)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'severity'   => 'required|string|in:low,medium,high,critical',
            'message'    => 'required|string',
        ]);

        $drugAlert->update($validated);

        return redirect()->route('drug-alerts.index')->with('success', 'تم تحديث التنبيه بنجاح.');
    }

    public function destroy(DrugAlert $drugAlert)
    {
        $drugAlert->delete();

        return redirect()->route('drug-alerts.index')->with('success', 'تم حذف التنبيه بنجاح.');
    }
}