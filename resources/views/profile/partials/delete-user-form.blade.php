<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">إيقاف الحساب</h2>

        <p class="mt-1 text-sm text-gray-600">
            عند إيقاف حسابك لن تستطيع الدخول به، لكن لا يُحذف أي شيء: فواتيرك وحركاتك تبقى محفوظة،
            ويمكن للإدارة استرجاع الحساب من «سلة المحذوفات».
        </p>
    </header>

    @if (\App\Http\Controllers\ProfileController::cannotSelfDelete(auth()->user()))
        <p class="text-sm" style="background:#FEF3C7;color:#92400E;padding:10px 12px;border-radius:10px">
            حساب مدير النظام وصاحب الصيدلية لا يمكن إيقافه من هنا، حتى لا تبقى الصيدلية بدون مسؤول.
        </p>
    @else
        <x-danger-button
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        >إيقاف حسابي</x-danger-button>

        <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
            <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
                @csrf
                @method('delete')

                <h2 class="text-lg font-medium text-gray-900">هل تريد إيقاف حسابك؟</h2>

                <p class="mt-1 text-sm text-gray-600">
                    سيتم تسجيل خروجك ولن تستطيع الدخول حتى تسترجع الإدارة حسابك. أدخل كلمة المرور للتأكيد.
                </p>

                <div class="mt-6">
                    <x-input-label for="password" value="كلمة المرور" class="sr-only" />

                    <x-text-input
                        id="password"
                        name="password"
                        type="password"
                        class="mt-1 block w-3/4"
                        placeholder="كلمة المرور"
                    />

                    <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end">
                    <x-secondary-button x-on:click="$dispatch('close')">إلغاء</x-secondary-button>

                    <x-danger-button class="ms-3">إيقاف الحساب</x-danger-button>
                </div>
            </form>
        </x-modal>
    @endif
</section>
