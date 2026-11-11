<style>
    .rp { max-width: 1280px; margin: 0 auto; display: grid; grid-template-columns: minmax(0, 1fr); gap: 18px; color: var(--ink); }
    .rp .num { font-variant-numeric: tabular-nums; }
    .rp-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; }
    .rp-head h2 { margin: 0; font-size: 20px; font-weight: 700; }
    .rp-head p { margin: 4px 0 0; font-size: 14px; color: var(--muted); max-width: 640px; line-height: 1.6; }
    .rp-btn {
        height: 42px; padding: 0 16px; border-radius: 10px; display: inline-flex; align-items: center; gap: 8px;
        background: var(--teal); color: #fff !important; font-size: 14px; font-weight: 600; text-decoration: none; border: 0; cursor: pointer;
    }
    .rp-btn:hover { background: var(--teal-strong); }
    .rp-btn svg { width: 18px; height: 18px; }
    .rp-btn.is-ghost { background: var(--paper); color: var(--ink) !important; border: 1.5px solid var(--line); }
    .rp-btn.is-ghost:hover { border-color: var(--teal); color: var(--teal) !important; background: var(--teal-soft); }
    .rp-btn:disabled { opacity: .5; cursor: not-allowed; }

    .rp-flash { padding: 12px 16px; border-radius: 12px; font-size: 14px; font-weight: 600; }
    .rp-flash.ok { background: var(--teal-soft); color: var(--teal-strong); border: 1px solid var(--teal-line); }
    .rp-flash.err { background: var(--red-soft); color: var(--red); border: 1px solid #F3C2BD; font-weight: 500; }
    .rp-flash ul { margin: 0; padding-inline-start: 18px; }

    /* بطاقات الأدوار */
    .rp-roles { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
    .rp-role { padding: 18px; display: flex; flex-direction: column; gap: 14px; }
    .rp-role.is-admin { background: var(--ink); border-color: var(--ink); color: #fff; }
    .rp-role-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
    .rp-role-name { font-size: 16px; font-weight: 700; margin: 0; word-break: break-word; }
    .rp-role-users { font-size: 13px; color: var(--muted); margin-top: 2px; }
    .rp-role.is-admin .rp-role-users { color: #A9C2BF; }
    .rp-lock { flex: none; display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 600; padding: 3px 9px; border-radius: 999px; background: rgba(255,255,255,.1); color: #C9D8D6; }
    .rp-lock svg { width: 14px; height: 14px; }
    .rp-meter-label { display: flex; justify-content: space-between; font-size: 13px; color: var(--muted); }
    .rp-role.is-admin .rp-meter-label { color: #A9C2BF; }
    .rp-meter-label b { color: var(--ink); font-weight: 600; }
    .rp-role.is-admin .rp-meter-label b { color: #fff; }
    .rp-meter { height: 6px; border-radius: 6px; background: var(--canvas); overflow: hidden; margin-top: 6px; }
    .rp-role.is-admin .rp-meter { background: rgba(255,255,255,.12); }
    .rp-meter i { display: block; height: 100%; border-radius: 6px; background: var(--teal); }
    .rp-role.is-admin .rp-meter i { background: #7FD1C8; }
    .rp-areas { display: flex; flex-wrap: wrap; gap: 6px; min-height: 26px; }
    .rp-area { font-size: 12px; font-weight: 500; padding: 3px 9px; border-radius: 7px; background: var(--canvas); color: var(--ink); }
    .rp-area.is-partial { background: var(--paper); border: 1px dashed var(--line); color: var(--muted); }
    .rp-role.is-admin .rp-area { background: rgba(255,255,255,.1); color: #E2ECEB; }
    .rp-none { font-size: 13px; color: var(--muted); }
    .rp-role-actions { display: flex; gap: 8px; margin-top: auto; }
    .rp-role-actions a, .rp-role-actions button {
        flex: 1; height: 38px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        font: 600 13px var(--font); text-decoration: none; cursor: pointer; border: 1.5px solid var(--line); background: var(--paper); color: var(--ink);
    }
    .rp-role-actions a:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-soft); }
    .rp-role-actions button:hover:not(:disabled) { border-color: var(--red); color: var(--red); background: var(--red-soft); }
    .rp-role-actions button:disabled { opacity: .45; cursor: not-allowed; }
    .rp-role.is-admin .rp-role-actions a { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.15); color: #fff; }
    .rp-role.is-admin .rp-role-actions a:hover { background: rgba(255,255,255,.16); }
    .rp-role-actions form { flex: 1; display: flex; }

    /* المصفوفة */
    .rp-matrix-wrap { overflow-x: auto; }
    .rp-matrix { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13.5px; }
    .rp-matrix th, .rp-matrix td { padding: 10px 14px; border-bottom: 1px solid var(--line-soft); text-align: center; white-space: nowrap; }
    .rp-matrix th:first-child, .rp-matrix td:first-child { text-align: right; position: sticky; right: 0; background: var(--paper); z-index: 1; white-space: normal; min-width: 240px; }
    .rp-matrix thead th { background: #F5F8F7 !important; font-weight: 600; color: var(--muted); }
    .rp-matrix thead th:first-child { background: #F5F8F7 !important; }
    .rp-matrix .rp-group-row td { background: #F9FBFA !important; font-weight: 700; font-size: 13px; color: var(--ink); text-align: right; }
    .rp-matrix .rp-perm small { display: block; color: var(--muted); font-size: 12px; font-weight: 400; margin-top: 2px; }
    .rp-yes { display: inline-grid; place-items: center; width: 24px; height: 24px; border-radius: 7px; background: var(--teal-soft); color: var(--teal); }
    .rp-yes svg { width: 15px; height: 15px; }
    .rp-no { color: #B9C7C4; }
    .rp-matrix tbody tr:hover td { background: #F7FAF9 !important; }

    /* النموذج */
    .rp-form { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 18px; align-items: start; }
    .rp-side { position: sticky; top: 0; padding: 18px; display: flex; flex-direction: column; gap: 14px; }
    .rp-field label { display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px; color: var(--ink) !important; }
    .rp-field input { width: 100%; height: 44px; padding: 0 12px; font-size: 15px; }
    .rp-field small { display: block; margin-top: 6px; font-size: 12.5px; color: var(--muted); }
    .rp-count { padding: 14px; border-radius: 12px; background: var(--canvas); }
    .rp-count strong { display: block; font-size: 26px; font-weight: 700; line-height: 1.1; }
    .rp-count strong small { font-size: 15px; font-weight: 500; color: var(--muted); }
    .rp-count > span { font-size: 13px; color: var(--muted); }
    .rp-side-actions { display: grid; gap: 8px; }
    .rp-side-actions .rp-btn { justify-content: center; width: 100%; }
    .rp-tools { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .rp-search { position: relative; flex: 1; min-width: 220px; }
    .rp-search input { width: 100%; height: 42px; padding: 0 40px 0 12px; }
    .rp-search svg { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: var(--muted); pointer-events: none; }
    .rp-link-btn { height: 42px; padding: 0 12px; border-radius: 10px; border: 1.5px solid var(--line); background: var(--paper); font: 600 13px var(--font); color: var(--ink); cursor: pointer; }
    .rp-link-btn:hover { border-color: var(--teal); color: var(--teal); }

    .rp-groups { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 14px; align-items: start; }
    .rp-group { overflow: hidden; }
    .rp-group[hidden] { display: none; }
    .rp-group-head { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-bottom: 1px solid var(--line-soft); cursor: pointer; }
    .rp-group-icon { width: 36px; height: 36px; flex: none; border-radius: 10px; display: grid; place-items: center; background: var(--teal-soft); color: var(--teal); }
    .rp-group-icon svg { width: 19px; height: 19px; }
    .rp-group-title { flex: 1; min-width: 0; }
    .rp-group-title strong { display: block; font-size: 14.5px; }
    .rp-group-title > span { display: block; font-size: 12.5px; color: var(--muted); margin-top: 2px; }
    .rp-group-head input { width: 20px; height: 20px; flex: none; cursor: pointer; }
    .rp-perm-item { display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px; cursor: pointer; }
    .rp-perm-item[hidden] { display: none; }
    .rp-perm-item + .rp-perm-item { border-top: 1px solid var(--line-soft); }
    .rp-perm-item:hover { background: #F7FAF9; }
    .rp-perm-text { flex: 1; min-width: 0; }
    .rp-perm-text strong { display: block; font-size: 14px; font-weight: 600; }
    .rp-perm-text span { display: block; font-size: 12.5px; color: var(--muted); margin-top: 2px; line-height: 1.5; }
    .rp-perm-text code { display: inline-block; margin-top: 4px; font-size: 11px; color: #8A9997; direction: ltr; font-family: ui-monospace, Consolas, monospace; }

    /* مفتاح التبديل */
    .rp-switch { position: relative; width: 40px; height: 24px; flex: none; margin-top: 1px; }
    .rp-switch input { position: absolute; opacity: 0; inset: 0; margin: 0; cursor: pointer; }
    .rp-switch i { position: absolute; inset: 0; border-radius: 999px; background: #CBD6D4; transition: background .15s ease; }
    .rp-switch i::after { content: ''; position: absolute; top: 3px; right: 3px; width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.2); transition: transform .15s ease; }
    .rp-switch input:checked + i { background: var(--teal); }
    .rp-switch input:checked + i::after { transform: translateX(-16px); }
    .rp-switch input:focus-visible + i { outline: 3px solid #5FB3AE; outline-offset: 2px; }
    .rp-switch input:disabled + i { opacity: .6; }

    .rp-admin-note { display: flex; gap: 10px; padding: 14px 16px; border-radius: 12px; background: var(--ink); color: #E2ECEB; font-size: 14px; line-height: 1.6; }
    .rp-admin-note svg { width: 20px; height: 20px; flex: none; margin-top: 2px; color: #7FD1C8; }
    .rp-empty-search { padding: 30px; text-align: center; color: var(--muted); font-size: 14px; }

    @media (max-width: 980px) {
        .rp-form { grid-template-columns: minmax(0, 1fr); }
        .rp-side { position: static; order: -1; }
        .rp-groups { grid-template-columns: minmax(0, 1fr); }
    }
    @media (prefers-reduced-motion: reduce) { .rp-switch i, .rp-switch i::after { transition: none; } }
</style>
