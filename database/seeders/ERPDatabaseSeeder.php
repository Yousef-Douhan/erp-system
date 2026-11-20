<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\Models\Branch;
use App\Models\User;
use App\Models\Customer;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Support\Facades\Hash;

class ERPDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. إنشاء المؤسسة
        $organization = Organization::create([
            'name' => 'صيدلية الأمل المركزية',
            'currency' => 'USD',
            'timezone' => 'Asia/Gaza',
            'status' => 'active',
        ]);

        // 2. إنشاء الفرع
        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'الفرع الرئيسي',
            'type' => 'pharmacy',
            'phone' => '0592157706',
            'address' => 'نابلس، فلسطين',
            'status' => 'active',
        ]);

        // 3. إنشاء المستخدم
        $user = User::create([
            'organization_id' => $organization->id,
            'username' => 'youssef',
            'email' => 'yousefhassandouhan09@gmail.com',
            'phone' => '0592157706',
            'password' => Hash::make('12345678'),
            'is_active' => true,
        ]);

        // 4. الاسم الكامل والراتب على المستخدم نفسه
        $user->forceFill(['full_name' => 'يوسف دوحان', 'salary' => 1200.00, 'branch_id' => $branch->id])->save();

        // 5. إنشاء العميل
        $customer = Customer::create([
            'organization_id' => $organization->id,
            'name' => 'أحمد محمد',
            'phone' => '0591111111',
            'balance' => 0.00,
            'credit_limit' => 500.00,
        ]);

        // 6. إنشاء التصنيف
        $category = Category::create([
            'organization_id' => $organization->id,
            'name' => 'أدوية عامة',
            'is_active' => true,
        ]);

        // 7. إنشاء المنتج (غير مراقب لتسهيل البيع التجريبي حالياً)
        $product = Product::create([
            'organization_id' => $organization->id,
            'category_id' => $category->id,
            'name' => 'بانادول إكسترا',
            'sku' => 'PAN-EXT-001',
            'track_expiry' => false,
            'is_controlled' => false,
            'is_active' => true,
        ]);

        // 8. إنشاء دفعة مخزونية (Batch) لكي يتوفر مخزون برقم ID = 1
        ProductBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $branch->id, // نفس الـ branch_id
            'batch_number' => 'BATCH-2026-01',
            'expiry_date' => '2028-12-31',
            'cost_price' => 2.50,
            'selling_price' => 4.00,
            'quantity' => 100, // توفر 100 حبة في المخزون
        ]);
    }
}
