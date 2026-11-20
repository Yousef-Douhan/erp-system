{{-- نوافذ الإيداع والسحب والتحويل (مشتركة بين صفحة الخزائن وكشف الخزينة) --}}
{{-- المتغيرات: $transferTargets = الخزائن النشطة الممكن التحويل لها --}}
<style>
    .cr-dialog { position: fixed; inset: 0; margin: auto; height: fit-content; max-height: calc(100vh - 32px); overflow: auto; border: 0; border-radius: var(--radius); padding: 0; width: min(440px, calc(100vw - 32px)); box-shadow: 0 20px 50px rgba(16, 42, 43, .25); }
    .cr-dialog::backdrop { background: rgba(16, 42, 43, .45); }
    .cr-dialog form { display: grid; gap: 14px; padding: 20px; }
    .cr-dialog h3 { margin: 0; font-size: 17px; }
    .cr-dialog .cr-sub { margin: -8px 0 0; color: var(--muted); font-size: 13.5px; }
    .cr-dialog .x-actions { justify-content: flex-start; }
</style>

<dialog class="cr-dialog" id="crMoneyDialog">
    <form method="POST" id="crMoneyForm">
        @csrf
        <h3 id="crMoneyTitle"></h3>
        <p class="cr-sub" id="crMoneySub"></p>
        <div class="x-field">
            <label for="crAmount">المبلغ (₪)</label>
            <input type="number" name="amount" id="crAmount" step="0.01" min="0.01" required inputmode="decimal">
        </div>
        <div class="x-field">
            <label for="crReason">السبب</label>
            <input type="text" name="reason" id="crReason" maxlength="255" required>
        </div>
        <div class="x-note" id="crMoneyNote"></div>
        <div class="x-actions">
            <button class="x-btn" id="crMoneySubmit">حفظ</button>
            <button type="button" class="x-btn is-ghost" data-close>إلغاء</button>
        </div>
    </form>
</dialog>

<dialog class="cr-dialog" id="crTransferDialog">
    <form method="POST" action="{{ route('cash-registers.transfer') }}">
        @csrf
        <h3>تحويل بين الخزائن</h3>
        <p class="cr-sub">النقد ينتقل من خزينة لأخرى داخل الصيدلية. لا يؤثر على الأرباح.</p>
        <input type="hidden" name="from_register_id" id="crFrom">
        <div class="x-field">
            <label>من</label>
            <input type="text" id="crFromName" disabled>
        </div>
        <div class="x-field">
            <label for="crTo">إلى</label>
            <select name="to_register_id" id="crTo" required>
                @foreach ($transferTargets as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}{{ $t->branch?->name ? ' — ' . $t->branch->name : '' }}</option>
                @endforeach
            </select>
        </div>
        <div class="x-field">
            <label for="crTAmount">المبلغ (₪)</label>
            <input type="number" name="amount" id="crTAmount" step="0.01" min="0.01" required inputmode="decimal">
        </div>
        <div class="x-field">
            <label for="crNote">ملاحظة (اختياري)</label>
            <input type="text" name="note" id="crNote" maxlength="255" placeholder="مثال: تسليم إيراد اليوم للمدير">
        </div>
        <div class="x-actions">
            <button class="x-btn">تحويل</button>
            <button type="button" class="x-btn is-ghost" data-close>إلغاء</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    const money = n => Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const md = document.getElementById('crMoneyDialog'), td = document.getElementById('crTransferDialog');
    const texts = {
        deposit:  { title: 'إيداع في', btn: 'إيداع', hint: 'مثال: فكة من المالك، أو مبلغ أُعيد للصندوق', note: 'الإيداع يُسجَّل على «جاري المالك» ولا يُحسب كمبيعات أو أرباح.' },
        withdraw: { title: 'سحب من', btn: 'سحب', hint: 'مثال: المالك أخذ مبلغاً، أو إيداع في البنك', note: 'للمصاريف (كهرباء، رواتب...) استعمل «المصروفات»، ولدفع الموردين استعمل فاتورة الشراء، حتى تظهر في التقارير.' },
    };

    document.querySelectorAll('[data-cash]').forEach(btn => btn.addEventListener('click', () => {
        const kind = btn.dataset.cash, d = btn.dataset;
        if (kind === 'transfer') {
            document.getElementById('crFrom').value = d.id;
            document.getElementById('crFromName').value = d.name + ' (رصيدها ' + money(d.balance) + ' ₪)';
            const to = document.getElementById('crTo');
            [...to.options].forEach(o => { o.hidden = o.value === d.id; });
            const first = [...to.options].find(o => !o.hidden);
            if (!first) { alert('لا توجد خزينة أخرى نشطة للتحويل إليها.'); return; }
            to.value = first.value;
            document.getElementById('crTAmount').max = d.balance;
            td.showModal();
            return;
        }
        const t = texts[kind];
        document.getElementById('crMoneyForm').action = d.url;
        document.getElementById('crMoneyTitle').textContent = t.title + ' «' + d.name + '»';
        document.getElementById('crMoneySub').textContent = 'الرصيد الحالي ' + money(d.balance) + ' ₪';
        document.getElementById('crMoneySubmit').textContent = t.btn;
        document.getElementById('crReason').placeholder = t.hint;
        document.getElementById('crMoneyNote').textContent = t.note;
        const amount = document.getElementById('crAmount');
        amount.value = ''; amount.max = kind === 'withdraw' ? d.balance : '';
        document.getElementById('crReason').value = '';
        md.showModal();
        amount.focus();
    }));

    document.querySelectorAll('.cr-dialog [data-close]').forEach(b => b.addEventListener('click', () => b.closest('dialog').close()));
})();
</script>
