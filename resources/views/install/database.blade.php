@extends('layouts.guest')
@section('title', 'Cài đặt Nét — Kết nối cơ sở dữ liệu')
@section('robots', 'noindex, nofollow')
@section('subtitle', 'Bước 2/4 · Kết nối cơ sở dữ liệu')
@section('content')
    <form method="POST" action="{{ route('install.database.save') }}" class="space-y-4">
        @csrf
        <x-input name="db_host" label="Database Host" icon="fa-server" value="{{ old('db_host', '127.0.0.1') }}" required autocomplete="off" />
        <x-input name="db_port" label="Port" icon="fa-ethernet" value="{{ old('db_port', '3306') }}" required autocomplete="off" />
        <x-input name="db_database" label="Tên Database" icon="fa-database" value="{{ old('db_database') }}" required autocomplete="off" />
        <x-input name="db_username" label="Username" icon="fa-user" value="{{ old('db_username') }}" required autocomplete="off" />
        <x-input name="db_password" type="password" label="Password" icon="fa-key" autocomplete="off" />

        <x-button icon="fa-plug" class="mt-2">Kết nối &amp; tạo bảng dữ liệu</x-button>
    </form>
@endsection
