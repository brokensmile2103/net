{{-- Hộp báo lỗi chung cho các trang không có ô nhập riêng hiển thị lỗi. --}}
@if($errors->any())
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm']) }} role="alert">
        <ul class="space-y-0.5">@foreach($errors->all() as $error)<li><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
