<template>
  <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-6">
    <div class="flex justify-between items-center border-b border-slate-100 pb-4">
      <div>
        <h3 class="text-lg font-bold text-slate-800">الأدوار والصلاحيات (Spatie Roles)</h3>
        <p class="text-xs text-slate-400">التحكم في وصول المستخدمين للواجهات والوظائف</p>
      </div>
      <button class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-indigo-700">
        + إضافة دور جديد
      </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div v-for="role in roles" :key="role.id" class="border border-slate-100 rounded-2xl p-5 bg-slate-50/50">
        <div class="flex justify-between items-center mb-3">
          <h4 class="font-bold text-indigo-900">{{ role.name }}</h4>
          <span class="text-xs bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-lg font-semibold">{{ role.users_count }} مستخدمين</span>
        </div>
        <div class="flex flex-wrap gap-2 mt-4">
          <span v-for="permission in role.permissions" :key="permission" class="bg-white border border-slate-200 text-slate-600 text-xs px-2.5 py-1 rounded-md">
            {{ permission }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const roles = ref([
  { id: 1, name: 'Admin (مدير النظام)', users_count: 2, permissions: ['view_dashboard', 'manage_users', 'manage_pharmacy', 'manage_roles'] },
  { id: 2, name: 'Pharmacist (صيدلي)', users_count: 5, permissions: ['view_dashboard', 'manage_pharmacy', 'create_sales'] },
  { id: 3, name: 'Accountant (محاسب)', users_count: 1, permissions: ['view_dashboard', 'manage_purchases', 'view_reports'] }
]);
</script>