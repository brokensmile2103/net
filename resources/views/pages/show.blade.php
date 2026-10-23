@extends('layouts.guest')
@section('title', $page->title.' · Nét')
@section('meta_description', $page->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($page->content), 155))
@section('subtitle', $page->title)
@section('content')
    <div class="legal-content">
        {!! $page->content !!}
    </div>

    <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-sm text-[var(--muted)] hover:text-[var(--ink)] mt-6">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>Về trang chủ
    </a>
@endsection
