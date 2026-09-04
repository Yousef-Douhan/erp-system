<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>استعادة كلمة المرور - نظام الإدارة الشامل ERP</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 font-sans antialiased m-0 p-0 overflow-x-hidden">

    <div class="min-h-screen w-screen flex flex-col lg:flex-row m-0 p-0">
        
        <!-- الجانب الأيمن (الترحيبي) -->
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
                    استعادة الحساب الآمنة
                </span>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white leading-tight tracking-tight">
                    لا تقلق، <br>يمكننا مساعدتك في استعادة حسابك.
                </h1>
                <p class="text-slate-400 text-sm leading-relaxed max-w-md">
                    أدخل بريدك الإلكتروني المسجل وسنرسل لك رابطاً مباشراً لتعيين كلمة مرور جديدة بكل سهولة وأمان.
                </p>
            </div>

            <!-- حقوق النشر -->
            <div class="relative z-10 text-xs text-slate-500">
                جميع الحقوق محفوظة &copy; {{ date('Y') }} - يوسف دوحان
            </div>
        </div>

        <!-- الجانب الأيسر (نموذج استعادة كلمة المرور) -->
        <div class="lg:w-1/2 flex items-center justify-center p-6 sm:p-12 lg:p-16 bg-slate-950">
            <div class="w-full max-w-md space-y-8 bg-slate-900/50 p-8 sm:p-10 rounded-3xl border border-slate-800/80 backdrop-blur-2xl shadow-2xl">
                
                <div class="space-y-2">
                    <h2 class="text-2xl font-black text-white tracking-tight">نسيت كلمة المرور؟ 🔑</h2>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        لا مشكلة. فقط أخبرنا ببريدك الإلكتروني وسنرسل لك رابط إعادة تعيين كلمة المرور لتتمكن من اختيار كلمة مرور جديدة.
                    </p>
                </div>

                <!-- Session Status -->
                <x-auth-session-status class="text-xs" :status="session('status')" />

                <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                    @csrf

                    <!-- Email Address -->
                    <div class="space-y-1.5">
                        <label for="email" class="block text-xs font-bold text-slate-300">البريد الإلكتروني</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="name@example.com"
                            class="w-full bg-slate-900/90 border border-slate-800 rounded-xl px-4 py-3.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all shadow-inner" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1 text-xs" />
                    </div>

                    <!-- Submit Button & Back to Login -->
                    <div class="space-y-3 pt-2">
                        <button type="submit" class="w-full py-4 px-4 bg-indigo-600 hover:bg-indigo-500 active:scale-[0.99] text-white text-xs font-bold rounded-xl shadow-xl shadow-indigo-600/25 transition-all duration-200">
                            إرسال رابط استعادة كلمة المرور
                        </button>
                        
                        <div class="text-center pt-2">
                            <a href="{{ route('login') }}" class="text-xs text-slate-400 hover:text-indigo-400 font-semibold transition-colors">
                                &larr; العودة إلى صفحة تسجيل الدخول
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>

</body>
</html>