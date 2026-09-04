
<template>
  <div class="space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-4 shadow-sm">
      <div class="flex items-center gap-3 w-full sm:w-auto">
        <input type="text" v-model="search" @input="fetchItems" placeholder="بحث باسم الدواء أو البار كود..." class="w-full sm:w-80 pr-10 pl-4 py-2.5 rounded-xl border border-slate-100 bg-slate-50 text-sm focus:bg-white focus:outline-indigo-500">
      </div>
      <button @click="openAddModal" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-all shadow-md shadow-indigo-100 flex items-center gap-2">
        + إضافة دواء جديد
      </button>
    </div>

    <!-- جدول الأدوية والمستحضرات -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <table class="w-full text-right border-collapse text-sm">
        <thead class="bg-slate-50 border-b border-slate-100 text-slate-400 font-semibold">
          <tr>
            <th class="py-4 px-6">اسم المستحضر/الدواء</th>
            <th class="py-4 px-6">الكمية المتوفرة</th>
            <th class="py-4 px-6">تاريخ الصلاحية</th>
            <th class="py-4 px-6">السعر</th>
            <th class="py-4 px-6 text-center">الحالة</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="item in items" :key="item.id" class="hover:bg-slate-50/50">
            <td class="py-4 px-6 font-medium text-slate-800">{{ item.name }}</td>
            <td class="py-4 px-6">
              <span :class="item.stock <= 5 ? 'text-rose-600 font-bold' : 'text-slate-600'">{{ item.stock }}</span>
            </td>
            <td class="py-4 px-6 text-slate-500">{{ item.expiry_date }}</td>
            <td class="py-4 px-6 font-semibold text-slate-800">₪ {{ item.price }}</td>
            <td class="py-4 px-6 text-center">
              <span v-if="item.stock <= 5" class="bg-rose-50 text-rose-600 px-3 py-1 rounded-full text-xs font-semibold">نوشك على النفاد</span>
              <span v-else class="bg-emerald-50 text-emerald-600 px-3 py-1 rounded-full text-xs font-semibold">متوفر</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const items = ref([
  { id: 1, name: 'Paracetamol 500mg', stock: 120, expiry_date: '2027-05-01', price: 15.00 },
  { id: 2, name: 'Amoxicillin 250mg', stock: 3, expiry_date: '2026-11-15', price: 42.00 },
  { id: 3, name: 'Ibuprofen 400mg', stock: 45, expiry_date: '2028-01-10', price: 22.50 }
]);
const search = ref('');
</script>