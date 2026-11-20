@extends('layouts.app')

@section('content')
@php
    $today = today();
    $productsData = $products->map(function ($p) use ($today) {
        $expiredBatchQty = (int) ($p->expired_batch_qty ?? 0);
        $sellable = max(0, (int) $p->stock_quantity - $expiredBatchQty);
        $productExpiry = $p->expiry_date ? \Illuminate\Support\Carbon::parse($p->expiry_date) : null;
        $hasBatches = ($p->batches_count ?? 0) > 0;

        // الأقرب انتهاءً: من الدفعات إن وُجدت، وإلا من تاريخ المنتج
        $expiry = $p->nearest_batch_expiry
            ? \Illuminate\Support\Carbon::parse($p->nearest_batch_expiry)
            : ($p->track_expiry ? $productExpiry : null);

        $expired = (!$hasBatches && $p->track_expiry && $productExpiry && $productExpiry->lt($today))
            || ($hasBatches && $sellable <= 0 && $expiredBatchQty > 0);
        $soon = !$expired && $expiry && $expiry->gte($today) && $expiry->lte($today->copy()->addDays(30));

        $baseName = $p->unit->name ?? 'حبة';
        $units = collect([[
            'id'       => 0,
            'name'     => $baseName,
            'factor'   => 1,
            'price'    => (float) $p->selling_price,
            'default'  => false,
            'barcodes' => array_values(array_filter([(string) ($p->barcode ?? '')])),
        ]])->concat($p->productUnits->map(fn ($u) => [
            'id'       => $u->id,
            'name'     => $u->unit->name ?? 'وحدة',
            'factor'   => max(0.0001, (float) $u->conversion_factor),
            'price'    => (float) $u->selling_price > 0 ? (float) $u->selling_price : (float) $p->selling_price * (float) $u->conversion_factor,
            'default'  => (bool) $u->is_default,
            'barcodes' => $u->barcodes->pluck('barcode')->prepend($u->barcode)->filter()->map(fn ($b) => (string) $b)->values()->all(),
        ]))->values();

        return [
            'id'         => $p->id,
            'name'       => $p->name,
            'sku'        => (string) ($p->sku ?? ''),
            'stock'      => $sellable,
            'alert'      => (int) $p->alert_quantity,
            'category'   => $p->category_id,
            'controlled' => (bool) $p->is_controlled,
            'expired'    => $expired,
            'soon'       => $soon,
            'expiry'     => $expiry?->format('Y-m-d'),
            'units'      => $units,
            'alerts'     => $p->drugAlerts->map(fn ($a) => ['severity' => $a->severity, 'message' => $a->message])->values(),
        ];
    })->values();

    $customersData = $customers->map(fn ($c) => [
        'id'            => $c->id,
        'name'          => $c->name,
        'phone'         => $c->phone,
        'balance'       => (float) $c->balance,
        'limit'         => (float) $c->credit_limit,
        'prescriptions' => $c->prescriptions->map(fn ($r) => [
            'id'        => $r->id,
            'doctor'    => $r->doctor_name,
            'diagnosis' => $r->diagnosis,
            'date'      => $r->created_at?->format('Y-m-d'),
        ])->values(),
    ])->values();

    $methodsData = $paymentMethods->map(fn ($m) => [
        'code'   => $m->code,
        'name'   => $m->name,
        'icon'   => $m->icon,
        'wallet' => (bool) $m->requires_wallet,
        'kind'   => $m->kind ?: ($m->requires_wallet ? 'wallet' : 'cash'),   // cash | bank | wallet
    ])->values();

    if ($methodsData->isEmpty()) {
        $methodsData = collect([['code' => 'cash', 'name' => 'نقدي', 'icon' => '💵', 'wallet' => false]]);
    }

    $registersData = $registers->map(fn ($r) => [
        'id'      => $r->id,
        'name'    => $r->name,
        'balance' => (float) $r->current_balance,
    ])->values();
@endphp



<style>
    .pos {
        --ink: #102A2B;
        --teal: #0F5C5A;
        --teal-strong: #0B4746;
        --teal-soft: #E5F1EF;
        --paper: #FFFFFF;
        --canvas: #EEF2F1;
        --line: #D8E1DF;
        --muted: #5C6E6C;
        --amber: #9A5A06;
        --amber-soft: #FCF1DF;
        --red: #B42318;
        --red-soft: #FCEBE9;
        --radius-lg: 14px;
        --radius-sm: 8px;

        font-family: 'IBM Plex Sans Arabic', 'Figtree', system-ui, sans-serif;
        color: var(--ink);
        display: grid;
        grid-template-columns: minmax(0, 1fr) 410px;
        gap: 16px;
        height: calc(100vh - 64px - 48px);
        min-height: 520px;
    }
    .pos *, .pos *::before, .pos *::after { box-sizing: border-box; }
    .pos button { font-family: inherit; cursor: pointer; }
    .pos .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
    .pos button:disabled { cursor: not-allowed; }
    .pos :focus-visible { outline: 3px solid #5FB3AE; outline-offset: 2px; }
    .pos .num { font-variant-numeric: tabular-nums; direction: ltr; unicode-bidi: isolate; }

    /* ---------- الكتالوج ---------- */
    .pos-catalog {
        display: flex; flex-direction: column; min-height: 0;
        background: var(--paper); border: 1px solid var(--line); border-radius: var(--radius-lg);
        overflow: hidden;
    }
    .pos-toolbar { display: flex; gap: 10px; align-items: center; padding: 14px; border-bottom: 1px solid var(--line); }
    .pos-search { position: relative; flex: 1; }
    .pos-search input {
        width: 100%; height: 48px; padding: 0 44px 0 92px;
        border: 1.5px solid var(--line); border-radius: 10px; background: var(--canvas);
        font: inherit; font-size: 15px; color: var(--ink);
    }
    .pos-search input:focus { outline: none; border-color: var(--teal); background: var(--paper); box-shadow: 0 0 0 4px var(--teal-soft); }
    .pos-search svg { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; color: var(--muted); }
    .pos-kbd {
        position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
        font-size: 11px; color: var(--muted); background: var(--paper); border: 1px solid var(--line);
        border-radius: 6px; padding: 3px 7px; white-space: nowrap;
    }
    .pos-tool {
        height: 48px; padding: 0 14px; display: inline-flex; align-items: center; gap: 8px;
        border: 1.5px solid var(--line); border-radius: 10px; background: var(--paper);
        color: var(--ink); font-size: 13px; font-weight: 600; text-decoration: none; white-space: nowrap;
    }
    .pos-tool:hover { border-color: var(--teal); color: var(--teal); }
    .pos-tool svg { width: 18px; height: 18px; }
    .pos-tool.is-debt { color: var(--amber); }
    .pos-tool.is-debt:hover { border-color: var(--amber); background: var(--amber-soft); }

    .pos-cats { display: flex; gap: 8px; padding: 10px 14px; overflow-x: auto; border-bottom: 1px solid var(--line); scrollbar-width: thin; }
    .pos-cats::-webkit-scrollbar { height: 6px; }
    .pos-cat {
        flex: none; height: 34px; padding: 0 14px; border-radius: 999px;
        border: 1px solid var(--line); background: var(--paper); color: var(--muted);
        font-size: 13px; font-weight: 500;
    }
    .pos-cat:hover { color: var(--ink); border-color: #B9C7C4; }
    .pos-cat[aria-pressed="true"] { background: var(--ink); border-color: var(--ink); color: #fff; }
    .pos-cat small { opacity: .65; margin-inline-start: 4px; }

    .pos-grid {
        flex: 1; min-height: 0; overflow-y: auto; padding: 14px;
        display: grid; grid-template-columns: repeat(auto-fill, minmax(176px, 1fr)); grid-auto-rows: max-content; gap: 10px; align-content: start;
        background: var(--canvas);
    }
    .pos-product {
        position: relative; text-align: right; padding: 12px; min-height: 112px; width: 100%;
        display: flex; flex-direction: column; justify-content: flex-start; gap: 8px; align-items: stretch;
        background: var(--paper); border: 1.5px solid transparent; border-radius: 12px;
        box-shadow: 0 1px 0 var(--line);
        transition: border-color .12s ease, transform .12s ease;
    }
    .pos-product > * { flex: none; max-width: 100%; }
    .pos-product .pos-product-foot { margin-top: auto; }
    .pos-product:hover:not(:disabled) { border-color: var(--teal); }
    .pos-product:active:not(:disabled) { transform: scale(.98); }
    .pos-product:disabled { opacity: .55; background: #F7F8F8; }
    .pos-product-name { font-size: 14px; font-weight: 600; line-height: 1.45; max-height: calc(1.45em * 2); overflow: hidden; padding-left: 26px; }
    .pos-product-foot { display: flex; align-items: flex-end; justify-content: space-between; gap: 6px; }
    .pos-product-price { font-size: 17px; font-weight: 700; color: var(--teal-strong); }
    .pos-product-price span { font-size: 12px; font-weight: 500; color: var(--muted); }
    .pos-stock { font-size: 11.5px; font-weight: 600; padding: 2px 8px; border-radius: 6px; background: var(--teal-soft); color: var(--teal-strong); white-space: nowrap; }
    .pos-stock.low { background: var(--amber-soft); color: var(--amber); }
    .pos-stock.out { background: var(--red-soft); color: var(--red); }
    .pos-in-cart {
        position: absolute; top: 8px; left: 8px; min-width: 24px; height: 24px; padding: 0 6px;
        border-radius: 999px; background: var(--teal); color: #fff; font-size: 12px; font-weight: 700;
        display: none; align-items: center; justify-content: center;
    }
    .pos-product.has-qty .pos-in-cart { display: inline-flex; }
    .pos-flag { font-size: 11px; font-weight: 600; color: var(--amber); }
    .pos-flag.expired { color: var(--red); }
    .pos-empty-grid { grid-column: 1 / -1; text-align: center; padding: 60px 20px; color: var(--muted); font-size: 14px; }

    /* ---------- الفاتورة (التذكرة) ---------- */
    .pos-ticket {
        display: flex; flex-direction: column; min-height: 0; overflow: hidden;
        background: var(--paper); border: 1px solid var(--line); border-radius: var(--radius-lg);
        overflow: hidden;
    }
    .pos-ticket-head { padding: 12px 16px 10px; border-bottom: 1px dashed var(--line); flex: none; }
    .pos-ticket-title { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 10px; }
    .pos-ticket-title h2 { font-size: 16px; font-weight: 700; margin: 0; }
    .pos-ticket-title span { font-size: 12px; color: var(--muted); }
    .pos-ticket-meta { display: inline-flex; gap: 10px; }
    .pos-customer-row { display: flex; gap: 8px; }
    .pos-customer-row > div { flex: 1; min-width: 0; }
    .pos-icon-btn {
        width: 40px; height: 40px; flex: none; border-radius: 10px; border: 1.5px solid var(--line);
        background: var(--paper); color: var(--teal); display: inline-flex; align-items: center; justify-content: center;
    }
    .pos-icon-btn:hover { border-color: var(--teal); background: var(--teal-soft); }
    .pos-icon-btn svg { width: 18px; height: 18px; }
    .pos-customer-info { margin-top: 8px; font-size: 12.5px; color: var(--muted); display: flex; gap: 12px; flex-wrap: wrap; }
    .pos-customer-info b { color: var(--amber); font-weight: 700; }
    .pos-rx { margin-top: 8px; padding: 8px 10px; border-radius: 10px; background: var(--amber-soft); }
    .pos-rx[hidden] { display: none; }
    .pos-rx label { display: block; font-size: 12px; font-weight: 600; color: var(--amber); margin-bottom: 4px; }
    .pos-rx select, .pos-register select, .pos-line-unit select {
        width: 100%; height: 34px; border: 1.5px solid var(--line); border-radius: 8px; background: var(--paper);
        font: inherit; font-size: 13px; color: var(--ink); padding: 0 8px;
    }
    .pos-register select { height: 38px; }
    .pos-tag { display: inline-block; font-size: 11px; font-weight: 600; padding: 1px 6px; border-radius: 5px; margin-inline-start: 4px; vertical-align: middle; }
    .pos-tag.rx { background: var(--amber-soft); color: var(--amber); }
    .pos-tag.alert-low, .pos-tag.alert-medium { background: var(--amber-soft); color: var(--amber); }
    .pos-tag.alert-high, .pos-tag.alert-critical { background: var(--red-soft); color: var(--red); }
    .pos-line-alert { font-size: 11.5px; line-height: 1.4; margin-top: 2px; color: var(--amber); }
    .pos-line-alert.is-high { color: var(--red); }
    .pos .ts-wrapper.single .ts-control {
        min-height: 40px; border-radius: 10px; border: 1.5px solid var(--line); font-family: inherit; font-size: 13.5px;
        box-shadow: none; padding: 9px 12px 9px 34px !important; background-position: left 12px center !important; text-align: right;
    }
    .pos .ts-wrapper.single .ts-control > .item { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
    .pos .ts-wrapper.single.has-items .ts-control > input { position: absolute; opacity: 0; }
    .pos .ts-wrapper.single.has-items.focus .ts-control > input { position: static; opacity: 1; }
    .pos .ts-wrapper.single.focus .ts-control { border-color: var(--teal); box-shadow: 0 0 0 4px var(--teal-soft); }
    .pos .ts-dropdown { font-family: inherit; font-size: 13.5px; border-radius: 10px; border-color: var(--line); }
    .pos .ts-dropdown .active { background: var(--teal-soft); color: var(--ink); }

    .pos-ticket-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; }
    .pos-lines { flex: 1 0 auto; min-height: 90px; }
    .pos-ticket-foot { flex: none; border-top: 1px solid var(--line); padding-top: 10px; background: var(--paper); }
    .pos-line { display: grid; grid-template-columns: minmax(0, 1fr) auto 64px 28px; align-items: start; gap: 8px; padding: 8px 12px 8px 16px; border-bottom: 1px solid #EEF2F1; }
    .pos-line.flash { animation: pos-flash .6s ease; }
    @keyframes pos-flash { from { background: var(--teal-soft); } to { background: transparent; } }
    .pos-line-name { font-size: 13.5px; font-weight: 600; line-height: 1.35; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pos-line-unit { font-size: 12px; color: var(--muted); }
    .pos-line-total { font-size: 14.5px; font-weight: 700; text-align: left; padding-top: 5px; }
    .pos-stepper { display: inline-flex; align-items: center; border: 1.5px solid var(--line); border-radius: 9px; overflow: hidden; }
    .pos-stepper button { width: 28px; height: 30px; border: 0; background: var(--canvas); color: var(--ink); font-size: 16px; font-weight: 600; }
    .pos-stepper button:hover:not(:disabled) { background: var(--teal-soft); color: var(--teal); }
    .pos-stepper button:disabled { color: #B8C4C2; }
    .pos-stepper input { width: 40px; height: 30px; border: 0; text-align: center; font: inherit; font-size: 14px; font-weight: 600; background: var(--paper); -moz-appearance: textfield; }
    .pos-stepper input::-webkit-inner-spin-button { -webkit-appearance: none; }
    .pos-remove { width: 28px; height: 28px; border: 0; background: transparent; color: var(--muted); font-size: 18px; line-height: 1; border-radius: 6px; }
    .pos-remove:hover { color: var(--red); background: var(--red-soft); }
    .pos-empty-cart { height: 100%; min-height: 160px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; padding: 24px; text-align: center; color: var(--muted); font-size: 13.5px; }
    .pos-empty-cart strong { color: var(--ink); font-size: 14.5px; }

    .pos-summary { padding: 6px 16px 8px; border-top: 1px dashed var(--line); font-size: 13.5px; flex: none; }
    .pos-row { display: flex; align-items: center; justify-content: space-between; padding: 3px 0; color: var(--muted); }
    .pos-row .num { color: var(--ink); font-weight: 600; }
    .pos-row input { width: 110px; height: 30px; border: 1.5px solid var(--line); border-radius: 8px; text-align: center; font: inherit; font-weight: 600; }
    .pos-row input:focus { outline: none; border-color: var(--teal); }

    .pos-total {
        margin: 0 12px; padding: 12px 16px; border-radius: 12px; background: var(--ink); color: #fff;
        display: flex; align-items: center; justify-content: space-between; flex: none;
    }
    .pos-total-label { font-size: 13px; opacity: .8; }
    .pos-total-label small { display: block; font-size: 11.5px; opacity: .75; margin-top: 2px; }
    .pos-total-value { font-size: 32px; font-weight: 700; letter-spacing: -.5px; line-height: 1; }
    .pos-total-value span { font-size: 16px; font-weight: 500; opacity: .7; margin-inline-start: 4px; }

    .pos-pay { padding: 10px 16px 12px; display: flex; flex-direction: column; gap: 7px; }
    .pos-methods { display: flex; flex-wrap: wrap; gap: 6px; }
    .pos-method {
        flex: 1 1 auto; min-width: 72px; height: 34px; padding: 0 8px; white-space: nowrap; border-radius: 9px; border: 1.5px solid var(--line);
        background: var(--paper); color: var(--ink); font-size: 13px; font-weight: 600;
    }
    .pos-method:hover { border-color: var(--teal); }
    .pos-method[aria-pressed="true"] { border-color: var(--teal); background: var(--teal-soft); color: var(--teal-strong); }
    .pos-field-label { font-size: 12.5px; color: var(--muted); margin-bottom: 4px; display: block; }
    .pos-inline { display: flex; align-items: center; gap: 10px; }
    .pos-inline[hidden] { display: none; }
    .pos-inline label { flex: none; width: 92px; font-size: 12.5px; color: var(--muted); }
    .pos-paid input, .pos-wallet input {
        flex: 1; min-width: 0; height: 40px; border: 1.5px solid var(--line); border-radius: 10px; padding: 0 12px;
        font: inherit; font-size: 18px; font-weight: 700; text-align: center;
    }
    .pos-wallet input { font-size: 15px; font-weight: 500; }
    .pos-wallet-group { display: grid; gap: 8px; padding: 10px; border: 1px dashed var(--teal-line, #BFDCD8); border-radius: 12px; background: #F7FBFA; }
    .pos-wallet-group[hidden] { display: none; }
    .pos-wallet-group .pos-wallet-title { font-size: 12.5px; font-weight: 700; color: var(--teal); }
    .pos-wallet-group input { height: 36px; text-align: right; }
    .pos-wallet-group input.num { direction: ltr; text-align: right; }
    .pos-wallet-group .opt { font-weight: 400; color: var(--muted); font-size: 11.5px; }
    .pos-paid input:focus, .pos-wallet input:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 0 4px var(--teal-soft); }
    .pos-quick { display: flex; gap: 6px; }
    .pos-quick [hidden] { display: none; }
    .pos-quick button { flex: 1; height: 30px; white-space: nowrap; border-radius: 8px; border: 1px solid var(--line); background: var(--canvas); font-size: 12.5px; font-weight: 600; color: var(--ink); }
    .pos-quick button:hover { border-color: var(--teal); color: var(--teal); }
    .pos-balance { display: flex; justify-content: space-between; font-size: 14px; padding: 7px 12px; border-radius: 9px; background: var(--canvas); }
    .pos-balance.is-change { background: var(--teal-soft); color: var(--teal-strong); }
    .pos-balance.is-due { background: var(--amber-soft); color: var(--amber); }
    .pos-balance .num { font-weight: 700; }
    .pos-hint { font-size: 12.5px; line-height: 1.5; padding: 8px 10px; border-radius: 8px; background: var(--amber-soft); color: var(--amber); }
    .pos-hint.is-error { background: var(--red-soft); color: var(--red); }

    .pos-actions { display: grid; grid-template-columns: 1fr auto auto; gap: 6px; padding: 10px 12px 12px; }
    .pos-ticket-hint { margin: 0 12px 8px; }
    .pos-ticket-hint[hidden], .pos-hint[hidden] { display: none; }

    .pos-sheet { position: fixed; inset: 0; z-index: 80; display: none; align-items: center; justify-content: center; padding: 16px; background: rgba(16,42,43,.45); }
    .pos-sheet.open { display: flex; }
    .pos-sheet-card { width: 100%; max-width: 480px; max-height: calc(100vh - 32px); overflow-y: auto; background: var(--paper); border-radius: 16px; padding: 16px 0 4px; box-shadow: 0 24px 60px rgba(16,42,43,.3); }
    .pos-sheet-head { display: flex; align-items: center; justify-content: space-between; padding: 0 16px 12px; }
    .pos-sheet-head h3 { margin: 0; font-size: 17px; font-weight: 700; }
    .pos-sheet-close { height: 34px; padding: 0 12px; border-radius: 9px; border: 1.5px solid var(--line); background: var(--paper); font-size: 13px; font-weight: 600; color: var(--muted); }
    .pos-sheet-close:hover { color: var(--ink); border-color: #B9C7C4; }
    .pos-sheet .pos-pay { padding: 14px 16px 16px; gap: 10px; }
    .pos-sheet .pos-method { height: 42px; font-size: 14px; }
    .pos-sheet .pos-paid input { height: 48px; font-size: 22px; }
    .pos-sheet .pos-complete { height: 54px; font-size: 17px; }

    .pos-units { display: inline-flex; margin-top: 4px; border: 1.5px solid var(--line); border-radius: 8px; overflow: hidden; }
    .pos-units button { height: 26px; padding: 0 10px; border: 0; border-inline-start: 1px solid var(--line); background: var(--paper); font-size: 12px; font-weight: 600; color: var(--muted); }
    .pos-units button:first-child { border-inline-start: 0; }
    .pos-units button[aria-pressed="true"] { background: var(--teal); color: #fff; }
    .pos-units button:hover:not([aria-pressed="true"]) { background: var(--teal-soft); color: var(--teal-strong); }
    .pos-units-add { display: inline-block; margin-inline-start: 6px; font-size: 11.5px; color: var(--teal); text-decoration: none; }
    .pos-units-add:hover { text-decoration: underline; }
    .pos-product-units { display: flex; flex-wrap: nowrap; gap: 4px; overflow: hidden; }
    .pos-product-units span { flex: none; font-size: 11px; padding: 1px 6px; border-radius: 5px; background: var(--canvas); color: var(--muted); white-space: nowrap; }
    .pos-product-units span b { font-weight: 600; color: var(--ink); }
    .pos-complete {
        height: 48px; border: 0; border-radius: 12px; background: var(--teal); color: #fff;
        font-size: 16px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 10px;
    }
    .pos-complete:hover:not(:disabled) { background: var(--teal-strong); }
    .pos-complete:disabled { background: #A9BDBB; }
    .pos-complete kbd, .pos-secondary kbd { font-family: inherit; font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 5px; background: rgba(255,255,255,.18); }
    .pos-secondary-row { display: flex; gap: 8px; }
    .pos-secondary {
        height: 48px; padding: 0 12px; border-radius: 12px; border: 1.5px solid var(--line); background: var(--paper);
        font-size: 13px; font-weight: 600; color: var(--ink);
    }
    .pos-secondary.is-debt { color: var(--amber); }
    .pos-secondary.is-debt:hover:not(:disabled) { border-color: var(--amber); background: var(--amber-soft); }
    .pos-secondary.is-clear:hover:not(:disabled) { border-color: var(--red); color: var(--red); background: var(--red-soft); }
    .pos-secondary:disabled { opacity: .5; }
    .pos-secondary kbd { background: var(--canvas); color: var(--muted); }

    /* ---------- رسائل ونوافذ ---------- */
    .pos-banner { grid-column: 1 / -1; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border-radius: 12px; font-size: 14px; font-weight: 600; }
    .pos-banner.ok { background: var(--teal-soft); color: var(--teal-strong); border: 1px solid #BFDCD8; }
    .pos-banner.err { background: var(--red-soft); color: var(--red); border: 1px solid #F3C2BD; }
    .pos-banner ul { margin: 0; padding-inline-start: 18px; font-weight: 500; }
    .pos-banner a { color: inherit; text-decoration: underline; white-space: nowrap; }
    .pos.has-banner { grid-template-rows: auto minmax(0, 1fr); }

    .pos-toast {
        position: fixed; top: 24px; left: 50%; transform: translateX(-50%); z-index: 100;
        padding: 10px 18px; border-radius: 10px; font: 600 14px 'IBM Plex Sans Arabic', sans-serif;
        color: #fff; background: var(--ink, #102A2B); box-shadow: 0 8px 24px rgba(16,42,43,.25);
    }
    .pos-toast.err { background: #B42318; }

    .pos-modal { position: fixed; inset: 0; z-index: 90; display: none; align-items: center; justify-content: center; background: rgba(16,42,43,.45); padding: 16px; }
    .pos-modal.open { display: flex; }
    .pos-modal-card { width: 100%; max-width: 380px; background: #fff; border-radius: 14px; padding: 20px; font-family: 'IBM Plex Sans Arabic', sans-serif; color: #102A2B; }
    .pos-modal-card h3 { margin: 0 0 14px; font-size: 16px; }
    .pos-modal-card label { display: block; font-size: 13px; color: #5C6E6C; margin-bottom: 4px; }
    .pos-modal-card input { width: 100%; height: 42px; border: 1.5px solid #D8E1DF; border-radius: 10px; padding: 0 12px; font: inherit; margin-bottom: 12px; }
    .pos-modal-card input:focus { outline: none; border-color: #0F5C5A; }
    .pos-modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 4px; }
    .pos-modal-actions button { height: 40px; padding: 0 18px; border-radius: 10px; font: 600 14px 'IBM Plex Sans Arabic', sans-serif; border: 1.5px solid #D8E1DF; background: #fff; cursor: pointer; }
    .pos-modal-actions button[type="submit"] { background: #0F5C5A; border-color: #0F5C5A; color: #fff; }

    /* ---------- البيع بدون إنترنت ---------- */
    .pos-net { cursor: pointer; font-family: inherit; }
    .pos-net .pos-net-dot { width: 9px; height: 9px; border-radius: 50%; background: #1F8A5B; box-shadow: 0 0 0 3px #E3F4EC; }
    .pos-net[data-tone="off"] { border-color: #E9B4AE; color: #B42318; background: #FCEBE9; }
    .pos-net[data-tone="off"] .pos-net-dot { background: #B42318; box-shadow: 0 0 0 3px #F8D3CF; }
    .pos-net[data-tone="bad"] { border-color: #E9B4AE; color: #B42318; }
    .pos-net[data-tone="bad"] .pos-net-dot { background: #B42318; box-shadow: 0 0 0 3px #F8D3CF; }
    .pos-net[data-tone="wait"] .pos-net-dot { background: #9A5A06; box-shadow: 0 0 0 3px #FCF1DF; }
    .pos-net-count { min-width: 22px; height: 22px; padding: 0 6px; border-radius: 11px; background: #9A5A06; color: #fff; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; }
    .pos-net[data-tone="off"] .pos-net-count, .pos-net[data-tone="bad"] .pos-net-count { background: #B42318; }
    .pos-net span { display: inline !important; }

    .pos-queue { position: fixed; inset: 0; z-index: 95; display: none; align-items: flex-start; justify-content: center; background: rgba(16,42,43,.45); padding: 40px 16px; overflow-y: auto; }
    .pos-queue.open { display: flex; }
    .pos-queue-card { width: 100%; max-width: 640px; background: #fff; border-radius: 14px; padding: 18px; font-family: 'IBM Plex Sans Arabic', sans-serif; color: #102A2B; }
    .pos-queue-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
    .pos-queue-head h3 { margin: 0; font-size: 16px; }
    .pos-queue-close, .pos-queue-btn { height: 36px; padding: 0 14px; border-radius: 9px; font: 600 13px 'IBM Plex Sans Arabic', sans-serif; border: 1.5px solid #D8E1DF; background: #fff; color: #102A2B; cursor: pointer; white-space: nowrap; }
    .pos-queue-btn { background: #0F5C5A; border-color: #0F5C5A; color: #fff; }
    .pos-queue-btn:disabled { opacity: .5; cursor: default; }
    .pos-queue-btn.is-ghost { background: #fff; color: #0F5C5A; border-color: #D8E1DF; }
    .pos-queue-btn.is-danger { background: #fff; color: #B42318; border-color: #E9B4AE; }
    .pos-queue-btn.is-armed { background: #B42318; color: #fff; border-color: #B42318; }
    .pos-queue-status { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border-radius: 10px; background: #E3F4EC; color: #14643F; font-weight: 600; font-size: 14px; margin-bottom: 10px; }
    .pos-queue-status[data-tone="off"] { background: #FCEBE9; color: #B42318; }
    .pos-queue-msg { font-size: 13px; padding: 8px 12px; border-radius: 9px; background: #EEF2F1; margin-bottom: 8px; line-height: 1.6; }
    .pos-queue-msg.is-bad { background: #FCEBE9; color: #B42318; }
    .pos-queue-empty { padding: 28px 8px; text-align: center; color: #5C6E6C; }
    .pos-queue-list { display: grid; gap: 8px; }
    .pos-queue-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 6px 12px; padding: 10px 12px; border: 1px solid #D8E1DF; border-radius: 10px; font-size: 14px; }
    .pos-queue-sub { font-size: 12.5px; color: #5C6E6C; margin-top: 3px; }
    .pos-queue-sub.is-bad { color: #B42318; } .pos-queue-sub.is-warn { color: #9A5A06; }
    .pos-queue-tag { align-self: start; font-size: 12px; font-weight: 700; padding: 3px 9px; border-radius: 7px; background: #FCF1DF; color: #9A5A06; white-space: nowrap; }
    .pos-queue-tag[data-tone="ok"] { background: #E3F4EC; color: #14643F; }
    .pos-queue-tag[data-tone="bad"] { background: #FCEBE9; color: #B42318; }
    .pos-queue-actions { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 6px; }
    .pos-offline-note { display: none; }
    .pos.is-offline .pos-offline-note { display: block; font-size: 12.5px; font-weight: 600; color: #B42318; background: #FCEBE9; border-radius: 9px; padding: 7px 10px; margin-top: 8px; }

    @media (max-width: 1100px) {
        .pos { grid-template-columns: 1fr; height: auto; }
        .pos-catalog { height: 70vh; }
        .pos-ticket { min-height: 80vh; }
    }
    @media (max-width: 640px) {
        .pos-toolbar { flex-wrap: wrap; }
        .pos-tool span { display: none; }
        .pos-kbd { display: none; }
        .pos-search input { padding-left: 14px; }
    }
    @media (max-height: 700px) and (min-width: 1101px) {
        .pos-ticket { overflow-y: auto; }
        .pos-ticket-body { flex: none; overflow: visible; }
    }
    @media (prefers-reduced-motion: reduce) {
        .pos-line.flash { animation: none; }
        .pos-product { transition: none; }
    }
</style>

<div class="pos {{ (session('success') || $errors->any() || !empty($shiftBlocked)) ? 'has-banner' : '' }}" id="pos" @if (session('success') || $errors->any()) data-pos-flash="1" @endif>

    @if (!empty($shiftBlocked) && !$errors->any())
        <div class="pos-banner err" role="alert">
            <span>لا يمكن البيع قبل فتح وردية على خزينة. هذا إعداد الصيدلية.</span>
            @if (Route::has('shifts.index'))
                <a href="{{ route('shifts.index') }}">فتح وردية الآن</a>
            @endif
        </div>
    @endif

    @if (session('success'))
        <div class="pos-banner ok" role="status">
            <span>{{ session('success') }}</span>
            @if (session('last_sale_id') && Route::has('sales.show'))
                <a href="{{ route('sales.show', session('last_sale_id')) }}">عرض الفاتورة وطباعتها</a>
            @endif
        </div>
    @endif

    @if ($errors->any())
        <div class="pos-banner err" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ================= الكتالوج ================= --}}
    <section class="pos-catalog" aria-label="المنتجات">
        <div class="pos-toolbar">
            <label class="pos-search">
                <span class="sr-only">ابحث عن منتج أو امسح الباركود</span>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                <input type="text" id="posSearch" autocomplete="off" placeholder="ابحث بالاسم أو امسح الباركود" autofocus>
                <span class="pos-kbd">F2</span>
            </label>
            <button type="button" class="pos-tool pos-net" id="posNet" data-tone="wait" aria-haspopup="dialog" title="حالة الاتصال والفواتير المحفوظة على الجهاز">
                <span class="pos-net-dot" aria-hidden="true"></span><span>جارٍ الفحص</span>
            </button>
            <a href="{{ route('debts.index') }}" class="pos-tool is-debt" title="دفتر الديون">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                <span>الديون</span>
            </a>
            <a href="{{ route('sales.index') }}" class="pos-tool" title="الفواتير السابقة">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>الفواتير</span>
            </a>
        </div>

        <div class="pos-cats" role="toolbar" aria-label="التصنيفات">
            <button type="button" class="pos-cat" data-cat="all" aria-pressed="true">الكل <small class="num">{{ $products->count() }}</small></button>
            @foreach ($categories as $category)
                @php $count = $products->where('category_id', $category->id)->count(); @endphp
                @if ($count > 0)
                    <button type="button" class="pos-cat" data-cat="{{ $category->id }}" aria-pressed="false">{{ $category->name }} <small class="num">{{ $count }}</small></button>
                @endif
            @endforeach
        </div>

        <div class="pos-grid" id="posGrid" aria-live="polite"></div>
    </section>

    {{-- ================= الفاتورة ================= --}}
    <form class="pos-ticket" id="posForm" action="{{ route('pos.store') }}" method="POST" aria-label="الفاتورة الحالية">
        @csrf
        <div id="posHidden"></div>
        <input type="hidden" name="payment_method" id="posMethodInput">
        <input type="hidden" name="discount" id="posDiscountInput" value="0">

        <div class="pos-ticket-head">
            <div class="pos-ticket-title">
                <h2>فاتورة جديدة</h2>
                <span class="pos-ticket-meta"><span>{{ auth()->user()->username }}</span><span class="num" id="posClock">{{ now()->format('H:i') }}</span></span>
            </div>
            <div class="pos-customer-row">
                <div>
                    <label for="posCustomer" class="sr-only">العميل</label>
                    <select name="customer_id" id="posCustomer">
                        <option value="">زبون نقدي</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' — ' . $customer->phone : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" class="pos-icon-btn" onclick="POS.openModal()" title="إضافة عميل جديد" aria-label="إضافة عميل جديد">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v6m3-3h-6M13 7a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                </button>
            </div>
            <div class="pos-customer-info" id="posCustomerInfo" hidden></div>

            <div class="pos-rx" id="posRx" hidden>
                <label for="posRxSelect">الوصفة الطبية (مطلوبة للأدوية الخاضعة للرقابة)</label>
                <select id="posRxSelect" name="prescription_id"></select>
            </div>
        </div>

        <div class="pos-ticket-body">
            <div class="pos-lines" id="posLines"></div>

            <div class="pos-summary">
                <div class="pos-row"><span>المجموع</span><span class="num" id="posSubtotal">0.00</span></div>
                <div class="pos-row">
                    <label for="posDiscount">خصم على الفاتورة</label>
                    <input type="number" id="posDiscount" class="num" min="0" step="0.01" value="0" inputmode="decimal">
                </div>
            </div>
        </div>

        <div class="pos-ticket-foot">
            <div class="pos-hint is-error pos-ticket-hint" id="posTicketHint" hidden></div>
            <div class="pos-total">
                <div class="pos-total-label">الإجمالي المطلوب<small id="posItemsCount">لا توجد أصناف</small></div>
                <div class="pos-total-value num" id="posTotal">0.00<span>₪</span></div>
            </div>
            <div class="pos-actions">
                <button type="button" class="pos-complete" id="posOpenPay" disabled>الدفع <kbd>F9</kbd></button>
                <button type="button" class="pos-secondary is-debt" id="posDebt" disabled title="تسجيل الفاتورة كاملة ديناً على العميل">بيع آجل</button>
                <button type="button" class="pos-secondary is-clear" id="posClear" disabled title="تفريغ الفاتورة (Esc)">تفريغ</button>
            </div>
        </div>

        {{-- نافذة الدفع --}}
        <div class="pos-sheet" id="posSheet" role="dialog" aria-modal="true" aria-labelledby="posSheetTitle">
            <div class="pos-sheet-card">
                <div class="pos-sheet-head">
                    <h3 id="posSheetTitle">الدفع</h3>
                    <button type="button" class="pos-sheet-close" id="posSheetClose" aria-label="رجوع للفاتورة">رجوع للفاتورة</button>
                </div>

                <div class="pos-total">
                    <div class="pos-total-label">المطلوب<small id="posSheetCustomer">زبون نقدي</small></div>
                    <div class="pos-total-value num" id="posSheetTotal">0.00<span>₪</span></div>
                </div>

                <div class="pos-pay">
                    <div class="pos-methods" role="group" aria-label="طريقة الدفع" id="posMethods"></div>

                    <div class="pos-inline pos-register" id="posRegister" hidden>
                        <label for="posRegisterSelect">الخزينة</label>
                        <select id="posRegisterSelect" name="cash_register_id"></select>
                    </div>

                    <div class="pos-wallet-group" id="posWallet" hidden>
                        <div class="pos-wallet-title" id="posWalletTitle">بيانات المحوِّل — للمطابقة مع كشف الحساب</div>
                        <div class="pos-inline pos-wallet">
                            <label for="posPayerName" id="posPayerNameLabel">اسم المحوِّل</label>
                            <input type="text" name="payer_name" id="posPayerName" maxlength="120" autocomplete="off" placeholder="كما يظهر في الحوالة">
                        </div>
                        <div class="pos-inline pos-wallet">
                            <label for="posWalletInput" id="posWalletLabel">رقم الجوال / المحفظة</label>
                            <input type="text" name="wallet_phone" id="posWalletInput" class="num" inputmode="tel" maxlength="30" autocomplete="off" placeholder="059xxxxxxx">
                        </div>
                        <div class="pos-inline pos-wallet">
                            <label for="posPayerAccount">رقم الحساب <span class="opt">(اختياري)</span></label>
                            <input type="text" name="payer_account" id="posPayerAccount" class="num" maxlength="60" autocomplete="off" placeholder="IBAN أو رقم الحساب">
                        </div>
                        <div class="pos-inline pos-wallet">
                            <label for="posTransferRef">رقم العملية <span class="opt">(اختياري)</span></label>
                            <input type="text" name="transfer_ref" id="posTransferRef" class="num" maxlength="60" autocomplete="off" placeholder="من رسالة التحويل">
                        </div>
                    </div>

                    <div class="pos-inline pos-paid">
                        <label for="posPaid">المبلغ المدفوع</label>
                        <input type="number" name="paid_amount" id="posPaid" class="num" min="0" step="0.01" value="0" inputmode="decimal">
                    </div>
                    <div class="pos-quick" id="posQuick">
                        <button type="button" data-quick="exact" title="F8">كامل</button>
                        <button type="button" data-quick="debt" id="posQuickDebt" hidden>+ سداد الدين</button>
                        <button type="button" data-quick="10" class="num">10</button>
                        <button type="button" data-quick="20" class="num">20</button>
                        <button type="button" data-quick="50" class="num">50</button>
                        <button type="button" data-quick="100" class="num">100</button>
                    </div>

                    <div class="pos-balance" id="posBalance"><span>الباقي للزبون</span><span class="num">0.00</span></div>
                    <div class="pos-hint" id="posHint" hidden></div>
                    <div class="pos-offline-note" role="status">لا يوجد إنترنت: الفاتورة ستُحفظ على الجهاز برقم مؤقت وتُرفع تلقائياً لما يرجع النت.</div>

                    <button type="submit" class="pos-complete" id="posComplete" disabled>
                        إتمام البيع <kbd>Enter</kbd>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- نافذة إضافة عميل --}}
<div class="pos-modal" id="posModal" role="dialog" aria-modal="true" aria-labelledby="posModalTitle">
    <form class="pos-modal-card" id="posCustomerForm" action="{{ route('pos.customer.store') }}" method="POST">
        @csrf
        <h3 id="posModalTitle">إضافة عميل جديد</h3>
        <label for="newCustomerName">اسم العميل</label>
        <input type="text" id="newCustomerName" name="name" required maxlength="255">
        <label for="newCustomerPhone">رقم الهاتف</label>
        <input type="text" id="newCustomerPhone" name="phone" class="num" inputmode="tel" maxlength="50">
        <div class="pos-modal-actions">
            <button type="button" onclick="POS.closeModal()">إلغاء</button>
            <button type="submit">حفظ العميل</button>
        </div>
    </form>
</div>

<script src="{{ asset('js/pos-offline.js') }}?v={{ @filemtime(public_path('js/pos-offline.js')) }}"></script>
<script>
const POS = (() => {
    const products = @json($productsData);
    const customers = @json($customersData);
    const methods = @json($methodsData);
    const registers = @json($registersData);
    const OFFLINE = {{ \Illuminate\Support\Js::from([
        'userId'      => auth()->id(),
        'userName'    => auth()->user()->name ?? auth()->user()->username,
        'branchId'    => $posBranchId ?? null,
        'storeName'   => auth()->user()->organization->name ?? config('app.name'),
        'renderedAt'  => now()->toIso8601String(),
        'pingUrl'     => route('pos.ping'),
        'syncUrl'     => route('pos.sync'),
        'pageUrl'     => route('pos.index'),
        'shiftBlocked'=> !empty($shiftBlocked),
    ]) }};

    // الفواتير المحفوظة على الجهاز ولم تصل السيرفر: تُطرح من المخزون وتُضاف لديون العملاء المعروضة
    if (window.PosOffline) {
        const d = PosOffline.deductions(OFFLINE.renderedAt);
        products.forEach(p => { if (d.stock[p.id]) p.stock = Math.max(0, p.stock - d.stock[p.id]); });
        customers.forEach(c => {
            if (d.debt[c.id]) c.balance = Math.round((c.balance + d.debt[c.id]) * 100) / 100;
            c.prescriptions = c.prescriptions.filter(r => !d.rx.has(String(r.id)));
        });
    }
    const productById = new Map(products.map(p => [String(p.id), p]));
    const customerById = new Map(customers.map(c => [String(c.id), c]));
    const STORAGE_KEY = 'pos_draft_v3';
    const justSold = @json((bool) session('success'));
    const UNITS_URL = @json(url('products'));
    const SEVERITY = { low: 'منخفض', medium: 'متوسط', high: 'مرتفع', critical: 'حرج' };

    // فهرس الباركودات: باركود المنتج أو باركود أي وحدة من وحداته
    const barcodeIndex = new Map();
    products.forEach(p => p.units.forEach(u => u.barcodes.forEach(b => {
        if (b) barcodeIndex.set(String(b).toLowerCase().trim(), { pid: String(p.id), uid: u.id });
    })));

    const state = {
        cart: [],            // [{pid, uid, qty}]
        customerId: '',
        prescriptionId: '',
        registerId: registers.length ? String(registers[0].id) : '',
        method: methods[0].code,
        discount: 0,
        paid: 0,
        paidTouched: false,
        category: 'all',
        query: '',
        lastAdded: null,
    };

    const $ = id => document.getElementById(id);
    const money = n => (Math.round((Number(n) || 0) * 100) / 100).toFixed(2);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    // توحيد النص للبحث: بدون تشكيل، والهمزات والتاء المربوطة والياء والأرقام العربية بشكل واحد
    const AR_DIGITS = '٠١٢٣٤٥٦٧٨٩', FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
    const norm = s => String(s ?? '')
        .toLowerCase()
        .replace(/[\u064B-\u065F\u0670\u0640]/g, '')   // التشكيل والتطويل
        .replace(/[إأآٱ]/g, 'ا')
        .replace(/ى/g, 'ي')
        .replace(/ئ/g, 'ي')
        .replace(/ؤ/g, 'و')
        .replace(/ة/g, 'ه')
        .replace(/[٠-٩]/g, d => AR_DIGITS.indexOf(d))
        .replace(/[۰-۹]/g, d => FA_DIGITS.indexOf(d))
        .replace(/\s+/g, ' ')
        .trim();
    // كل كلمات البحث يجب أن تظهر في النص (بأي ترتيب)
    const matchesWords = (q, text) => { const t = norm(text); return norm(q).split(' ').every(w => t.includes(w)); };
    const keyOf = (pid, uid) => `${pid}:${uid}`;
    const unitOf = (p, uid) => p.units.find(u => u.id === Number(uid)) || p.units[0];
    const defaultUnit = p => p.units.find(u => u.default) || p.units[0];
    const isCashMethod = m => m && (m.code === 'cash' || !m.wallet);

    // الكمية المستخدمة من المنتج بالوحدة الأساسية (كل الوحدات معاً)
    const baseUsed = (pid, exceptKey = null) => state.cart
        .filter(l => l.pid === pid && keyOf(l.pid, l.uid) !== exceptKey)
        .reduce((s, l) => s + l.qty * unitOf(productById.get(l.pid), l.uid).factor, 0);
    const maxQtyFor = (p, uid, exceptKey) => Math.floor((p.stock - baseUsed(String(p.id), exceptKey)) / unitOf(p, uid).factor + 1e-9);

    // ---------- حفظ المسودة حتى لا تضيع السلة عند تحديث الصفحة ----------
    function saveDraft() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                cart: state.cart, customerId: state.customerId, prescriptionId: state.prescriptionId,
                registerId: state.registerId, method: state.method, discount: state.discount,
            }));
        } catch (e) {}
    }
    function loadDraft() {
        if (justSold) { try { localStorage.removeItem(STORAGE_KEY); } catch (e) {} return; }
        try {
            const d = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
            if (!d) return;
            (d.cart || []).forEach(l => {
                const p = productById.get(String(l.pid));
                if (!p || p.expired || !p.units.some(u => u.id === Number(l.uid))) return;
                const qty = Math.min(Number(l.qty) || 0, maxQtyFor(p, l.uid, null));
                if (qty > 0) state.cart.push({ pid: String(l.pid), uid: Number(l.uid), qty });
            });
            state.customerId = customerById.has(String(d.customerId)) ? String(d.customerId) : '';
            const c = customerById.get(state.customerId);
            state.prescriptionId = c && c.prescriptions.some(r => String(r.id) === String(d.prescriptionId)) ? String(d.prescriptionId) : '';
            state.registerId = registers.some(r => String(r.id) === String(d.registerId)) ? String(d.registerId) : state.registerId;
            state.method = methods.some(m => m.code === d.method) ? d.method : methods[0].code;
            state.discount = Number(d.discount) || 0;
        } catch (e) {}
    }

    // ---------- الحسابات ----------
    function totals() {
        const subtotal = state.cart.reduce((s, l) => s + unitOf(productById.get(l.pid), l.uid).price * l.qty, 0);
        const discount = Math.min(Math.max(0, state.discount), subtotal);
        const total = Math.max(0, subtotal - discount);
        const customer = customerById.get(state.customerId) || null;
        const paid = Math.max(0, Number(state.paid) || 0);
        const due = Math.max(0, total - paid);
        const extra = Math.max(0, paid - total);
        const toOldDebt = customer ? Math.min(extra, customer.balance) : 0;
        const change = extra - toOldDebt;
        const items = state.cart.reduce((s, l) => s + l.qty, 0);
        const controlled = [...new Set(state.cart.map(l => productById.get(l.pid)).filter(p => p.controlled).map(p => p.name))];
        return { subtotal, discount, total, customer, paid, due, change, toOldDebt, items, controlled };
    }

    // ---------- الكتالوج ----------
    function renderGrid() {
        const q = norm(state.query);
        const list = products.filter(p =>
            (state.category === 'all' || String(p.category) === String(state.category)) &&
            (!q || matchesWords(q, p.name) || norm(p.sku).includes(q) || p.units.some(u => u.barcodes.some(b => norm(b).includes(q))))
        );

        if (!list.length) {
            $('posGrid').innerHTML = `<div class="pos-empty-grid">لا توجد منتجات مطابقة${q ? ` لـ «${esc(state.query)}»` : ''}. جرّب اسماً آخر أو امسح الباركود.</div>`;
            return;
        }

        $('posGrid').innerHTML = list.map(p => {
            const pid = String(p.id);
            const used = baseUsed(pid);
            const left = Math.max(0, p.stock - used);
            const inCartQty = state.cart.filter(l => l.pid === pid).reduce((s, l) => s + l.qty, 0);
            const blocked = p.stock <= 0 || p.expired;
            const stockClass = p.stock <= 0 ? 'out' : (p.stock <= p.alert ? 'low' : '');
            const stockText = p.expired ? 'منتهي' : (p.stock <= 0 ? 'نفد' : `متوفر ${left}`);
            const unit = defaultUnit(p);
            const topAlert = p.alerts.find(a => ['critical', 'high'].includes(a.severity)) || p.alerts[0];
            const flags = [
                p.expired ? `<span class="pos-flag expired">منتهي الصلاحية</span>`
                    : p.soon ? `<span class="pos-flag">ينتهي في <span class="num">${esc(p.expiry)}</span></span>` : '',
                p.controlled ? `<span class="pos-tag rx">يحتاج وصفة</span>` : '',
                topAlert ? `<span class="pos-tag alert-${esc(topAlert.severity)}">تنبيه ${esc(SEVERITY[topAlert.severity] || '')}</span>` : '',
            ].filter(Boolean).join(' ');
            return `
                <button type="button" class="pos-product ${inCartQty ? 'has-qty' : ''}" data-id="${pid}" ${blocked ? 'disabled' : ''}
                        title="${esc(p.name)}">
                    <span class="pos-in-cart num">${inCartQty}</span>
                    <span class="pos-product-name">${esc(p.name)}</span>
                    ${flags ? `<span>${flags}</span>` : ''}
                    ${p.units.length > 1 ? `<span class="pos-product-units" title="${esc(p.units.map(u => u.name + ' ' + money(u.price)).join('، '))}">${p.units.filter(u => u.id !== unit.id).map(u => `<span>${esc(u.name)} <b class="num">${money(u.price)}</b></span>`).join('')}</span>` : ''}
                    <span class="pos-product-foot">
                        <span class="pos-product-price num">${money(unit.price)}<span> ₪${p.units.length > 1 ? ' / ' + esc(unit.name) : ''}</span></span>
                        <span class="pos-stock ${stockClass}">${stockText}</span>
                    </span>
                </button>`;
        }).join('');
    }

    // ---------- السلة ----------
    function add(pid, uid = null) {
        const p = productById.get(String(pid));
        if (!p) return false;
        if (p.expired) { toast('هذا المنتج منتهي الصلاحية ولا يمكن بيعه.', true); return false; }
        const unit = uid === null ? defaultUnit(p) : unitOf(p, uid);
        const key = keyOf(String(p.id), unit.id);
        const line = state.cart.find(l => keyOf(l.pid, l.uid) === key);
        const isNew = !state.cart.some(l => l.pid === String(p.id));

        if (maxQtyFor(p, unit.id, null) < 1) {
            toast(`الكمية المتوفرة من «${p.name}» لا تكفي ${unit.factor > 1 ? 'لـ ' + unit.name + ' كاملة' : ''}.`, true);
            return false;
        }

        // تنبيهات الدواء: الحرجة تحتاج تأكيد
        if (isNew && p.alerts.length) {
            const critical = p.alerts.filter(a => a.severity === 'critical');
            if (critical.length && !confirm(`تنبيه حرج على «${p.name}»:\n\n${critical.map(a => '• ' + a.message).join('\n')}\n\nهل تريد إضافته للفاتورة؟`)) return false;
            if (!critical.length) toast(`تنبيه على «${p.name}»: ${p.alerts[0].message}`, true);
        }

        if (line) { line.qty++; state.cart = [line, ...state.cart.filter(l => l !== line)]; }
        else state.cart.unshift({ pid: String(p.id), uid: unit.id, qty: 1 });
        state.lastAdded = key;
        render();
        return true;
    }
    function setQty(key, qty) {
        const line = state.cart.find(l => keyOf(l.pid, l.uid) === key);
        if (!line) return;
        const p = productById.get(line.pid);
        const max = maxQtyFor(p, line.uid, key);
        let n = Math.floor(Number(qty) || 0);
        if (n > max) { toast(`المتاح من «${p.name}» بهذه الوحدة: ${max} فقط.`, true); n = max; }
        if (n <= 0) { remove(key); return; }
        line.qty = n;
        render();
    }
    function changeUnit(key, uid) {
        const line = state.cart.find(l => keyOf(l.pid, l.uid) === key);
        if (!line) return;
        const p = productById.get(line.pid);
        const newKey = keyOf(line.pid, Number(uid));
        const existing = state.cart.find(l => keyOf(l.pid, l.uid) === newKey);
        if (existing) { state.cart = state.cart.filter(l => l !== line); existing.qty += line.qty; }
        else line.uid = Number(uid);
        const target = existing || line;
        const max = maxQtyFor(p, target.uid, newKey);
        if (target.qty > max) {
            toast(`المتاح من «${p.name}» بوحدة ${unitOf(p, target.uid).name}: ${max}.`, true);
            target.qty = max;
            if (max <= 0) state.cart = state.cart.filter(l => l !== target);
        }
        render();
    }
    function remove(key) {
        state.cart = state.cart.filter(l => keyOf(l.pid, l.uid) !== key);
        render();
    }
    function clearSale() {
        if (!state.cart.length) return;
        if (!confirm('تفريغ الفاتورة الحالية؟')) return;
        state.cart = []; state.discount = 0; state.paid = 0; state.paidTouched = false;
        $('posDiscount').value = 0;
        render();
        $('posSearch').focus();
    }

    function renderLines() {
        if (!state.cart.length) {
            $('posLines').innerHTML = `<div class="pos-empty-cart"><strong>الفاتورة فارغة</strong>اضغط على منتج أو امسح الباركود لإضافته.</div>`;
            return;
        }
        $('posLines').innerHTML = state.cart.map(l => {
            const p = productById.get(l.pid);
            const unit = unitOf(p, l.uid);
            const key = keyOf(l.pid, l.uid);
            const max = maxQtyFor(p, l.uid, key);
            const unitPicker = p.units.length > 1
                ? `<div class="pos-units" role="group" aria-label="وحدة البيع">${p.units.map(u =>
                    `<button type="button" data-act="unit" data-key="${key}" data-uid="${u.id}" aria-pressed="${u.id === unit.id}"
                        title="${u.factor !== 1 ? esc(u.name) + ' = ' + u.factor + ' ' + esc(p.units[0].name) : esc(u.name)} — ${money(u.price)} ₪">${esc(u.name)}</button>`).join('')}</div>`
                : `<a class="pos-units-add" href="${UNITS_URL}/${p.id}/units" target="_blank" rel="noopener" title="عرّف وحدات البيع (شريط، علبة) وأسعارها لهذا المنتج">+ إضافة شريط / علبة</a>`;
            const alert = p.alerts[0];
            return `
                <div class="pos-line ${state.lastAdded === key ? 'flash' : ''}">
                    <div style="min-width:0">
                        <div class="pos-line-name" title="${esc(p.name)}">${esc(p.name)}${p.controlled ? '<span class="pos-tag rx">وصفة</span>' : ''}</div>
                        <div class="pos-line-unit"><span class="num">${money(unit.price)}</span> ₪ لكل ${esc(unit.name)}${p.units.length > 1 ? '' : ' ' + unitPicker}</div>
                        ${p.units.length > 1 ? unitPicker : ''}
                        ${alert ? `<div class="pos-line-alert ${['high', 'critical'].includes(alert.severity) ? 'is-high' : ''}">⚠ ${esc(alert.message)}</div>` : ''}
                    </div>
                    <div class="pos-stepper">
                        <button type="button" data-act="inc" data-key="${key}" aria-label="زيادة" ${max < 1 ? 'disabled' : ''}>+</button>
                        <input type="number" class="num" data-act="qty" data-key="${key}" value="${l.qty}" min="1" aria-label="الكمية">
                        <button type="button" data-act="dec" data-key="${key}" aria-label="إنقاص">−</button>
                    </div>
                    <div class="pos-line-total num">${money(unit.price * l.qty)}</div>
                    <button type="button" class="pos-remove" data-act="remove" data-key="${key}" aria-label="حذف ${esc(p.name)}" title="حذف">×</button>
                </div>`;
        }).join('');
        state.lastAdded = null;
    }

    function renderMethods() {
        $('posMethods').innerHTML = methods.map(m =>
            `<button type="button" class="pos-method" data-method="${esc(m.code)}" aria-pressed="${state.method === m.code}">${m.icon ? esc(m.icon) + ' ' : ''}${esc(m.name)}</button>`
        ).join('');
        const method = methods.find(m => m.code === state.method);
        $('posWallet').hidden = !(method && method.wallet);
        // البنك: اسم صاحب الحساب إجباري والجوال اختياري. المحفظة: الاسم والجوال إجباريان
        const isBank = method && method.kind === 'bank';
        $('posWalletTitle').textContent = isBank ? 'بيانات الحساب المحوِّل — للمطابقة مع كشف البنك' : 'بيانات المحوِّل — للمطابقة مع كشف المحفظة';
        $('posPayerNameLabel').textContent = isBank ? 'اسم صاحب الحساب' : 'اسم المحوِّل';
        $('posWalletLabel').innerHTML = isBank ? 'رقم الجوال <span class="opt">(اختياري)</span>' : 'رقم الجوال / المحفظة';
        $('posMethodInput').value = state.method;

        // الخزينة: تظهر للطرق النقدية فقط، والاختيار مطلوب فقط إذا كان هناك أكثر من خزينة
        const showRegister = isCashMethod(method) && registers.length > 1;
        $('posRegister').hidden = !showRegister;
        const sel = $('posRegisterSelect');
        if (!sel.options.length) {
            sel.innerHTML = registers.map(r => `<option value="${r.id}">${esc(r.name)}</option>`).join('');
        }
        sel.value = state.registerId;
        sel.disabled = !isCashMethod(method) || !registers.length;
    }

    function renderPrescription(t) {
        const box = $('posRx');
        const sel = $('posRxSelect');
        const list = t.customer ? t.customer.prescriptions : [];
        box.hidden = !t.controlled.length && !state.prescriptionId;
        sel.disabled = box.hidden;
        sel.innerHTML = `<option value="">${t.customer ? (list.length ? 'اختر الوصفة' : 'لا توجد وصفة غير مصروفة لهذا العميل') : 'اختر العميل أولاً'}</option>` +
            list.map(r => `<option value="${r.id}">د. ${esc(r.doctor)}${r.diagnosis ? ' — ' + esc(r.diagnosis) : ''} (${esc(r.date || '')})</option>`).join('');
        if (!list.some(r => String(r.id) === state.prescriptionId)) state.prescriptionId = '';
        sel.value = state.prescriptionId;
    }

    function renderSummary() {
        let t = totals();
        if (!state.paidTouched) { state.paid = t.total; t = totals(); }
        $('posPaid').value = state.paidTouched ? $('posPaid').value : money(t.total);

        // اسم المحوِّل: يُقترح اسم العميل المختار ما لم يكتب الكاشير اسماً آخر
        const payer = $('posPayerName');
        if (!payer.dataset.touched) payer.value = t.customer ? t.customer.name : '';

        $('posSubtotal').textContent = money(t.subtotal);
        $('posTotal').innerHTML = `${money(t.total)}<span>₪</span>`;
        $('posItemsCount').textContent = t.items ? `${t.items} قطعة في ${state.cart.length} ${state.cart.length === 1 ? 'صنف' : 'أصناف'}` : 'لا توجد أصناف';
        $('posDiscountInput').value = money(t.discount);

        // معلومات العميل
        $('posQuickDebt').hidden = !(t.customer && t.customer.balance > 0);
        const info = $('posCustomerInfo');
        if (t.customer) {
            info.hidden = false;
            info.innerHTML = `<span>دين سابق: <b class="num">${money(t.customer.balance)}</b> ₪</span>` +
                (t.customer.limit > 0 ? `<span>الحد الائتماني: <span class="num">${money(t.customer.limit)}</span> ₪</span>` : '') +
                (t.customer.prescriptions.length ? `<span>وصفات غير مصروفة: <span class="num">${t.customer.prescriptions.length}</span></span>` : '');
        } else {
            info.hidden = true;
        }

        renderPrescription(t);

        // الباقي / المتبقي
        const bal = $('posBalance');
        if (t.due > 0) {
            bal.className = 'pos-balance is-due';
            bal.innerHTML = `<span>متبقي ${t.customer ? 'يُرحّل كدين' : 'غير مدفوع'}</span><span class="num">${money(t.due)}</span>`;
        } else if (t.toOldDebt > 0) {
            bal.className = 'pos-balance is-change';
            bal.innerHTML = `<span>يُخصم من الدين السابق</span><span class="num">${money(t.toOldDebt)}</span>`;
        } else {
            bal.className = 'pos-balance' + (t.change > 0 ? ' is-change' : '');
            bal.innerHTML = `<span>الباقي للزبون</span><span class="num">${money(t.change)}</span>`;
        }

        // تنبيه الفاتورة (قبل الدفع): الوصفة الطبية
        const rxMissing = t.controlled.length > 0 && !state.prescriptionId;
        const ticketHint = rxMissing
            ? `«${t.controlled.join('، ')}» ${t.controlled.length > 1 ? 'تحتاج' : 'يحتاج'} وصفة طبية. ` +
              (t.customer ? (t.customer.prescriptions.length ? 'اختر الوصفة من الأعلى.' : 'أضف وصفة لهذا العميل من صفحة الوصفات أولاً.') : 'اختر العميل ثم وصفته.')
            : '';
        $('posTicketHint').hidden = !ticketHint;
        $('posTicketHint').textContent = ticketHint;

        // تنبيهات نافذة الدفع
        const method = methods.find(m => m.code === state.method);
        let hint = '', hintError = false;
        if (!t.customer && t.due > 0 && state.cart.length) {
            hint = 'البيع بالدين يحتاج إلى عميل مسجل. اختر العميل أو ادفع المبلغ كاملاً.'; hintError = true;
        } else if (method && method.wallet && state.cart.length && !$('posPayerName').value.trim()) {
            hint = method.kind === 'bank' ? 'أدخل اسم صاحب الحساب البنكي المحوِّل (كما يظهر في كشف البنك).' : 'أدخل اسم المحوِّل كما يظهر في المحفظة.'; hintError = true;
        } else if (method && method.kind === 'wallet' && state.cart.length && !$('posWalletInput').value.trim()) {
            hint = 'أدخل رقم جوال/محفظة المحوِّل لإتمام الدفع بهذه الطريقة.'; hintError = true;
        } else if (t.customer && t.customer.limit > 0 && t.customer.balance + t.due > t.customer.limit) {
            hint = `تنبيه: دين العميل سيصبح ${money(t.customer.balance + t.due)} ₪ وهو أعلى من حده الائتماني.`;
        } else if (isCashMethod(method) && !registers.length && t.paid > 0 && state.cart.length) {
            hint = 'لا توجد خزينة نشطة، لن يُسجَّل المبلغ في أي صندوق. أنشئ خزينة من صفحة الخزائن.';
        }
        $('posHint').hidden = !hint;
        $('posHint').className = 'pos-hint' + (hintError ? ' is-error' : '');
        $('posHint').textContent = hint;

        $('posSheetTotal').innerHTML = `${money(t.total)}<span>₪</span>`;
        $('posSheetCustomer').textContent = t.customer ? t.customer.name : 'زبون نقدي';
        $('posOpenPay').disabled = !state.cart.length || rxMissing;
        $('posComplete').disabled = !state.cart.length || hintError || rxMissing;
        $('posDebt').disabled = !state.cart.length || !t.customer || rxMissing;
        $('posClear').disabled = !state.cart.length;
    }

    function renderHidden() {
        $('posHidden').innerHTML = state.cart.map((l, i) =>
            `<input type="hidden" name="items[${i}][product_id]" value="${l.pid}">` +
            `<input type="hidden" name="items[${i}][quantity]" value="${l.qty}">` +
            (l.uid ? `<input type="hidden" name="items[${i}][product_unit_id]" value="${l.uid}">` : '')
        ).join('');
    }

    function render() {
        renderGrid(); renderLines(); renderMethods(); renderSummary(); renderHidden(); saveDraft();
    }

    // ---------- البحث والباركود ----------
    function handleScan(raw) {
        const q = norm(raw);
        if (!q) return;
        const hit = barcodeIndex.get(q);
        if (hit) { const p = productById.get(hit.pid); if (add(hit.pid, hit.uid)) toast(`أُضيف: ${p.name}`); return true; }
        const bySku = products.find(p => norm(p.sku) === q);
        if (bySku) { if (add(bySku.id)) toast(`أُضيف: ${bySku.name}`); return true; }
        const matches = products.filter(p => matchesWords(q, p.name) &&
            (state.category === 'all' || String(p.category) === String(state.category)));
        if (matches.length === 1) { if (add(matches[0].id)) toast(`أُضيف: ${matches[0].name}`); return true; }
        if (!matches.length) toast('لا يوجد منتج بهذا الباركود أو الاسم.', true);
        return false;
    }

    let toastTimer;
    function toast(msg, isError = false) {
        document.querySelectorAll('.pos-toast').forEach(t => t.remove());
        const el = document.createElement('div');
        el.className = 'pos-toast' + (isError ? ' err' : '');
        el.setAttribute('role', 'status');
        el.textContent = msg;
        document.body.appendChild(el);
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.remove(), 2600);
    }

    function submit(asDebt = false) {
        const t = totals();
        if (!state.cart.length) return;
        if (asDebt) {
            if (!t.customer) { toast('اختر العميل أولاً لتسجيل البيع بالدين.', true); return; }
            if (t.controlled.length && !state.prescriptionId) { toast('اختر الوصفة الطبية أولاً.', true); return; }
            state.paidTouched = true; state.paid = 0; $('posPaid').value = 0;
            $('posMethodInput').value = 'debt';
            $('posRegisterSelect').disabled = true;
        } else if ($('posComplete').disabled) {
            return;
        }
        renderHidden();
        $('posComplete').disabled = true; $('posDebt').disabled = true;
        $('posComplete').textContent = 'جارٍ الحفظ…';
        finish();
    }

    // ---------- الحفظ: مع النت على السيرفر، وبدونه على الجهاز ----------
    let finishing = false;
    async function finish() {
        if (finishing) return;
        if (!window.PosOffline) { $('posForm').submit(); return; }
        finishing = true;
        try {
            let net = await PosOffline.reachable();
            if (net === 'online' && PosOffline.counts().pending) {
                // الفواتير القديمة أولاً حتى يبقى الترتيب صحيحاً
                await Promise.race([PosOffline.sync(), new Promise(r => setTimeout(r, 8000))]);
            }
            if (net === 'online') { $('posForm').submit(); return; }
            if (OFFLINE.shiftBlocked) { toast('لا يمكن البيع قبل فتح وردية.', true); restoreButtons(); return; }
            queueSale();
        } catch (e) {
            restoreButtons();
            toast('تعذّر حفظ الفاتورة، حاول مرة أخرى.', true);
        } finally {
            finishing = false;
        }
    }

    function restoreButtons() {
        $('posComplete').innerHTML = 'إتمام البيع <kbd>Enter</kbd>';
        $('posRegisterSelect').disabled = false;
        $('posMethodInput').value = state.method;
        renderSummary();
    }

    function queueSale() {
        const t = totals();
        const code = $('posMethodInput').value || state.method;
        const method = methods.find(m => m.code === code);
        const isCash = code !== 'debt' && isCashMethod(method);
        const paid = code === 'debt' ? 0 : Math.max(0, Number($('posPaid').value) || 0);
        const due = Math.max(0, Math.round((t.total - paid) * 100) / 100);
        const extra = Math.max(0, paid - t.total);
        const toOldDebt = t.customer ? Math.min(extra, t.customer.balance) : 0;
        const val = id => ($(id).value || '').trim();

        const prices = {}, lines = [];
        state.cart.forEach(l => {
            const p = productById.get(l.pid), u = unitOf(p, l.uid);
            prices[`${p.id}:${l.uid || 0}`] = u.price;
            lines.push({ pid: p.id, uid: l.uid || 0, qty: l.qty, base_qty: Math.round(l.qty * u.factor), name: p.name, unit: u.name, price: u.price });
        });

        const entry = PosOffline.enqueue({
            customer_id: t.customer ? t.customer.id : null,
            customer_name: t.customer ? t.customer.name : '',
            total: t.total, discount: t.discount, paid, due,
            change: Math.max(0, extra - toOldDebt),
            debt_delta: Math.round((due - toOldDebt) * 100) / 100,
            method_name: code === 'debt' ? 'آجل' : (method ? method.name : code),
            lines,
            payload: {
                customer_id: t.customer ? t.customer.id : null,
                prescription_id: state.prescriptionId || null,
                items: state.cart.map(l => ({ product_id: Number(l.pid), product_unit_id: l.uid || null, quantity: l.qty })),
                prices,
                payment_method: code,
                paid_amount: paid,
                discount: t.discount,
                wallet_phone: isCash ? null : val('posWalletInput') || null,
                payer_name: isCash ? null : val('posPayerName') || null,
                payer_account: isCash ? null : val('posPayerAccount') || null,
                transfer_ref: isCash ? null : val('posTransferRef') || null,
            },
        });
        if (!entry) { restoreButtons(); toast('ذاكرة الجهاز ممتلئة، تعذّر حفظ الفاتورة.', true); return; }

        // تحديث المعروض محلياً: المخزون، دين العميل، الوصفة المصروفة
        lines.forEach(l => { const p = productById.get(String(l.pid)); if (p) p.stock = Math.max(0, p.stock - l.base_qty); });
        if (t.customer) {
            t.customer.balance = Math.round((t.customer.balance + entry.debt_delta) * 100) / 100;
            if (state.prescriptionId) t.customer.prescriptions = t.customer.prescriptions.filter(r => String(r.id) !== String(state.prescriptionId));
        }

        resetSale();
        closeSheet();
        showOfflineBanner(entry);
    }

    function resetSale() {
        state.cart = []; state.discount = 0; state.paid = 0; state.paidTouched = false; state.prescriptionId = '';
        state.method = methods[0].code;
        $('posDiscount').value = 0;
        ['posPayerName', 'posWalletInput', 'posPayerAccount', 'posTransferRef'].forEach(id => { $(id).value = ''; delete $(id).dataset.touched; });
        try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
        restoreButtons();
        render();
        $('posSearch').focus();
    }

    function showOfflineBanner(entry) {
        document.querySelectorAll('.pos-banner.is-offline-sale').forEach(b => b.remove());
        const b = document.createElement('div');
        b.className = 'pos-banner ok is-offline-sale';
        b.setAttribute('role', 'status');
        b.innerHTML = `<span>حُفظت الفاتورة على الجهاز برقم مؤقت <b class="num">${esc(entry.number)}</b> وستُرفع لما يرجع النت.</span>` +
            `<a href="#" data-print>طباعة الإيصال</a>`;
        b.querySelector('[data-print]').addEventListener('click', e => { e.preventDefault(); PosOffline.printReceipt(entry); });
        const pos = $('pos');
        pos.querySelectorAll(':scope > .pos-banner').forEach(x => x.remove());
        pos.classList.add('has-banner');
        pos.prepend(b);
    }

    function openSheet() {
        if ($('posOpenPay').disabled) return;
        $('posSheet').classList.add('open');
        renderSummary();
        setTimeout(() => { $('posPaid').focus(); $('posPaid').select(); }, 30);
    }
    function closeSheet() { $('posSheet').classList.remove('open'); }
    const sheetOpen = () => $('posSheet').classList.contains('open');

    function openModal() { $('posModal').classList.add('open'); setTimeout(() => $('newCustomerName').focus(), 30); }
    function closeModal() { $('posModal').classList.remove('open'); }

    // ---------- الأحداث ----------
    function bind() {
        $('posGrid').addEventListener('click', e => {
            const card = e.target.closest('.pos-product');
            if (card && !card.disabled) add(card.dataset.id);
        });

        document.querySelector('.pos-cats').addEventListener('click', e => {
            const b = e.target.closest('.pos-cat');
            if (!b) return;
            state.category = b.dataset.cat;
            document.querySelectorAll('.pos-cat').forEach(x => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
            renderGrid();
        });

        const search = $('posSearch');
        search.addEventListener('input', () => { state.query = search.value; renderGrid(); });
        search.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (handleScan(search.value)) { search.value = ''; state.query = ''; renderGrid(); }
            } else if (e.key === 'Escape' && search.value) {
                e.stopPropagation(); search.value = ''; state.query = ''; renderGrid();
            }
        });

        $('posLines').addEventListener('click', e => {
            const b = e.target.closest('button[data-act]');
            if (!b) return;
            const line = state.cart.find(l => keyOf(l.pid, l.uid) === b.dataset.key);
            if (!line) return;
            if (b.dataset.act === 'inc') add(line.pid, line.uid);
            if (b.dataset.act === 'dec') setQty(b.dataset.key, line.qty - 1);
            if (b.dataset.act === 'remove') remove(b.dataset.key);
            if (b.dataset.act === 'unit' && Number(b.dataset.uid) !== line.uid) changeUnit(b.dataset.key, b.dataset.uid);
        });
        $('posLines').addEventListener('change', e => {
            if (e.target.dataset.act === 'qty') setQty(e.target.dataset.key, e.target.value);
        });

        $('posMethods').addEventListener('click', e => {
            const b = e.target.closest('.pos-method');
            if (!b) return;
            state.method = b.dataset.method;
            renderMethods(); renderSummary(); saveDraft();
            const m = methods.find(x => x.code === state.method);
            if (m && m.wallet) $('posWalletInput').focus();
        });
        $('posWalletInput').addEventListener('input', renderSummary);
        $('posPayerName').addEventListener('input', e => { e.target.dataset.touched = e.target.value ? '1' : ''; renderSummary(); });
        $('posRegisterSelect').addEventListener('change', e => { state.registerId = e.target.value; saveDraft(); });
        $('posRxSelect').addEventListener('change', e => { state.prescriptionId = e.target.value; renderSummary(); saveDraft(); });

        $('posDiscount').addEventListener('input', e => { state.discount = Number(e.target.value) || 0; renderSummary(); saveDraft(); });
        $('posPaid').addEventListener('input', e => { state.paidTouched = true; state.paid = Number(e.target.value) || 0; renderSummary(); });
        $('posQuick').addEventListener('click', e => {
            const b = e.target.closest('[data-quick]');
            if (!b) return;
            const t = totals();
            if (b.dataset.quick === 'exact') { state.paidTouched = false; }
            else if (b.dataset.quick === 'debt') {
                // دفع الفاتورة + كامل الدين السابق
                state.paidTouched = true;
                state.paid = t.total + (t.customer ? t.customer.balance : 0);
                $('posPaid').value = money(state.paid);
            } else {
                state.paidTouched = true;
                const base = Number($('posPaid').value) === t.total ? 0 : (Number($('posPaid').value) || 0);
                state.paid = base + Number(b.dataset.quick);
                $('posPaid').value = money(state.paid);
            }
            renderSummary();
        });

        $('posForm').addEventListener('submit', e => { e.preventDefault(); submit(false); });
        $('posDebt').addEventListener('click', () => submit(true));
        $('posOpenPay').addEventListener('click', openSheet);
        $('posSheetClose').addEventListener('click', closeSheet);
        $('posSheet').addEventListener('click', e => { if (e.target === $('posSheet')) closeSheet(); });
        $('posPaid').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); submit(false); } });
        $('posClear').addEventListener('click', clearSale);

        $('posModal').addEventListener('click', e => { if (e.target === $('posModal')) closeModal(); });

        // إضافة عميل تحتاج السيرفر
        $('posCustomerForm').addEventListener('submit', async e => {
            if (!window.PosOffline || e.target.dataset.checked) return;
            e.preventDefault();
            if (await PosOffline.reachable() === 'online') { e.target.dataset.checked = '1'; e.target.submit(); }
            else toast('إضافة عميل جديد تحتاج إنترنت. سجّل البيع كزبون نقدي أو لعميل موجود.', true);
        });

        // اختصارات لوحة المفاتيح + التقاط الماسح الضوئي عندما لا يكون التركيز في حقل
        let buffer = '', lastKey = 0;
        document.addEventListener('keydown', e => {
            if ($('posModal').classList.contains('open')) { if (e.key === 'Escape') closeModal(); return; }
            if (e.key === 'F2') { e.preventDefault(); search.focus(); search.select(); return; }
            if (e.key === 'F4') { e.preventDefault(); window.posCustomerSelect ? window.posCustomerSelect.focus() : $('posCustomer').focus(); return; }
            if (e.key === 'F8') { e.preventDefault(); state.paidTouched = false; renderSummary(); openSheet(); return; }
            if (e.key === 'F9') { e.preventDefault(); sheetOpen() ? submit(false) : openSheet(); return; }
            if (e.key === 'Escape' && sheetOpen()) { e.preventDefault(); closeSheet(); return; }

            const tag = (document.activeElement.tagName || '').toLowerCase();
            if (['input', 'textarea', 'select'].includes(tag) || sheetOpen()) return;

            if (e.key === 'Escape') { clearSale(); return; }
            const now = Date.now();
            if (now - lastKey > 60) buffer = '';
            lastKey = now;
            if (e.key === 'Enter' && buffer.length >= 3) { e.preventDefault(); handleScan(buffer); buffer = ''; return; }
            if (e.key.length === 1) buffer += e.key;
        });

        setInterval(() => {
            const d = new Date();
            $('posClock').textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        }, 30000);
    }

    function initCustomerSelect() {
        const sel = $('posCustomer');
        sel.value = state.customerId;
        const onChange = v => { state.customerId = v ? String(v) : ''; state.prescriptionId = ''; renderSummary(); saveDraft(); };
        if (window.TomSelect) {
            window.posCustomerSelect = new TomSelect(sel, {
                create: false,
                allowEmptyOption: true,
                placeholder: 'ابحث عن عميل بالاسم أو الهاتف (F4)',
                // نفس قواعد البحث: بدون حساسية للهمزات والتاء المربوطة والتشكيل
                score: search => item => (!search.trim() || matchesWords(search, item.text)) ? 1 : 0,
                onChange,
            });
        } else {
            sel.addEventListener('change', () => onChange(sel.value));
        }
    }

    function init() {
        if (window.PosOffline) {
            PosOffline.init(Object.assign({}, OFFLINE, {
                onChange: s => { $('pos').classList.toggle('is-offline', s.net === 'offline' || s.net === 'auth'); },
            }));
        }
        loadDraft();
        $('posDiscount').value = state.discount;
        bind();
        render();
        initCustomerSelect();
        renderSummary();
    }

    document.addEventListener('DOMContentLoaded', init);
    return { openModal, closeModal };
})();
</script>
@endsection
