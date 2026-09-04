<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-xs text-slate-400 font-semibold">إجمالي أذونات الشراء</p>
        <h3 class="text-2xl font-bold text-slate-800 mt-2">12 أمر طلب</h3>
      </div>
      <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-xs text-slate-400 font-semibold">المبالغ المستحقة للموردين</p>
        <h3 class="text-2xl font-bold text-slate-800 mt-2">₪ 8,450.00</h3>
      </div>
      <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-xs text-slate-400 font-semibold">الموردين النشطين</p>
        <h3 class="text-2xl font-bold text-slate-800 mt-2">6 شركات</h3>
      </div>
    </div>

    <!-- جدول أوامر الشراء -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
      <h4 class="font-bold text-slate-800 mb-4">أحدث فواتير المشتريات</h4>
      <table class="w-full text-right text-sm">
        <thead class="bg-slate-50 border-b border-slate-100 text-slate-400">
          <tr>
            <th class="py-3 px-4">رقم الفاتورة</th>
            <th class="py-3 px-4">اسم المورد</th>
            <th class="py-3 px-4">التاريخ</th>
            <th class="py-3 px-4">المبلغ الإجمالي</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr>
            <td class="py-3 px-4 font-mono font-bold text-indigo-600">#PUR-9081</td>
            <td class="py-3 px-4">شركة الأدوية المركزية</td>
            <td class="py-3 px-4 text-slate-500">2026-08-18</td>
            <td class="py-3 px-4 font-semibold">₪ 3,100.00</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>