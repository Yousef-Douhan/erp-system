@extends('layouts.app')

@section('title', 'صلاحيات الدور: ' . $role->name)

@section('content')
@include('roles._styles')

<div class="rp">
    <div class="rp-head">
        <div>
            <h2>صلاحيات الدور «{{ $role->name }}»</h2>
            <p>فعّل أو أوقف الصلاحيات، ثم احفظ. يمكنك تحديد قسم كامل من المربع بجانب اسمه.</p>
        </div>
    </div>

    @include('roles._form', ['role' => $role])
</div>
@endsection
