<?php

namespace App\Services;

use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Support\Stock;
use Illuminate\Support\Facades\DB;

/**
 * استيراد الأدوية من Excel:
 * 1) parse(): يقرأ الصفوف ويتحقق منها ويحدد لكل صف: صنف جديد / تحديث صنف موجود / خطأ
 * 2) import(): ينفذ الصفوف السليمة فقط (الأصناف، الوحدة الكبيرة، الرصيد الافتتاحي في مستودع الفرع مع الدفعة)
 */
class ProductImportService
{
    public const MAX_ROWS = 3000;

    /** عناوين القالب بالترتيب، والمفتاح الداخلي لكل عمود */
    public const COLUMNS = [
        'name'        => 'اسم الصنف *',
        'barcode'     => 'الباركود',
        'category'    => 'التصنيف',
        'unit'        => 'الوحدة الأساسية',
        'purchase'    => 'سعر الشراء للوحدة *',
        'price'       => 'سعر البيع للوحدة *',
        'qty'         => 'الكمية الافتتاحية',
        'alert'       => 'حد التنبيه',
        'batch'       => 'رقم الدفعة',
        'expiry'      => 'تاريخ الانتهاء',
        'controlled'  => 'خاضع للرقابة (نعم/لا)',
        'big_unit'    => 'الوحدة الكبيرة (علبة/شريط)',
        'big_factor'  => 'عدد الوحدات في الكبيرة',
        'big_price'   => 'سعر بيع الوحدة الكبيرة',
    ];

    public static function templateRows(): array
    {
        return [
            ['باراسيتامول 500 ملغ', '6291000000012', 'مسكنات', 'حبة', 0.35, 0.5, 200, 40, 'B-2401', '2027-06-30', 'لا', 'علبة', 20, 9],
            ['أموكسيسيلين 250 شراب', '', 'مضادات حيوية', 'زجاجة', 8.4, 12, 15, 5, 'AMX-77', '03/2027', 'لا', '', '', ''],
            ['ترامادول 50 ملغ', '6291000000036', 'مسكنات', 'حبة', 1.1, 1.6, 60, 10, '', '2026-12-31', 'نعم', 'شريط', 10, 15],
        ];
    }

    /**
     * يقرأ الصفوف ويرجع: [rows => [...], summary => [...], unknown_headers => [...]]
     */
    public static function parse(array $sheet, int $orgId): array
    {
        $sheet = array_values(array_filter($sheet, fn ($r) => is_array($r)));
        if (count($sheet) < 2) {
            throw new \RuntimeException('الملف فارغ أو لا يحتوي إلا العناوين.');
        }

        [$map, $unknown] = self::mapHeaders($sheet[0]);
        foreach (['name', 'purchase', 'price'] as $required) {
            if (!array_key_exists($required, $map)) {
                throw new \RuntimeException('عمود «' . self::COLUMNS[$required] . '» غير موجود في الملف. استخدم القالب الجاهز أو أضف العمود بنفس الاسم.');
            }
        }

        $body = array_slice($sheet, 1);
        if (count($body) > self::MAX_ROWS) {
            throw new \RuntimeException('الملف فيه أكثر من ' . self::MAX_ROWS . ' صف. قسّمه لأكثر من ملف.');
        }

        // الأصناف الموجودة في الصيدلية (للمطابقة بالباركود أو الاسم)
        $existing = Product::withoutGlobalScopes()->where('organization_id', $orgId)->get(['id', 'name', 'barcode']);
        $byBarcode = $existing->filter(fn ($p) => $p->barcode)->keyBy(fn ($p) => (string) $p->barcode);
        $byName = $existing->keyBy(fn ($p) => self::normName($p->name));

        $rows = [];
        $seenBarcodes = [];
        $seenNames = [];

        foreach ($body as $i => $raw) {
            $line = $i + 2;
            $get = fn ($key) => isset($map[$key]) ? trim((string) ($raw[$map[$key]] ?? '')) : '';

            if (implode('', array_map('trim', array_map('strval', $raw))) === '') {
                continue;
            }

            $errors = [];
            $warnings = [];

            $name = preg_replace('/\s+/u', ' ', $get('name'));
            $barcode = self::digitsToLatin(preg_replace('/\s+/', '', $get('barcode')));
            if (preg_match('/^\d+(\.\d+)?E\+?\d+$/i', $barcode)) {
                $errors[] = 'الباركود مكتوب بصيغة علمية (مثل 6.29E+12). غيّر تنسيق العمود في Excel إلى «نص» وأعد كتابته.';
            }
            $barcode = preg_replace('/\.0+$/', '', $barcode);

            $purchase = self::number($get('purchase'));
            $price = self::number($get('price'));
            $qty = $get('qty') === '' ? 0 : self::number($get('qty'));
            $alert = $get('alert') === '' ? 5 : self::number($get('alert'));
            $expiryRaw = $get('expiry');
            $expiry = \App\Support\Spreadsheet::parseDate($expiryRaw);
            $bigUnit = $get('big_unit');
            $bigFactor = $get('big_factor') === '' ? null : self::number($get('big_factor'));
            $bigPrice = $get('big_price') === '' ? null : self::number($get('big_price'));

            if ($name === '') {
                $errors[] = 'اسم الصنف فارغ.';
            }
            if ($price === null || $price <= 0) {
                $errors[] = 'سعر البيع غير صحيح.';
            }
            if ($purchase === null || $purchase < 0) {
                $errors[] = 'سعر الشراء غير صحيح.';
            }
            if ($price !== null && $purchase !== null && $purchase > $price && $price > 0) {
                $errors[] = 'سعر الشراء (' . $purchase . ') أعلى من سعر البيع (' . $price . '). الأسعار للوحدة الأساسية (الحبة)، وليس للعلبة.';
            }
            if ($qty === null || $qty < 0 || floor($qty) != $qty) {
                $errors[] = 'الكمية يجب أن تكون عدداً صحيحاً.';
            }
            if ($alert === null || $alert < 0) {
                $errors[] = 'حد التنبيه غير صحيح.';
            }
            if ($expiryRaw !== '' && !$expiry) {
                $errors[] = 'تاريخ الانتهاء غير مفهوم: «' . $expiryRaw . '». اكتبه مثل 2027-03-31 أو 03/2027.';
            } elseif ($expiry && $expiry < date('Y-m-d')) {
                $warnings[] = 'تاريخ الانتهاء منتهٍ (' . $expiry . ').';
            }
            if ($bigUnit !== '' && ($bigFactor === null || $bigFactor < 2 || floor($bigFactor) != $bigFactor)) {
                $errors[] = 'حدد «عدد الوحدات في الكبيرة» (عدد صحيح 2 أو أكثر) للوحدة «' . $bigUnit . '».';
            }
            if ($bigUnit === '' && ($bigFactor || $bigPrice)) {
                $warnings[] = 'كُتب عدد/سعر للوحدة الكبيرة بدون اسمها، فسيُتجاهل.';
            }

            // التكرار داخل الملف نفسه
            $nameKey = self::normName($name);
            if ($barcode !== '' && isset($seenBarcodes[$barcode])) {
                $errors[] = 'الباركود مكرر في الملف (الصف ' . $seenBarcodes[$barcode] . ').';
            }
            if ($nameKey !== '' && isset($seenNames[$nameKey])) {
                $errors[] = 'الاسم مكرر في الملف (الصف ' . $seenNames[$nameKey] . ').';
            }
            $seenBarcodes[$barcode] ??= $barcode !== '' ? $line : null;
            $seenNames[$nameKey] ??= $nameKey !== '' ? $line : null;

            // صنف موجود؟ بالباركود أولاً ثم الاسم
            $match = ($barcode !== '' ? $byBarcode->get($barcode) : null) ?? $byName->get($nameKey);
            if ($match && $barcode !== '' && $match->barcode && (string) $match->barcode !== $barcode) {
                $errors[] = 'يوجد صنف بنفس الاسم لكن بباركود مختلف (' . $match->barcode . ').';
            }

            $rows[] = [
                'line'     => $line,
                'action'   => $errors ? 'error' : ($match ? 'update' : 'create'),
                'match_id' => $match?->id,
                'errors'   => $errors,
                'warnings' => $warnings,
                'data'     => [
                    'name' => $name, 'barcode' => $barcode ?: null, 'category' => $get('category') ?: null, 'unit' => $get('unit') ?: null,
                    'purchase' => $purchase, 'price' => $price, 'qty' => (int) $qty, 'alert' => (int) $alert,
                    'batch' => $get('batch') ?: null, 'expiry' => $expiry, 'controlled' => self::yes($get('controlled')),
                    'big_unit' => $bigUnit ?: null, 'big_factor' => $bigFactor ? (int) $bigFactor : null,
                    'big_price' => $bigPrice,
                ],
            ];
        }

        $summary = [
            'total'   => count($rows),
            'create'  => count(array_filter($rows, fn ($r) => $r['action'] === 'create')),
            'update'  => count(array_filter($rows, fn ($r) => $r['action'] === 'update')),
            'error'   => count(array_filter($rows, fn ($r) => $r['action'] === 'error')),
            'qty'     => array_sum(array_map(fn ($r) => $r['action'] !== 'error' ? $r['data']['qty'] : 0, $rows)),
        ];

        return ['rows' => $rows, 'summary' => $summary, 'unknown_headers' => $unknown];
    }

    /**
     * تنفيذ الصفوف السليمة. $updatePrices: تحديث أسعار الأصناف الموجودة.
     */
    public static function import(array $rows, int $branchId, int $orgId, bool $updatePrices): array
    {
        $done = ['created' => 0, 'updated' => 0, 'stock' => 0, 'skipped' => 0];
        $warehouseId = Stock::warehouseFor($branchId);
        $categories = [];
        $units = [];

        DB::transaction(function () use ($rows, $branchId, $orgId, $updatePrices, $warehouseId, &$done, &$categories, &$units) {
            $category = function (?string $name) use (&$categories, $orgId) {
                if (!$name) {
                    return null;
                }

                return $categories[$name] ??= self::revive(Category::withoutGlobalScopes()->firstOrCreate(['name' => $name, 'organization_id' => $orgId], ['is_active' => true]))->id;
            };
            $unit = function (?string $name) use (&$units, $orgId) {
                if (!$name) {
                    return null;
                }

                return $units[$name] ??= self::revive(Unit::withoutGlobalScopes()->firstOrCreate(['name' => $name, 'organization_id' => $orgId], ['is_active' => true]))->id;
            };

            foreach ($rows as $row) {
                if ($row['action'] === 'error') {
                    $done['skipped']++;
                    continue;
                }
                $d = $row['data'];

                $product = $row['match_id'] ? Product::withoutGlobalScopes()->where('organization_id', $orgId)->lockForUpdate()->find($row['match_id']) : null;
                if ($product) {
                    self::revive($product); // صنف كان محذوفاً: يرجع مع الاستيراد بدل تعديل صنف مخفي
                }

                if (!$product) {
                    $product = Product::create([
                        'organization_id' => $orgId,
                        'name'            => $d['name'],
                        'barcode'         => $d['barcode'],
                        'sku'             => null,
                        'category_id'     => $category($d['category']),
                        'unit_id'         => $unit($d['unit']),
                        'purchase_price'  => $d['purchase'],
                        'selling_price'   => $d['price'],
                        'stock_quantity'  => 0,
                        'alert_quantity'  => $d['alert'],
                        'track_expiry'    => (bool) $d['expiry'],
                        'is_controlled'   => $d['controlled'],
                        'expiry_date'     => $d['expiry'],
                        'is_active'       => true,
                    ]);
                    $done['created']++;
                } else {
                    $changes = array_filter([
                        'barcode'     => $product->barcode ? null : $d['barcode'],
                        'category_id' => $product->category_id ? null : $category($d['category']),
                        'unit_id'     => $product->unit_id ? null : $unit($d['unit']),
                    ]);
                    if ($updatePrices) {
                        $changes += ['purchase_price' => $d['purchase'], 'selling_price' => $d['price'], 'alert_quantity' => $d['alert'], 'is_controlled' => $d['controlled']];
                    }
                    if ($d['expiry']) {
                        $changes['track_expiry'] = true;
                    }
                    if ($changes) {
                        $product->update($changes);
                    }
                    $done['updated']++;
                }

                // الوحدة الكبيرة (علبة/شريط)
                if ($d['big_unit'] && $d['big_factor']) {
                    $unitId = $unit($d['big_unit']);
                    $pu = ProductUnit::withoutGlobalScopes()->firstOrNew(['product_id' => $product->id, 'unit_id' => $unitId]);
                    if (!$pu->exists || $updatePrices) {
                        $pu->conversion_factor = $d['big_factor'];
                        $pu->selling_price = $d['big_price'] ?: round($d['price'] * $d['big_factor'], 2);
                        $pu->purchase_price = round($d['purchase'] * $d['big_factor'], 2);
                        $pu->is_default = $pu->is_default ?? false;
                        $pu->save();
                    }
                }

                // الرصيد الافتتاحي في مستودع الفرع، مع دفعة إن وُجد رقم دفعة أو تاريخ انتهاء
                if ($d['qty'] > 0) {
                    $batchId = null;
                    if ($d['batch'] || $d['expiry']) {
                        $batch = ProductBatch::withoutGlobalScopes()->firstOrNew([
                            'product_id'   => $product->id,
                            'warehouse_id' => $warehouseId,
                            'batch_number' => $d['batch'] ?: 'IMP-' . ($d['expiry'] ? str_replace('-', '', $d['expiry']) : date('Ymd')),
                        ]);
                        $batch->expiry_date = $d['expiry'] ?: $batch->expiry_date;
                        $batch->cost_price = $d['purchase'];
                        $batch->selling_price = $d['price'];
                        $batch->quantity = (int) $batch->quantity + $d['qty'];
                        $batch->save();
                        $batchId = $batch->id;
                    }

                    InventoryTransaction::create([
                        'warehouse_id'   => $warehouseId,
                        'product_id'     => $product->id,
                        'batch_id'       => $batchId,
                        'type'           => 'opening_stock',
                        'reference_type' => 'excel_import',
                        'qty_in'         => $d['qty'],
                        'qty_out'        => 0,
                    ]);
                    $done['stock'] += $d['qty'];
                }
            }
        });

        return $done;
    }

    // ===================== مساعدات =====================

    /**
     * ربط عناوين الملف بالأعمدة (الترتيب غير مهم)
     */
    public static function mapHeaders(array $headers): array
    {
        $rules = [
            'big_price'  => ['سعر بيع الوحدة الكبيرة', 'سعر الكبيرة', 'سعر العلبة', 'box price'],
            'big_factor' => ['عدد الوحدات', 'عدد الحبات', 'العدد في', 'factor', 'pieces'],
            'big_unit'   => ['الوحدة الكبيرة', 'وحدة كبيرة', 'big unit', 'pack'],
            'purchase'   => ['سعر الشراء', 'الشراء', 'التكلفة', 'cost', 'purchase'],
            'price'      => ['سعر البيع', 'البيع', 'السعر', 'price', 'selling'],
            'batch'      => ['رقم الدفعة', 'الدفعة', 'التشغيلة', 'lot', 'batch'],
            'expiry'     => ['تاريخ الانتهاء', 'الانتهاء', 'الصلاحية', 'expiry', 'exp'],
            'controlled' => ['رقابة', 'controlled'],
            'alert'      => ['حد التنبيه', 'الحد الأدنى', 'تنبيه', 'alert', 'min'],
            'qty'        => ['الكمية', 'الرصيد', 'كمية', 'qty', 'quantity', 'stock'],
            'unit'       => ['الوحدة الأساسية', 'الوحدة', 'unit'],
            'category'   => ['التصنيف', 'الفئة', 'تصنيف', 'category'],
            'barcode'    => ['الباركود', 'باركود', 'barcode', 'ean'],
            'name'       => ['اسم الصنف', 'الاسم', 'اسم الدواء', 'الصنف', 'name', 'product'],
        ];

        $map = [];
        $unknown = [];
        foreach ($headers as $i => $header) {
            $h = mb_strtolower(trim(str_replace('*', '', (string) $header)));
            if ($h === '') {
                continue;
            }
            $found = null;
            foreach ($rules as $key => $needles) {
                if (isset($map[$key])) {
                    continue;
                }
                foreach ($needles as $needle) {
                    if (str_contains($h, mb_strtolower($needle))) {
                        $found = $key;
                        break 2;
                    }
                }
            }
            if ($found) {
                $map[$found] = $i;
            } else {
                $unknown[] = trim((string) $header);
            }
        }

        return [$map, $unknown];
    }

    public static function number(?string $value): ?float
    {
        $v = self::digitsToLatin(trim((string) $value));
        $v = str_replace(['₪', 'ش', ' ', "\u{00A0}", 'ILS', '٫'], ['', '', '', '', '', '.'], $v);
        if ($v === '') {
            return null;
        }
        // فاصلة عشرية (2,5) أو فاصلة آلاف (1,250.50)
        if (str_contains($v, ',') && !str_contains($v, '.')) {
            $v = substr_count($v, ',') === 1 && strlen(substr($v, strpos($v, ',') + 1)) <= 2 ? str_replace(',', '.', $v) : str_replace(',', '', $v);
        } else {
            $v = str_replace(',', '', $v);
        }

        return is_numeric($v) ? round((float) $v, 4) : null;
    }

    public static function yes(?string $value): bool
    {
        return in_array(mb_strtolower(trim((string) $value)), ['نعم', 'ن', 'yes', 'y', '1', 'true', '✓', 'x'], true);
    }

    public static function normName(?string $name): string
    {
        $n = mb_strtolower(trim((string) $name));
        $n = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $n);
        $n = strtr($n, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي']);

        return preg_replace('/\s+/u', ' ', $n);
    }

    private static function digitsToLatin(string $v): string
    {
        return strtr($v, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
    }

    /** استرجاع سجل محذوف حذفاً ناعماً إن وُجد */
    private static function revive($model)
    {
        if (method_exists($model, 'trashed') && $model->trashed()) {
            $model->restore();
        }

        return $model;
    }
}
