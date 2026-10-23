@extends('layouts.app')
@section('title', 'Hồ sơ cá nhân · Nét')
@section('page-title', 'Hồ sơ cá nhân')
@section('content')
<div class="px-4 py-5 md:px-8 md:py-7 max-w-xl mx-auto">
    @include('profile._form')
</div>
@endsection
