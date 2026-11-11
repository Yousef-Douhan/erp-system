<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Services\ProductImportService;
use App\Support\Spreadsheet;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * استيراد الأدوية من ملف Excel: رفع ← معاينة وفحص ← تأكيد
 */
class ProductImportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('create', Product::class);

        $branches = Branch::orderBy('name')->get(['id', 'name', 'organization_id']);
        $preview = null;

        if ($token = $request->query('token')) {
            $preview = $this->load($token);
            if (!$preview) {
                return redirect()->route('products.import')->with('error', 'انتهت صلاحية المعاينة. ارفع الملف من جديد.');
            }
        }

        return view('products.import', compact('branches', 'preview', 'token'));
    }

    /**
     * قالب Excel جاهز بالأعمدة وأمثلة
     */
    public function template()
    {
        $path = storage_path('app/import-template-' . Str::random(8) . '.xlsx');
        Spreadsheet::writeXlsx(
            $path,
            array_values(ProductImportService::COLUMNS),
            ProductImportService::templateRows(),
            [28, 18, 16, 14, 14, 14, 12, 10, 12, 14, 12, 16, 14, 14],
            'الأدوية',
            [1, 8, 9] // الباركود ورقم الدفعة والتاريخ كنص
        );

        return response()->download($path, 'قالب-استيراد-الأدوية.xlsx')->deleteFileAfterSend();
    }

    /**
     * قراءة الملف وفحص كل صف (بدون حفظ أي شيء)
     */
    public function preview(Request $request)
    {
        $this->authorize('create', Product::class);

        $request->validate([
            'file'      => 'required|file|max:5120',
            'branch_id' => 'nullable|integer',
        ], [
            'file.required' => 'اختر ملف Excel أولاً.',
            'file.max'      => 'حجم الملف أكبر من 5 ميغابايت.',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            return back()->with('error', 'الصيغة غير مدعومة. احفظ الملف بصيغة Excel (.xlsx) أو CSV. الصيغة القديمة .xls غير مدعومة: افتحه في Excel واحفظه باسم .xlsx.');
        }

        $branch = Branch::findOrFail(Tenancy::branchId() ?? $request->input('branch_id') ?? Tenancy::defaultBranchId());

        try {
            $sheet = Spreadsheet::read($file->getRealPath(), $ext);
            $result = ProductImportService::parse($sheet, (int) $branch->organization_id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $token = Str::random(32);
        File::ensureDirectoryExists($this->dir());
        File::put($this->path($token), json_encode([
            'user_id'   => $request->user()->id,
            'branch_id' => $branch->id,
            'org_id'    => (int) $branch->organization_id,
            'branch'    => $branch->name,
            'file'      => $file->getClientOriginalName(),
            'created'   => now()->toDateTimeString(),
            'result'    => $result,
        ], JSON_UNESCAPED_UNICODE));

        return redirect()->route('products.import', ['token' => $token]);
    }

    /**
     * تنفيذ الاستيراد للصفوف السليمة
     */
    public function store(Request $request)
    {
        $this->authorize('create', Product::class);

        $data = $this->load((string) $request->input('token'));
        if (!$data) {
            return redirect()->route('products.import')->with('error', 'انتهت صلاحية المعاينة. ارفع الملف من جديد.');
        }

        // الفرع ما زال ضمن صلاحيات المستخدم
        Branch::findOrFail($data['branch_id']);

        @set_time_limit(300);
        $done = ProductImportService::import($data['result']['rows'], (int) $data['branch_id'], (int) $data['org_id'], $request->boolean('update_prices'));
        File::delete($this->path($request->input('token')));

        $msg = "تم الاستيراد: {$done['created']} صنف جديد، و{$done['updated']} صنف موجود"
            . ($done['stock'] ? "، وأُضيف {$done['stock']} وحدة لمخزون فرع {$data['branch']}" : '')
            . ($done['skipped'] ? "، وتُرك {$done['skipped']} صف فيه أخطاء" : '') . '.';

        return redirect()->route('products.index')->with('success', $msg);
    }

    private function dir(): string
    {
        return storage_path('app/imports');
    }

    private function path(string $token): string
    {
        return $this->dir() . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9]/', '', $token) . '.json';
    }

    /**
     * المعاينة صالحة لساعتين ولنفس المستخدم فقط
     */
    private function load(string $token): ?array
    {
        $path = $this->path($token);
        if ($token === '' || !File::exists($path) || File::lastModified($path) < now()->subHours(2)->timestamp) {
            return null;
        }

        $data = json_decode(File::get($path), true);

        return ($data['user_id'] ?? null) === auth()->id() ? $data : null;
    }
}
