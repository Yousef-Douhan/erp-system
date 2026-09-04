<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل الدخول - نظام الإدارة الشامل ERP</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 font-sans antialiased m-0 p-0 overflow-x-hidden">

    <div class="min-h-screen w-screen flex flex-col lg:flex-row m-0 p-0">
        
        <!-- الجانب الأيمن (الترحيبي): يظهر بكامل العرض في الشاشات الكبيرة -->
        <div class="lg:w-1/2 relative bg-gradient-to-br from-indigo-950 via-slate-900 to-slate-950 p-8 sm:p-12 lg:p-16 flex flex-col justify-between overflow-hidden border-l border-slate-800/80">
            <!-- خلفيات ضوئية جمالية -->
            <div class="absolute -top-32 -right-32 w-96 h-96 bg-indigo-600/15 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-32 -left-32 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

            <!-- الشعار العلوي -->
            <div class="relative z-10 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-black shadow-lg shadow-indigo-500/30 text-base">
                    E
                </div>
                <span class="text-white font-bold text-base tracking-wide">نظام الإدارة الشامل ERP</span>
            </div>

            <!-- محتوى ترحيبي في المنتصف -->
            <div class="relative z-10 space-y-4 my-auto py-10 max-w-xl">
                <span class="px-3.5 py-1.5 bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 rounded-full text-xs font-semibold inline-block">
                    الإصدار الحديث 2026
                </span>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white leading-tight tracking-tight">
                    إدارة أعمالك <br>بكل ذكاء واحترافية.
                </h1>
                <p class="text-slate-400 text-sm leading-relaxed max-w-md">
                    منصة متكاملة لإدارة المبيعات، العملاء، المخازن، والحسابات المالية بدقة وسرعة عالية.
                </p>
            </div>

            <!-- حقوق النشر -->
            <div class="relative z-10 text-xs text-slate-500">
                جميع الحقوق محفوظة &copy; {{ date('Y') }} - يوسف دوحان
            </div>
        </div>

        <!-- الجانب الأيسر (نموذج تسجيل الدخول) -->
        <div class="lg:w-1/2 flex items-center justify-center p-6 sm:p-12 lg:p-16 bg-slate-950">
            <div class="w-full max-w-md space-y-8 bg-slate-900/50 p-8 sm:p-10 rounded-3xl border border-slate-800/80 backdrop-blur-2xl shadow-2xl">
                
                <div class="space-y-2">
                    <h2 class="text-2xl font-black text-white tracking-tight">مرحباً بك مجدداً 👋</h2>
                    <p class="text-xs text-slate-400">الرجاء إدخال بيانات حسابك للوصول إلى لوحة التحكم</p>
                </div>

                <!-- Session Status -->
                <x-auth-session-status class="text-xs" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <!-- Email Address -->
                    <div class="space-y-1.5">
                        <label for="email" class="block text-xs font-bold text-slate-300">البريد الإلكتروني</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="name@example.com"
                            class="w-full bg-slate-900/90 border border-slate-800 rounded-xl px-4 py-3.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all shadow-inner" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1 text-xs" />
                    </div>

                    <!-- Password -->
                    <div class="space-y-1.5">
                        <label for="password" class="block text-xs font-bold text-slate-300">كلمة المرور</label>
                        <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••"
                            class="w-full bg-slate-900/90 border border-slate-800 rounded-xl px-4 py-3.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all shadow-inner" />
                        <x-input-error :messages="$errors->get('password')" class="mt-1 text-xs" />
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between text-xs pt-1">
                        <label for="remember_me" class="inline-flex items-center gap-2 cursor-pointer">
                            <input id="remember_me" type="checkbox" name="remember" class="rounded bg-slate-900 border-slate-700 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer">
                            <span class="text-slate-400 font-medium">تذكرني على هذا الجهاز</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-indigo-400 hover:text-indigo-300 font-semibold transition-colors">
                                نسيت كلمة المرور؟
                            </a>
                        @endif
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-3">
                        <button type="submit" class="w-full py-4 px-4 bg-indigo-600 hover:bg-indigo-500 active:scale-[0.99] text-white text-xs font-bold rounded-xl shadow-xl shadow-indigo-600/25 transition-all duration-200">
                            تسجيل الدخول إلى النظام
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

</body>
</html>