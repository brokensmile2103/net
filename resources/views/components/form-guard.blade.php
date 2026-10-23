@props(['name'])
@php $__fg = \App\Services\FormGuard::generate($name); @endphp

<input type="hidden" name="form_ts" value="{{ $__fg['timestamp'] }}">
<input type="hidden" name="form_sig" value="{{ $__fg['signature'] }}">
<input type="hidden" name="hp_key" value="{{ $__fg['honeypot_field'] }}">

{{-- Honeypot — tên field NGẪU NHIÊN mỗi lần load trang (xem FormGuard::generate),
     có `readonly` để browser/password manager KHÔNG autofill vào đây (đây là
     hành vi chuẩn của mọi trình duyệt với input readonly, không phải hack),
     cộng thêm ẩn off-screen + tabindex="-1" + aria-hidden. Người dùng thật
     không bao giờ thấy/điền field này; bot quét form thường tự động điền
     MỌI trường tìm thấy kể cả field ẩn. --}}
<div style="position: absolute; left: -9999px; top: -9999px; opacity: 0;" aria-hidden="true">
    <label for="{{ $__fg['honeypot_field'] }}">Ghi chú nội bộ</label>
    <input type="text" id="{{ $__fg['honeypot_field'] }}" name="{{ $__fg['honeypot_field'] }}" tabindex="-1" autocomplete="off" readonly>
</div>
