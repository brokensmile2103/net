@php
    // Chỉ lấy old() cho đúng form vừa gửi (nhiều form sửa trên cùng trang).
    $mine = $s ? (int) old('_service') === $s->id : ! old('_service');
    $v = fn ($key, $default = null) => $mine ? old($key, $default) : $default;
    $p = 'svc'.($s?->id ?? 'new').'_';
    $sent = $mine && old('name') !== null; // form vừa gửi lỗi → checkbox lấy đúng trạng thái đã gửi
    $chk = fn ($key, $default) => $sent ? (bool) old($key) : (bool) $default;
    $price = $v('price', $s?->price !== null ? (int) $s->price : null);
@endphp
<div class="grid sm:grid-cols-2 gap-4">
    <div class="sm:col-span-2"><x-input name="name" :id="$p.'name'" label="Tên dịch vụ *" :value="$v('name', $s?->name)" required maxlength="120" placeholder="VD: Thiết kế logo" /></div>
    <div>
        <x-input name="price" :id="$p.'price'" label="Giá (đ)" :value="is_numeric($price) ? money($price) : ''" class="money-input" inputmode="numeric" placeholder="Để trống = báo giá sau" />
        <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="price_is_from" value="1" class="rounded accent-[var(--accent)]" @checked($chk('price_is_from', $s?->price_is_from))> Hiện là “từ …” (giá khởi điểm)</label>
    </div>
    <x-input name="duration_days" :id="$p.'days'" type="number" min="1" max="365" label="Thời gian làm (ngày)" :value="$v('duration_days', $s?->duration_days)" hint="Dùng để gợi ý hạn bàn giao." />
    <x-input name="revision_rounds" :id="$p.'rev'" type="number" min="0" max="20" label="Số lần sửa miễn phí" :value="$v('revision_rounds', $s?->revision_rounds)" :hint="'Để trống = theo mặc định ('.$studio->default_revision_rounds.' lần)'" />
    <x-input name="deposit_percent" :id="$p.'dep'" type="number" min="0" max="100" label="Cọc (%)" :value="$v('deposit_percent', $s?->deposit_percent)" :hint="'Để trống = theo mặc định ('.$studio->default_deposit_percent.'%). Nhập 0 = không cần cọc.'" />
    <div class="sm:col-span-2"><x-textarea name="description" :id="$p.'desc'" label="Mô tả cho khách" rows="2" :value="$v('description', $s?->description)" placeholder="Bao gồm 3 phương án phác thảo, file AI/PDF/PNG, hướng dẫn sử dụng cơ bản…" /></div>
    <div class="sm:col-span-2"><x-textarea name="brief_hint" :id="$p.'hint'" label="Gợi ý chuẩn bị brief" rows="2" :value="$v('brief_hint', $s?->brief_hint)" hint="Hiện trong form đặt đơn khi khách chọn dịch vụ này." placeholder="Tên thương hiệu, ngành nghề, đối tượng khách hàng, 3–5 logo bạn thích…" /></div>
    @include('services._brief-builder')
    <label class="sm:col-span-2 flex items-center gap-2 text-sm"><input type="checkbox" name="is_public" value="1" class="rounded accent-[var(--accent)]" @checked($chk('is_public', $s ? $s->is_public : true))> Hiện trên trang đặt đơn</label>
</div>
