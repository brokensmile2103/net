{{--
    Google Analytics 4 riêng của studio (Cài đặt → Trang đặt đơn → Google Analytics). Chủ studio chỉ nhập mã đo lường
    G-XXXXXXX — đoạn mã dưới đây do Nét tự sinh (không nhận script dán tay ⇒ không chèn được mã lạ vào trang khách).
    - Trang đặt đơn (@section('ga_page', 'order')): đo lượt xem như bình thường.
    - Ngay sau khi khách gửi đơn / đặt hẹn (session ga_lead): gửi sự kiện generate_lead nhưng KHÔNG gửi lượt xem trang
      theo dõi — địa chỉ & tiêu đề báo về Google là của trang đặt đơn, link theo dõi riêng của khách không bao giờ lộ ra.
    - Chủ studio đang đăng nhập xem trang của mình: không gửi gì (số liệu không bị lẫn).
--}}
@php
    $gaId = $studio->gaId();
    $gaPage = trim($__env->yieldContent('ga_page'));
    $gaLead = session('ga_lead');
    $gaOwner = auth()->id() !== null && auth()->id() === $studio->owner_id;
@endphp
@if($gaId && ! $gaOwner && ($gaPage === 'order' || is_array($gaLead)))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){ dataLayer.push(arguments); }
        gtag('js', new Date());
        @if($gaPage === 'order')
            gtag('config', @js($gaId));
        @else
            gtag('config', @js($gaId), { send_page_view: false, page_location: @js($studio->orderFormUrl()), page_title: @js($studio->name) });
        @endif
        @if(is_array($gaLead))
            gtag('event', 'generate_lead', @js(array_filter([
                'lead_type' => ($gaLead['form'] ?? 'order') === 'consult' ? 'hen_tu_van' : 'dat_don',
                'service' => $gaLead['service'] ?? null,
                'value' => $gaLead['value'] ?? null,
                'currency' => ! empty($gaLead['value']) ? 'VND' : null,
                'page_location' => $studio->orderFormUrl(),
            ], fn ($v) => $v !== null && $v !== '')));
        @endif
    </script>
@endif
