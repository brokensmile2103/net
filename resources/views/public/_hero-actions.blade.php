{{-- Nút chính của phần đầu trang. Biến: $studio, $services --}}
<div class="pf-actions">
    @if($studio->order_form_enabled)
        <a href="#dat-don" class="pf-btn pf-btn-primary">Đặt thiết kế</a>
    @endif
    @if(! empty($consultSlots))
        <a href="#tu-van" class="pf-btn pf-btn-ghost"><i class="fa-regular fa-calendar" aria-hidden="true"></i>Hẹn gọi tư vấn</a>
    @endif
    @if($services->isNotEmpty())
        <a href="#bang-gia" class="pf-btn pf-btn-ghost">Xem bảng giá</a>
    @endif
    @if($studio->contact_url)
        <a href="{{ $studio->contact_url }}" target="_blank" rel="noopener" class="pf-btn pf-btn-ghost">{{ link_host_label($studio->contact_url) }}</a>
    @endif
</div>
