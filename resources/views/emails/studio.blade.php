{{--
    Email gửi khách mang thương hiệu studio — giao diện đi theo mẫu trang đặt đơn ($t = App\Support\EmailTheme::for()).
    Viết cho hộp thư: bảng + style inline, rộng 600px, ảnh có alt, webfont chỉ là "tặng thêm" (luôn có font dự phòng).
    Biến: $studio, $t, $c (App\Services\Mail\EmailContent), $subject, $signature, $sandboxFor (chế độ thử).
--}}
@php
    use App\Services\Mail\EmailContent as EC;
    $k = $t['key'];
    $avatar = $studio->avatarUrl();
    $avatarUrl = $avatar && ! str_starts_with($avatar, 'http') ? url($avatar) : $avatar;
    $p = 'margin:0 0 14px;font-family:'.$t['font'].';font-size:16px;line-height:1.6;color:'.$t['ink2'].';';
    $headStyle = 'margin:0 0 16px;font-family:'.$t['head_font'].';font-size:'.$t['head_size'].';line-height:1.18;font-weight:'.$t['head_weight'].';letter-spacing:'.$t['head_spacing'].';color:'.$t['ink'].';';
    $eyebrowStyle = 'margin:0 0 10px;font-family:'.$t['eyebrow_font'].';font-size:12px;line-height:1.4;letter-spacing:'.($k === 'sketch' ? '0' : '1.2px').';text-transform:'.($k === 'sketch' ? 'none' : 'uppercase').';color:'.$t['accent_ink'].';font-weight:600;';
    $btnBg = $t['accent'];
    $btnStyle = 'display:inline-block;padding:14px 26px;font-family:'.$t['font'].';font-size:16px;font-weight:700;line-height:1;color:'.$t['on_accent'].';background:'.$btnBg.';border-radius:'.$t['btn_radius'].';text-decoration:none;box-shadow:'.$t['btn_shadow'].';'
        .($k === 'riso' ? 'border:2px solid '.$t['ink'].';' : '').($k === 'sketch' ? 'border:2px solid '.$t['ink'].';' : '');
    $cardPad = $k === 'swiss' ? '8px 0 8px' : '36px 36px 30px';
@endphp
<!DOCTYPE html>
<html lang="vi" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $subject }}</title>
    @if($t['fonts_url'])<link href="{{ $t['fonts_url'] }}" rel="stylesheet">@endif
    <style>
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; }
        a { color: {{ $t['accent_ink'] }}; }
        @media (max-width: 620px) {
            .net-wrap { width: 100% !important; }
            .net-pad { padding-left: 22px !important; padding-right: 22px !important; }
            .net-head { font-size: {{ $k === 'swiss' ? '28px' : '24px' }} !important; }
            .net-mast { font-size: 30px !important; }
            .net-step-label { display: none !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:{{ $t['page_bg'] }};">
    {{-- Dòng xem trước trong hộp thư (ẩn) --}}
    @if($c->preheader)
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all;">{{ \Illuminate\Support\Str::limit(EC::plain($c->preheader), 140) }}&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;</div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:{{ $t['page_bg'] }};">
        <tr><td align="center" style="padding:28px 12px 36px;">
            <table role="presentation" class="net-wrap" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;">

                @if($sandboxFor)
                    <tr><td style="padding:0 0 14px;">
                        <div style="background:#e0f2fe;border:1px solid #7dd3fc;border-radius:8px;padding:10px 14px;font-family:Helvetica,Arial,sans-serif;font-size:13px;line-height:1.5;color:#075985;">
                            <strong>Email thử (chế độ thử đang bật)</strong> — gửi thật thì email này tới <strong>{{ $sandboxFor }}</strong>. Tắt chế độ thử ở Cài đặt → Email gửi khách.
                        </div>
                    </td></tr>
                @endif

                {{-- ===== Đầu thư theo mẫu ===== --}}
                @switch($k)
                    @case('artboard')
                        <tr><td style="padding:0 2px 8px;font-family:{{ $t['eyebrow_font'] }};font-size:11px;color:#77777f;">
                            <span style="color:{{ $t['accent'] }};">&#9633;</span>&nbsp;{{ $studio->slug }} / email — 600 × auto
                        </td></tr>
                        @break
                    @case('riso')
                        <tr><td style="padding:4px 4px 18px;">
                            <p style="margin:0;font-family:{{ $t['head_font'] }};font-size:34px;line-height:1.05;font-weight:800;letter-spacing:-1px;color:{{ $t['face'] }};text-shadow:3px 3px 0 {{ $t['face_shadow'] }};">{{ $studio->name }}</p>
                            @if($studio->tagline)<p style="margin:8px 0 0;font-family:{{ $t['font'] }};font-size:13px;color:{{ $t['muted'] }};">{{ $studio->tagline }}</p>@endif
                        </td></tr>
                        @break
                    @case('swiss')
                        <tr><td style="background:{{ $t['accent'] }};height:14px;line-height:14px;font-size:0;">&nbsp;</td></tr>
                        <tr><td class="net-pad" style="padding:22px 0 6px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                                <td style="font-family:{{ $t['head_font'] }};font-size:30px;line-height:1;font-weight:800;letter-spacing:-1.2px;color:{{ $t['ink'] }};">{{ $studio->name }}</td>
                                <td align="right" style="font-family:{{ $t['font'] }};font-size:12px;font-weight:700;color:{{ $t['ink'] }};">{{ now()->format('d.m.Y') }}</td>
                            </tr></table>
                            <div style="border-top:3px solid {{ $t['ink'] }};margin-top:16px;line-height:0;font-size:0;">&nbsp;</div>
                        </td></tr>
                        @break
                    @case('editorial')
                        <tr><td align="center" style="padding:6px 0 22px;">
                            <div style="border-top:1px solid {{ $t['ink'] }};border-bottom:1px solid {{ $t['ink'] }};padding:3px 0;font-size:0;line-height:0;">&nbsp;</div>
                            <p class="net-mast" style="margin:16px 0 4px;font-family:{{ $t['head_font'] }};font-size:40px;line-height:1.05;font-weight:700;letter-spacing:-1px;color:{{ $t['ink'] }};">{{ $studio->name }}</p>
                            <p style="margin:0 0 14px;font-family:Georgia,serif;font-style:italic;font-size:14px;color:{{ $t['muted'] }};">{{ $studio->tagline ?: 'Thư gửi bạn từ studio' }}</p>
                            <div style="border-top:3px double {{ $t['ink'] }};font-size:0;line-height:0;">&nbsp;</div>
                        </td></tr>
                        @break
                    @case('swatch')
                        <tr><td style="padding:0 0 16px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;border-radius:6px;border:1px solid {{ $t['line'] }};">
                                <tr><td style="background:{{ $t['accent'] }};border-radius:6px 6px 0 0;padding:26px 24px 34px;">
                                    <p style="margin:0;font-family:{{ $t['font'] }};font-size:26px;line-height:1.1;font-weight:800;color:{{ $t['on_accent'] }};">{{ $studio->name }}</p>
                                </td></tr>
                                <tr><td style="padding:10px 14px 4px;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                                        <td style="font-family:{{ $t['eyebrow_font'] }};font-size:12px;font-weight:600;color:{{ $t['ink'] }};">{{ strtoupper($t['accent']) }}</td>
                                        <td align="right" style="font-family:{{ $t['eyebrow_font'] }};font-size:11px;color:{{ $t['muted'] }};">{{ $studio->slug }}</td>
                                    </tr></table>
                                </td></tr>
                                <tr><td style="padding:6px 14px 14px;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                                        @foreach($t['shades'] as $sh)
                                            <td style="background:{{ $sh['hex'] }};height:18px;font-size:0;line-height:0;{{ $loop->first ? 'border-radius:3px 0 0 3px;' : '' }}{{ $loop->last ? 'border-radius:0 3px 3px 0;' : '' }}">&nbsp;</td>
                                        @endforeach
                                    </tr></table>
                                </td></tr>
                            </table>
                        </td></tr>
                        @break
                    @default
                        <tr><td style="padding:0 4px 16px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                                <td style="padding-right:12px;">
                                    @if($avatarUrl)
                                        <img src="{{ $avatarUrl }}" width="44" height="44" alt="" style="display:block;width:44px;height:44px;border-radius:50%;object-fit:cover;border:0;">
                                    @else
                                        <div style="width:44px;height:44px;border-radius:50%;background:{{ $t['accent'] }};color:{{ $t['on_accent'] }};font-family:{{ $t['font'] }};font-size:16px;font-weight:800;line-height:44px;text-align:center;">{{ $studio->initials() }}</div>
                                    @endif
                                </td>
                                <td style="font-family:{{ $k === 'sketch' ? $t['head_font'] : $t['font'] }};font-size:{{ $k === 'sketch' ? '22px' : '16px' }};font-weight:{{ $k === 'sketch' ? '400' : '700' }};color:{{ $t['ink'] }};">{{ $studio->name }}
                                    @if($studio->tagline)<div style="font-family:{{ $t['font'] }};font-size:13px;font-weight:400;color:{{ $t['muted'] }};margin-top:2px;">{{ \Illuminate\Support\Str::limit($studio->tagline, 70) }}</div>@endif
                                </td>
                            </tr></table>
                        </td></tr>
                @endswitch

                {{-- ===== Thân thư ===== --}}
                <tr><td style="background:{{ $t['card_bg'] }};border:{{ $t['card_border'] }};border-radius:{{ $t['radius'] }};{{ $k === 'riso' ? 'box-shadow:6px 6px 0 '.$t['second'].';' : '' }}">
                    @if($k === 'artboard')
                        {{-- Tay nắm khung chọn như phần mềm thiết kế --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                            <td style="font-size:0;line-height:0;"><div style="width:8px;height:8px;background:#fff;border:1px solid {{ $t['accent'] }};margin:-5px 0 0 -5px;">&nbsp;</div></td>
                            <td align="right" style="font-size:0;line-height:0;"><div style="width:8px;height:8px;background:#fff;border:1px solid {{ $t['accent'] }};margin:-5px -5px 0 0;">&nbsp;</div></td>
                        </tr></table>
                    @endif
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                        <tr><td class="net-pad" style="padding:{{ $cardPad }};">
                            @if($c->eyebrow)<p style="{{ $eyebrowStyle }}">{{ $c->eyebrow }}</p>@endif
                            <h1 class="net-head" style="{{ $headStyle }}">{{ $c->heading }}</h1>
                            @if($k === 'sketch')<div style="width:84px;border-top:3px solid {{ $t['accent'] }};border-radius:3px;margin:-6px 0 18px;font-size:0;line-height:0;">&nbsp;</div>@endif
                            @if($k === 'swiss')<div style="width:56px;border-top:6px solid {{ $t['accent'] }};margin:0 0 20px;font-size:0;line-height:0;">&nbsp;</div>@endif

                            @if($c->greeting)<p style="{{ $p }}color:{{ $t['ink'] }};">{{ $c->greeting }}</p>@endif
                            @foreach($c->paragraphs as $para)
                                <p style="{{ $p }}">{!! EC::inline($para, $t['accent_ink']) !!}</p>
                            @endforeach

                            @if($c->note)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 18px;">
                                    <tr><td style="background:{{ $t['soft'] }};border-left:4px solid {{ $t['accent'] }};{{ $k === 'riso' || $k === 'sketch' ? '' : 'border-radius:0 8px 8px 0;' }}padding:14px 16px;">
                                        <p style="margin:0 0 4px;font-family:{{ $t['eyebrow_font'] }};font-size:12px;font-weight:700;color:{{ $t['accent_ink'] }};">Lời nhắn từ {{ $studio->name }}</p>
                                        <p style="margin:0;font-family:{{ $k === 'editorial' ? 'Georgia,serif' : $t['font'] }};{{ $k === 'editorial' ? 'font-style:italic;' : '' }}font-size:15px;line-height:1.6;color:{{ $t['ink'] }};">{!! EC::inline($c->note, $t['accent_ink']) !!}</p>
                                    </td></tr>
                                </table>
                            @endif

                            @if($c->facts)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 20px;border-top:1px solid {{ $t['line'] }};">
                                    @foreach($c->facts as [$label, $value])
                                        <tr>
                                            <td style="padding:9px 12px 9px 0;border-bottom:1px solid {{ $t['line'] }};font-family:{{ $t['font'] }};font-size:13px;color:{{ $t['muted'] }};width:38%;vertical-align:top;">{{ $label }}</td>
                                            <td style="padding:9px 0;border-bottom:1px solid {{ $t['line'] }};font-family:{{ $t['font'] }};font-size:14px;font-weight:600;color:{{ $t['ink'] }};vertical-align:top;">{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            @if($c->steps && $c->steps['current'] >= 0)
                                @php $n = count($c->steps['labels']); @endphp
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:2px 0 24px;">
                                    <tr>
                                        @foreach($c->steps['labels'] as $i => $label)
                                            @php $done = $i < $c->steps['current']; $now = $i === $c->steps['current']; @endphp
                                            <td width="{{ floor(100 / $n) }}%" style="padding:0 2px;vertical-align:top;">
                                                <div style="height:6px;line-height:6px;font-size:0;border-radius:{{ $t['radius'] === '0' ? '0' : '3px' }};background:{{ $done || $now ? $t['accent'] : $t['line'] }};">&nbsp;</div>
                                                <div class="net-step-label" style="padding-top:6px;font-family:{{ $t['font'] }};font-size:11px;line-height:1.3;color:{{ $now ? $t['ink'] : $t['muted'] }};font-weight:{{ $now ? '700' : '400' }};">{{ $label }}</div>
                                            </td>
                                        @endforeach
                                    </tr>
                                </table>
                            @endif

                            @if($c->button)
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 22px;">
                                    <tr><td>
                                        <a href="{{ $c->button[1] }}" style="{{ $btnStyle }}" target="_blank" rel="noopener">{{ $c->button[0] }}{{ $k === 'swiss' ? '  →' : '' }}</a>
                                    </td></tr>
                                </table>
                                <p style="margin:0 0 18px;font-family:{{ $t['font'] }};font-size:12px;line-height:1.5;color:{{ $t['muted'] }};">Nút không bấm được? Mở link: <a href="{{ $c->button[1] }}" style="color:{{ $t['accent_ink'] }};word-break:break-all;">{{ $c->button[1] }}</a></p>
                            @endif

                            @foreach($c->after as $para)
                                <p style="{{ $p }}">{!! EC::inline($para, $t['accent_ink']) !!}</p>
                            @endforeach

                            <p style="margin:22px 0 0;font-family:{{ $k === 'sketch' ? $t['head_font'] : $t['font'] }};font-size:{{ $k === 'sketch' ? '19px' : '15px' }};line-height:1.55;color:{{ $t['ink'] }};">{!! nl2br(e($c->signoff ?? ('Cảm ơn bạn,'."\n".$studio->name)), false) !!}</p>
                            @if($signature)<p style="margin:6px 0 0;font-family:{{ $t['font'] }};font-size:13px;line-height:1.55;color:{{ $t['muted'] }};">{!! EC::inline($signature, $t['accent_ink']) !!}</p>@endif
                        </td></tr>
                    </table>
                </td></tr>

                {{-- ===== Chân thư ===== --}}
                <tr><td align="center" class="net-pad" style="padding:22px 24px 0;font-family:Helvetica,Arial,sans-serif;font-size:12px;line-height:1.6;color:#8a8a92;">
                    @php $contacts = array_filter([$studio->phone ? 'Zalo '.$studio->phone : null, $studio->email]); @endphp
                    @if($contacts)<p style="margin:0 0 8px;">{{ $studio->name }} · {{ implode(' · ', $contacts) }}</p>@endif
                    @if($c->footer)<p style="margin:0 0 8px;">{!! EC::inline($c->footer, '#6b6b74') !!}</p>@endif
                    <p style="margin:0;">Gửi qua <a href="{{ route('home') }}" style="color:#6b6b74;font-weight:700;text-decoration:none;">Nét</a> — quản lý đơn thiết kế</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
