<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DrugAlert;
use App\Models\InventoryTransaction;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * إضافة 100 منتج صيدلاني جاهز للبيع:
 * التصنيفات، الوحدات (حبة/شريط/علبة...)، وحدات البيع بأسعارها وباركوداتها،
 * دفعات بتواريخ صلاحية، وحركة "رصيد افتتاحي" لكل دفعة حتى يتطابق المخزون مع الدفعات.
 *
 * التشغيل:  php artisan db:seed --class=PharmacyProductsSeeder
 * آمن للتكرار: المنتج الموجود بنفس الباركود لا يُضاف مرة ثانية.
 */
class PharmacyProductsSeeder extends Seeder
{
    // إزاحة الترقيم (للباركود ورمز المنتج ورقم الدفعة) حتى لا تتكرر بين ملفات البيانات
    protected int $offset = 0;

    // [الاسم، التصنيف، النوع، سعر الوحدة الأساسية، الحد الأدنى، خاضع للرقابة]
    // النوع: tab/cap = أقراص/كبسولات (حبة، شريط 10، علبة 30) | syrup | cream | drops | inj | sachet | supply
    protected array $items = [
        // مسكنات وخافضات حرارة
        ['باراسيتامول 500 ملغ', 'pain', 'tab', 0.30, 60],
        ['باراسيتامول 1000 ملغ', 'pain', 'tab', 0.45, 40],
        ['إيبوبروفين 400 ملغ', 'pain', 'tab', 0.50, 40],
        ['إيبوبروفين 600 ملغ', 'pain', 'tab', 0.70, 30],
        ['ديكلوفيناك صوديوم 50 ملغ', 'pain', 'tab', 0.60, 30],
        ['نابروكسين 500 ملغ', 'pain', 'tab', 0.80, 20],
        ['أسبرين 100 ملغ', 'pain', 'tab', 0.15, 60],
        ['ميلوكسيكام 15 ملغ', 'pain', 'tab', 0.75, 20],
        ['باراسيتامول شراب أطفال 120 ملغ', 'kids', 'syrup', 9.00, 10],
        ['إيبوبروفين شراب أطفال 100 ملغ', 'kids', 'syrup', 12.00, 10],
        ['ترامادول 50 ملغ', 'pain', 'cap', 1.20, 20, true],

        // مضادات حيوية
        ['أموكسيسيلين 500 ملغ', 'anti', 'cap', 0.80, 30],
        ['أموكسيسيلين 250 ملغ شراب', 'anti', 'syrup', 14.00, 8],
        ['أموكسيسيلين مع كلافولانيك 625 ملغ', 'anti', 'tab', 2.20, 20],
        ['أموكسيسيلين مع كلافولانيك 1 غم', 'anti', 'tab', 3.00, 14],
        ['أزيثرومايسين 500 ملغ', 'anti', 'tab', 4.50, 9],
        ['أزيثرومايسين 200 ملغ شراب', 'anti', 'syrup', 28.00, 6],
        ['سيفالكسين 500 ملغ', 'anti', 'cap', 1.30, 20],
        ['سيفيكسيم 400 ملغ', 'anti', 'cap', 4.00, 10],
        ['سيبروفلوكساسين 500 ملغ', 'anti', 'tab', 1.60, 20],
        ['ليفوفلوكساسين 500 ملغ', 'anti', 'tab', 3.20, 14],
        ['كلاريثرومايسين 500 ملغ', 'anti', 'tab', 3.50, 14],
        ['دوكسيسيكلين 100 ملغ', 'anti', 'cap', 1.00, 20],
        ['ميترونيدازول 500 ملغ', 'anti', 'tab', 0.50, 30],
        ['نيتروفورانتوين 100 ملغ', 'anti', 'cap', 1.10, 20],

        // أمراض مزمنة (ضغط، سكري، قلب، دهون)
        ['ميتفورمين 500 ملغ', 'chronic', 'tab', 0.25, 60],
        ['ميتفورمين 850 ملغ', 'chronic', 'tab', 0.35, 60],
        ['ميتفورمين 1000 ملغ', 'chronic', 'tab', 0.45, 60],
        ['غليكلازايد 80 ملغ', 'chronic', 'tab', 0.40, 40],
        ['غليميبيرايد 2 ملغ', 'chronic', 'tab', 0.60, 30],
        ['أملوديبين 5 ملغ', 'chronic', 'tab', 0.40, 60],
        ['أملوديبين 10 ملغ', 'chronic', 'tab', 0.55, 40],
        ['لوسارتان 50 ملغ', 'chronic', 'tab', 0.70, 40],
        ['فالسارتان 80 ملغ', 'chronic', 'tab', 0.90, 30],
        ['إنالابريل 10 ملغ', 'chronic', 'tab', 0.35, 40],
        ['بيسوبرولول 5 ملغ', 'chronic', 'tab', 0.60, 30],
        ['أتينولول 50 ملغ', 'chronic', 'tab', 0.30, 30],
        ['هيدروكلوروثيازيد 25 ملغ', 'chronic', 'tab', 0.20, 30],
        ['فوروسيمايد 40 ملغ', 'chronic', 'tab', 0.25, 30],
        ['أتورفاستاتين 20 ملغ', 'chronic', 'tab', 0.90, 40],
        ['أتورفاستاتين 40 ملغ', 'chronic', 'tab', 1.20, 30],
        ['روزوفاستاتين 10 ملغ', 'chronic', 'tab', 1.10, 30],
        ['سيمفاستاتين 20 ملغ', 'chronic', 'tab', 0.50, 30],
        ['كلوبيدوقريل 75 ملغ', 'chronic', 'tab', 1.00, 30],
        ['وارفارين 5 ملغ', 'chronic', 'tab', 0.50, 30],
        ['ليفوثيروكسين 50 ميكروغرام', 'chronic', 'tab', 0.30, 40],
        ['ليفوثيروكسين 100 ميكروغرام', 'chronic', 'tab', 0.40, 40],

        // الجهاز الهضمي
        ['أوميبرازول 20 ملغ', 'gi', 'cap', 0.60, 40],
        ['أوميبرازول 40 ملغ', 'gi', 'cap', 0.90, 30],
        ['إيزوميبرازول 40 ملغ', 'gi', 'tab', 1.40, 30],
        ['بانتوبرازول 40 ملغ', 'gi', 'tab', 1.20, 30],
        ['فاموتيدين 40 ملغ', 'gi', 'tab', 0.50, 30],
        ['دومبيريدون 10 ملغ', 'gi', 'tab', 0.40, 30],
        ['ميبيفيرين 135 ملغ', 'gi', 'tab', 0.80, 30],
        ['لوبيراميد 2 ملغ', 'gi', 'cap', 0.50, 20],
        ['أملاح الإماهة الفموية', 'gi', 'sachet', 1.50, 20],
        ['شراب لاكتولوز', 'gi', 'syrup', 18.00, 6],
        ['شراب مضاد للحموضة', 'gi', 'syrup', 15.00, 8],
        ['حبوب بيساكوديل 5 ملغ', 'gi', 'tab', 0.30, 30],

        // الحساسية والبرد والجهاز التنفسي
        ['لوراتادين 10 ملغ', 'allergy', 'tab', 0.50, 30],
        ['سيتريزين 10 ملغ', 'allergy', 'tab', 0.45, 30],
        ['ديسلوراتادين 5 ملغ', 'allergy', 'tab', 0.90, 30],
        ['فيكسوفينادين 180 ملغ', 'allergy', 'tab', 1.30, 20],
        ['مونتيلوكاست 10 ملغ', 'allergy', 'tab', 1.50, 28],
        ['شراب سيتريزين للأطفال', 'kids', 'syrup', 13.00, 8],
        ['شراب كحة جاف', 'allergy', 'syrup', 16.00, 8],
        ['شراب كحة مع بلغم (أمبروكسول)', 'allergy', 'syrup', 14.00, 8],
        ['بخاخ سالبيوتامول 100 ميكروغرام', 'allergy', 'inhaler', 22.00, 6],
        ['بخاخ أنف ملحي', 'allergy', 'spray', 12.00, 8],
        ['بخاخ أنف موميتازون', 'allergy', 'spray', 38.00, 4],
        ['شراب كودايين للكحة', 'allergy', 'syrup', 25.00, 4, true],

        // فيتامينات ومكملات
        ['فيتامين د 1000 وحدة', 'vit', 'cap', 0.40, 40],
        ['فيتامين د 50000 وحدة', 'vit', 'cap', 3.00, 10],
        ['فيتامين سي 1000 ملغ فوار', 'vit', 'effervescent', 1.20, 20],
        ['فيتامين ب المركب', 'vit', 'tab', 0.40, 30],
        ['حديد مع حمض الفوليك', 'vit', 'tab', 0.50, 30],
        ['حمض الفوليك 5 ملغ', 'vit', 'tab', 0.20, 30],
        ['كالسيوم مع فيتامين د', 'vit', 'tab', 0.60, 30],
        ['مغنيسيوم 250 ملغ', 'vit', 'tab', 0.70, 30],
        ['زنك 50 ملغ', 'vit', 'tab', 0.50, 30],
        ['أوميغا 3 1000 ملغ', 'vit', 'cap', 0.80, 30],
        ['فيتامينات متعددة للكبار', 'vit', 'tab', 0.90, 30],
        ['شراب فيتامينات للأطفال', 'kids', 'syrup', 24.00, 6],

        // الجلدية والعيون والأذن
        ['كريم فيوسيدك أسيد 2%', 'derma', 'cream', 22.00, 6],
        ['كريم هيدروكورتيزون 1%', 'derma', 'cream', 14.00, 6],
        ['كريم كلوتريمازول 1%', 'derma', 'cream', 12.00, 6],
        ['كريم مرطب للبشرة الجافة', 'derma', 'cream', 28.00, 5],
        ['جل ديكلوفيناك للمفاصل', 'derma', 'cream', 18.00, 6],
        ['قطرة عين مرطبة', 'eye', 'drops', 19.00, 6],
        ['قطرة عين مضاد حيوي', 'eye', 'drops', 16.00, 6],
        ['قطرة أذن', 'eye', 'drops', 15.00, 5],

        // الأعصاب (خاضعة للرقابة)
        ['ديازيبام 5 ملغ', 'neuro', 'tab', 0.60, 20, true],
        ['كلونازيبام 2 ملغ', 'neuro', 'tab', 0.90, 20, true],

        // مستلزمات طبية
        ['كمامات طبية (علبة 50)', 'supply', 'supply', 15.00, 10],
        ['شاش طبي معقم', 'supply', 'supply', 3.00, 20],
        ['لاصق جروح (علبة 20)', 'supply', 'supply', 8.00, 10],
        ['ميزان حرارة رقمي', 'supply', 'supply', 25.00, 4],
        ['جهاز قياس ضغط رقمي', 'supply', 'supply', 140.00, 2],
        ['شرائط فحص سكر (50 شريط)', 'supply', 'supply', 65.00, 4],
        ['معقم يدين 500 مل', 'supply', 'supply', 14.00, 8],
    ];

    protected array $categories = [
        'pain'    => 'المسكنات وخافضات الحرارة',
        'anti'    => 'المضادات الحيوية',
        'chronic' => 'الأمراض المزمنة',
        'gi'      => 'الجهاز الهضمي',
        'allergy' => 'الحساسية والجهاز التنفسي',
        'vit'     => 'الفيتامينات والمكملات',
        'kids'    => 'أدوية الأطفال',
        'derma'   => 'الجلدية',
        'eye'     => 'العيون والأذن',
        'neuro'   => 'الأعصاب',
        'supply'  => 'المستلزمات الطبية',
        'women'   => 'صحة المرأة',
        'care'    => 'العناية الشخصية',
    ];

    // الوحدة الأساسية لكل نوع + وحدات البيع الإضافية [الاسم، معامل التحويل، نسبة السعر]
    protected array $types = [
        'tab'          => ['base' => 'حبة', 'extra' => [['شريط', 10, 0.95], ['علبة', 30, 0.90]]],
        'cap'          => ['base' => 'كبسولة', 'extra' => [['شريط', 10, 0.95], ['علبة', 30, 0.90]]],
        'effervescent' => ['base' => 'قرص فوار', 'extra' => [['أنبوبة', 10, 0.95]]],
        'sachet'       => ['base' => 'كيس', 'extra' => [['علبة', 10, 0.92]]],
        'syrup'        => ['base' => 'زجاجة', 'extra' => []],
        'cream'        => ['base' => 'أنبوب', 'extra' => []],
        'drops'        => ['base' => 'عبوة', 'extra' => []],
        'inhaler'      => ['base' => 'بخاخ', 'extra' => []],
        'spray'        => ['base' => 'بخاخ', 'extra' => []],
        'supply'       => ['base' => 'قطعة', 'extra' => []],
        'supp'         => ['base' => 'تحميلة', 'extra' => [['علبة', 10, 0.92]]],
        'lotion'       => ['base' => 'عبوة', 'extra' => []],
        'pack'         => ['base' => 'علبة', 'extra' => []],
    ];

    // تنبيهات دوائية مهمة لبعض الأصناف
    protected array $alerts = [
        'وارفارين 5 ملغ' => ['critical', 'يتفاعل مع الأسبرين ومضادات الالتهاب (إيبوبروفين، ديكلوفيناك) ويزيد خطر النزيف. تأكد من الأدوية الأخرى التي يتناولها المريض.'],
        'ترامادول 50 ملغ' => ['high', 'يسبب النعاس والإدمان. لا يُصرف مع المهدئات أو الكحول.'],
        'ديازيبام 5 ملغ' => ['high', 'مهدئ خاضع للرقابة. لا يُصرف بدون وصفة، ويُنبَّه المريض لتجنب القيادة.'],
        'كلونازيبام 2 ملغ' => ['high', 'مهدئ خاضع للرقابة. لا يُصرف بدون وصفة.'],
        'شراب كودايين للكحة' => ['high', 'لا يُعطى للأطفال تحت 12 سنة، وخاضع للرقابة.'],
        'أسبرين 100 ملغ' => ['medium', 'لا يُعطى للأطفال والمراهقين تحت 16 سنة. يزيد خطر النزيف مع الوارفارين.'],
        'دوكسيسيكلين 100 ملغ' => ['medium', 'لا يُعطى للحوامل والأطفال تحت 8 سنوات. يؤخذ مع ماء كثير.'],
        'سيبروفلوكساسين 500 ملغ' => ['medium', 'يقلل امتصاصه مع الحليب ومكملات الكالسيوم والحديد، يؤخذ قبلها بساعتين.'],
        'ميترونيدازول 500 ملغ' => ['medium', 'يمنع تناول الكحول أثناء العلاج.'],
    ];

    public function run(): void
    {
        $orgId = Organization::value('id') ?? Organization::create([
            'name' => 'الصيدلية المركزية', 'currency' => 'ILS', 'timezone' => 'Asia/Gaza', 'status' => 'active',
        ])->id;

        $warehouseId = Warehouse::query()->value('id') ?? Warehouse::create(['name' => 'المستودع الرئيسي'])->id;

        $categoryIds = [];
        foreach ($this->categories as $key => $name) {
            $categoryIds[$key] = Category::firstOrCreate(
                ['name' => $name, 'organization_id' => $orgId],
                ['is_active' => true]
            )->id;
        }

        $unitIds = [];
        $unitName = function (string $name) use (&$unitIds, $orgId) {
            return $unitIds[$name] ??= Unit::firstOrCreate(
                ['name' => $name, 'organization_id' => $orgId],
                ['is_active' => true]
            )->id;
        };

        $added = 0;
        $skipped = 0;

        DB::transaction(function () use ($orgId, $warehouseId, $categoryIds, $unitName, &$added, &$skipped) {
            foreach ($this->items as $i => $row) {
                [$name, $cat, $type, $price, $alert] = $row;
                $controlled = $row[5] ?? false;
                $n = $i + 1 + $this->offset;
                $barcode = $this->ean13(629100000000 + $n * 10);

                if (Product::where('barcode', $barcode)->orWhere('name', $name)->exists()) {
                    $skipped++;
                    continue;
                }

                $typeDef = $this->types[$type];
                $isMedicine = $cat !== 'supply';
                $purchase = round($price * 0.70, 2);

                $product = Product::create([
                    'organization_id' => $orgId,
                    'category_id'     => $categoryIds[$cat],
                    'unit_id'         => $unitName($typeDef['base']),
                    'name'            => $name,
                    'sku'             => 'PH-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                    'barcode'         => $barcode,
                    'purchase_price'  => $purchase,
                    'selling_price'   => $price,
                    'stock_quantity'  => 0, // يُضاف عبر حركة الرصيد الافتتاحي أدناه
                    'alert_quantity'  => $alert,
                    'track_expiry'    => $isMedicine,
                    'is_controlled'   => $controlled,
                    'is_active'       => true,
                    'description'     => null,
                ]);

                // وحدات البيع الإضافية (شريط، علبة...) بأسعار أقل قليلاً من سعر الحبة
                foreach ($typeDef['extra'] as $j => [$extraName, $factor, $ratio]) {
                    ProductUnit::create([
                        'product_id'        => $product->id,
                        'unit_id'           => $unitName($extraName),
                        'conversion_factor' => $factor,
                        'purchase_price'    => round($purchase * $factor * $ratio, 2),
                        'selling_price'     => $this->nicePrice($price * $factor * $ratio),
                        'barcode'           => $this->ean13(629200000000 + $n * 10 + $j + 1),
                        'is_default'        => false,
                    ]);
                }

                // الدفعات: دفعة أو دفعتان بتواريخ صلاحية مختلفة
                $factorForStock = $typeDef['extra'] ? $typeDef['extra'][count($typeDef['extra']) - 1][1] : 1;
                $batches = $this->batchPlan($i, $alert, $factorForStock, $isMedicine);

                foreach ($batches as $k => [$qty, $expiry]) {
                    $batch = ProductBatch::create([
                        'product_id'         => $product->id,
                        'warehouse_id'       => $warehouseId,
                        'batch_number'       => 'B' . now()->format('y') . '-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT) . chr(65 + $k),
                        'manufacturing_date' => $isMedicine ? $expiry?->copy()->subYears(2) : null,
                        'expiry_date'        => $expiry,
                        'cost_price'         => $purchase,
                        'selling_price'      => $price,
                        'quantity'           => $qty,
                    ]);

                    // حركة رصيد افتتاحي: تزيد رصيد المنتج تلقائياً
                    InventoryTransaction::create([
                        'warehouse_id' => $warehouseId,
                        'product_id'   => $product->id,
                        'batch_id'     => $batch->id,
                        'type'         => 'opening_stock',
                        'qty_in'       => $qty,
                        'qty_out'      => 0,
                    ]);
                }

                // آخر تاريخ صلاحية على مستوى المنتج (أقرب دفعة)
                $nearest = collect($batches)->pluck(1)->filter()->sortBy(fn ($d) => $d->timestamp)->first();
                if ($nearest) {
                    $product->updateQuietly(['expiry_date' => $nearest]);
                }

                if (isset($this->alerts[$name])) {
                    [$severity, $message] = $this->alerts[$name];
                    DrugAlert::create(['product_id' => $product->id, 'severity' => $severity, 'message' => $message]);
                }

                $added++;
            }
        });

        $this->command?->info("تمت إضافة {$added} منتج" . ($skipped ? "، وتم تخطي {$skipped} موجود مسبقاً." : '.'));
    }

    /**
     * خطة الدفعات: أغلب المنتجات مخزونها جيد، وبعضها منخفض، وبعضها قريب الانتهاء (لتجربة التنبيهات)
     */
    private function batchPlan(int $i, int $alert, int $packSize, bool $isMedicine): array
    {
        $months = fn (int $m) => $isMedicine ? today()->addMonths($m) : null;

        // كل 15 منتج: واحد مخزونه أقل من الحد الأدنى
        if ($i % 15 === 7) {
            return [[max(1, intdiv($alert, 2)), $months(14)]];
        }

        $base = $packSize * (3 + ($i * 7) % 6); // من 3 إلى 8 علب/عبوات
        $base = max($base, $alert * 3);

        // كل 12 منتج: دفعة قريبة الانتهاء (خلال 3-5 أسابيع) + دفعة جديدة
        if ($isMedicine && $i % 12 === 3) {
            return [
                [max(5, intdiv($base, 3)), today()->addDays(20 + $i % 15)],
                [$base, $months(18)],
            ];
        }

        // كل 4 منتجات: دفعتان (قديمة وجديدة)
        if ($i % 4 === 0) {
            return [
                [intdiv($base, 2), $months(8 + $i % 6)],
                [$base, $months(20 + $i % 8)],
            ];
        }

        return [[$base, $months(12 + $i % 18)]];
    }

    private function nicePrice(float $value): float
    {
        // تقريب لأقرب 0.5 شيكل
        return max(0.5, round($value * 2) / 2);
    }

    /**
     * باركود EAN-13 صالح (12 رقم + رقم التحقق)
     */
    private function ean13(int $twelveDigits): string
    {
        $digits = str_pad((string) $twelveDigits, 12, '0', STR_PAD_LEFT);
        $sum = 0;
        foreach (str_split($digits) as $pos => $d) {
            $sum += (int) $d * ($pos % 2 === 0 ? 1 : 3);
        }

        return $digits . ((10 - $sum % 10) % 10);
    }
}
