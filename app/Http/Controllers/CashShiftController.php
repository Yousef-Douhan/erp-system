<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Services\CashRegisterService;
use App\Services\ShiftService;
use Illuminate\Http\Request;

class CashShiftController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canAll = $user->can('manage_accounting');

        $myShift = CashRegisterService::openShiftFor($user);
        $mySummary = $myShift ? ShiftService::summary($myShift) : null;

        $shifts = CashShift::with(['cashRegister:id,name', 'user:id,username', 'closer:id,username'])
            ->when(!$canAll, fn ($q) => $q->where('user_id', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('opened_at')
            ->paginate(20)
            ->withQueryString();

        $busy = CashShift::where('status', 'open')->pluck('cash_register_id');
        $registers = CashRegister::where('status', 'active')->orderBy('name')->get(['id', 'name', 'current_balance'])
            ->map(fn ($r) => tap($r, fn ($r) => $r->busy = $busy->contains($r->id)));

        return view('shifts.index', compact('myShift', 'mySummary', 'shifts', 'registers', 'canAll'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cash_register_id' => 'required|exists:cash_registers,id,deleted_at,NULL',
            'notes'            => 'nullable|string|max:500',
        ]);

        ShiftService::open(CashRegister::findOrFail($validated['cash_register_id']), $request->user(), $validated['notes'] ?? null);

        return redirect()->route('shifts.index')->with('success', 'تم فتح الوردية. كل المبيعات النقدية ستُسجل على هذه الخزينة.');
    }

    public function show(Request $request, CashShift $shift)
    {
        $this->ensureCanSee($request, $shift);

        $shift->load(['cashRegister', 'user', 'closer']);
        $summary = ShiftService::summary($shift);

        return view('shifts.show', compact('shift', 'summary'));
    }

    public function close(Request $request, CashShift $shift)
    {
        $this->ensureCanSee($request, $shift);

        $validated = $request->validate([
            'actual_balance' => 'required|numeric|min:0',
            'notes'          => 'nullable|string|max:500',
        ]);

        $shift = ShiftService::close($shift, (float) $validated['actual_balance'], $request->user(), $validated['notes'] ?? null);

        $diff = (float) $shift->difference;
        $msg = $diff == 0
            ? 'تم إغلاق الوردية. الصندوق مطابق تماماً.'
            : ($diff > 0
                ? 'تم إغلاق الوردية مع زيادة ' . number_format($diff, 2) . ' ₪ سُجلت كإيراد آخر.'
                : 'تم إغلاق الوردية مع عجز ' . number_format(abs($diff), 2) . ' ₪ سُجل كمصروف آخر.');

        return redirect()->route('shifts.show', $shift)->with('success', $msg);
    }

    /**
     * الكاشير يرى ورديته فقط، والمحاسب يرى كل الورديات
     */
    private function ensureCanSee(Request $request, CashShift $shift): void
    {
        abort_unless($shift->user_id === $request->user()->id || $request->user()->can('manage_accounting'), 403);
    }
}
