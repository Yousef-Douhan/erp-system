@extends('layouts.app')

@section('title', 'دور جديد')

@section('content')
@include('roles._styles')

<div class="rp">
    <div class="rp-head">
        <div>
            <h2>دور جديد</h2>
            <p>سمِّ الدور ثم فعّل الصلاحيات التي يحتاجها. يمكنك تحديد قسم كامل من المربع بجانب اسمه.</p>
        </div>
    </div>

    @include('roles._form', ['role' => null])
</div>
@endsection
