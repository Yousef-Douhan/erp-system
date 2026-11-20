<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $methods = PaymentMethod::all();
        return view('payment_methods.index', compact('methods'));
    }

    public function store(Request $request)
    {
        // طرق الدفع مشتركة بين كل الصيدليات، فتعديلها لمدير النظام فقط
        abort_unless(\App\Support\Tenancy::isSuperAdmin(), 403, 'طرق الدفع يديرها مدير النظام فقط.');

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:payment_methods,code',
            'kind' => 'required|in:cash,bank,wallet',
            'color' => 'required|string',
        ]);

        PaymentMethod::create([
            'name' => $request->name,
            'code' => $request->code,
            'kind' => $request->kind,
            'icon' => $request->icon,
            'color' => $request->color,
            'requires_wallet' => $request->has('requires_wallet'),
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->back()->with('success', 'تم إضافة طريقة الدفع بنجاح');
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        // طرق الدفع مشتركة بين كل الصيدليات، فتعديلها لمدير النظام فقط
        abort_unless(\App\Support\Tenancy::isSuperAdmin(), 403, 'طرق الدفع يديرها مدير النظام فقط.');

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:payment_methods,code,' . $paymentMethod->id,
            'kind' => 'required|in:cash,bank,wallet',
            'color' => 'required|string',
        ]);

        $paymentMethod->update([
            'name' => $request->name,
            'code' => $request->code,
            'kind' => $request->kind,
            'icon' => $request->icon,
            'color' => $request->color,
            'requires_wallet' => $request->has('requires_wallet'),
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->back()->with('success', 'تم تحديث طريقة الدفع بنجاح');
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        // طرق الدفع مشتركة بين كل الصيدليات، فتعديلها لمدير النظام فقط
        abort_unless(\App\Support\Tenancy::isSuperAdmin(), 403, 'طرق الدفع يديرها مدير النظام فقط.');

        $paymentMethod->delete();
        return redirect()->back()->with('success', 'تم حذف طريقة الدفع بنجاح');
    }
}