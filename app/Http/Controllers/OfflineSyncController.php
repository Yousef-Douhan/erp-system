<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * البيع بدون إنترنت:
 * - ping: فحص الاتصال + رمز CSRF جديد (الجلسة قد تتجدد أثناء الانقطاع)
 * - sync: استقبال الفواتير المحفوظة على جهاز البيع. كل فاتورة لها رمز (uuid) من الجهاز،
 *   فإعادة رفعها لا تنشئ فاتورة ثانية، ويرجع لها نفس الرقم الرسمي.
 * - review: الفواتير التي رُفعت وفيها ما يحتاج نظر المدير.
 */
class OfflineSyncController extends Controller
{
    public const MAX_BATCH = 25;

    public function ping()
    {
        return response()->json([
            'ok'      => true,
            'token'   => csrf_token(),
            'user_id' => auth()->id(),
            'time'    => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    public function sync(Request $request, PosController $pos)
    {
        $this->authorize('create', Sale::class);

        $sales = $request->input('sales');
        if (!is_array($sales) || !$sales) {
            return response()->json(['message' => 'لا توجد فواتير للرفع.'], 422);
        }
        if (count($sales) > self::MAX_BATCH) {
            return response()->json(['message' => 'عدد الفواتير في الدفعة الواحدة كبير.'], 422);
        }

        $results = [];
        foreach (array_values($sales) as $raw) {
            $results[] = $this->syncOne(is_array($raw) ? $raw : [], $pos);
        }

        return response()->json([
            'results'     => $results,
            'server_time' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    private function syncOne(array $raw, PosController $pos): array
    {
        $uuid = is_string($raw['uuid'] ?? null) ? $raw['uuid'] : null;

        $v = Validator::make($raw, [
            'uuid'                    => 'required|string|min:8|max:64',
            'number'                  => 'nullable|string|max:40',
            'user_id'                 => 'required|integer',
            'at'                      => 'required|date',
            'customer_id'             => 'nullable|integer',
            'prescription_id'         => 'nullable|integer',
            'items'                   => 'required|array|min:1|max:200',
            'items.*.product_id'      => 'required|integer',
            'items.*.product_unit_id' => 'nullable|integer',
            'items.*.quantity'        => 'required|integer|min:1',
            'prices'                  => 'nullable|array',
            'prices.*'                => 'nullable|numeric|min:0',
            'payment_method'          => 'required|string|max:50',
            'paid_amount'             => 'required|numeric|min:0',
            'discount'                => 'nullable|numeric|min:0',
            'wallet_phone'            => 'nullable|string|max:30',
            'payer_name'              => 'nullable|string|max:120',
            'payer_account'           => 'nullable|string|max:60',
            'transfer_ref'            => 'nullable|string|max:60',
        ]);
        if ($v->fails()) {
            return ['uuid' => $uuid, 'status' => 'rejected', 'message' => 'بيانات الفاتورة ناقصة: ' . $v->errors()->first()];
        }

        // كل كاشير يرفع فواتيره فقط (الفاتورة باسم من باعها)
        if ((int) $raw['user_id'] !== (int) auth()->id()) {
            return ['uuid' => $uuid, 'status' => 'other_user', 'message' => 'هذه الفاتورة لمستخدم آخر، تُرفع عند دخوله.'];
        }

        if ($done = $this->existing($uuid)) {
            return $done;
        }

        // وقت البيع: لا يُقبل وقت في المستقبل (ساعة الجهاز غلط)
        $at = Carbon::parse($raw['at'])->setTimezone(config('app.timezone'));
        $notes = [];
        if ($at->gt(now()->addMinutes(5))) {
            $at = now();
            $notes[] = 'ساعة جهاز البيع كانت متقدمة؛ سُجّل وقت الرفع بدل وقت الجهاز.';
        } elseif ($at->lt(now()->subDays(30))) {
            $notes[] = 'الفاتورة أقدم من 30 يوماً عند رفعها.';
        }

        try {
            $sale = $pos->createSale($raw, [
                'uuid'   => $uuid,
                'number' => $raw['number'] ?? null,
                'at'     => $at,
                'prices' => $raw['prices'] ?? [],
            ]);
        } catch (ValidationException $e) {
            return ['uuid' => $uuid, 'status' => 'rejected', 'message' => collect($e->errors())->flatten()->first() ?: 'تعذر حفظ الفاتورة.'];
        } catch (QueryException $e) {
            // نفس الفاتورة وصلت من طلب آخر في نفس اللحظة
            if ($done = $this->existing($uuid)) {
                return $done;
            }
            report($e);
            return ['uuid' => $uuid, 'status' => 'retry', 'message' => 'خطأ مؤقت في السيرفر، ستُعاد المحاولة.'];
        } catch (\Throwable $e) {
            report($e);
            return ['uuid' => $uuid, 'status' => 'retry', 'message' => 'خطأ مؤقت في السيرفر، ستُعاد المحاولة.'];
        }

        if ($notes) {
            $sale->forceFill([
                'needs_review' => true,
                'review_note'  => trim(implode("\n", $notes) . "\n" . $sale->review_note),
            ])->save();
        }

        return $this->result($sale, 'ok');
    }

    private function existing(?string $uuid): ?array
    {
        if (!$uuid) {
            return null;
        }
        $sale = Tenancy::bypass(fn () => Sale::withTrashed()->where('client_uuid', $uuid)->first());
        if (!$sale) {
            return null;
        }
        if ((int) $sale->employee_id !== (int) auth()->id()) {
            return ['uuid' => $uuid, 'status' => 'rejected', 'message' => 'رمز الفاتورة مستخدم لفاتورة أخرى.'];
        }

        return $this->result($sale, 'duplicate');
    }

    private function result(Sale $sale, string $status): array
    {
        return [
            'uuid'           => $sale->client_uuid,
            'status'         => $status,
            'sale_id'        => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'needs_review'   => (bool) $sale->needs_review,
            'review_note'    => $sale->review_note,
        ];
    }

    // ================= مراجعة فواتير البيع بدون إنترنت =================

    public function review(Request $request)
    {
        $tab = $request->query('tab') === 'all' ? 'all' : 'review';

        $sales = Sale::with(['employee:id,username', 'branch:id,name', 'reviewer:id,username'])
            ->whereNotNull('offline_at')
            ->when($tab === 'review', fn ($q) => $q->where('needs_review', true)->whereNull('reviewed_at'))
            ->latest('offline_at')
            ->paginate(30)
            ->withQueryString();

        $pendingCount = Sale::whereNotNull('offline_at')->where('needs_review', true)->whereNull('reviewed_at')->count();

        return view('offline_sales.index', compact('sales', 'tab', 'pendingCount'));
    }

    public function markReviewed(Request $request, Sale $sale)
    {
        abort_unless($sale->needs_review, 404);

        $request->validate(['note' => 'nullable|string|max:500']);

        $sale->forceFill([
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => trim($sale->review_note . ($request->filled('note') ? "\nالمراجعة: " . $request->note : '')),
        ])->save();

        return back()->with('success', "تمت مراجعة الفاتورة {$sale->invoice_number}.");
    }
}
