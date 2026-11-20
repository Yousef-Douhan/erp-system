{{-- حقل كلمة مرور مع زر إظهار/إخفاء وتنبيه Caps Lock. المتغيرات: $id, $name, $label, $autocomplete, $placeholder?, $hint?, $labelExtra? --}}
<div class="field {{ $errors->has($name) ? 'has-error' : '' }}">
    <div class="field-row">
        <label for="{{ $id }}">{{ $label }}</label>
        {!! $labelExtra ?? '' !!}
    </div>
    <div class="input">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
        <input id="{{ $id }}" type="password" name="{{ $name }}" required autocomplete="{{ $autocomplete }}" placeholder="{{ $placeholder ?? '••••••••' }}" dir="ltr"
               @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $id }}-err" @endif>
        <button type="button" class="toggle-pass" data-toggle-pass="{{ $id }}" aria-label="إظهار كلمة المرور">
            <svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="eye-off" style="display:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.2A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6C3.9 8.4 2 12 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/></svg>
        </button>
    </div>
    <div class="caps" id="{{ $id }}-caps">⚠ زر الأحرف الكبيرة (Caps Lock) مفعّل</div>
    @if (!empty($hint))<div class="hint">{{ $hint }}</div>@endif
    @error($name)<div class="err" id="{{ $id }}-err">{{ $message }}</div>@enderror
</div>
