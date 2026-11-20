@extends('layouts.admin')
@section('title', 'Cài đặt hệ thống · Quản trị Nét')
@section('content')
    <h1 class="font-display text-2xl font-bold tracking-tight mb-6">Cài đặt hệ thống</h1>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-2xl bg-white rounded-lg border border-[var(--line)] p-4 sm:p-5 space-y-4">
        @csrf @method('PUT')

        <div>
            <p class="text-sm font-semibold mb-1">Đăng ký tài khoản mới</p>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="registration_open" value="0">
                <input type="checkbox" name="registration_open" value="1" @checked($registrationOpen) class="mt-1 w-4 h-4 rounded border-neutral-300">
                <span class="text-sm text-[var(--ink-2)] leading-relaxed">
                    Cho phép người lạ tự đăng ký
                    <span class="block text-xs text-[var(--muted)] mt-0.5">
                        Tắt nếu bạn chỉ tự dùng hoặc dùng trong nhóm nhỏ. Khi tắt: trang đăng ký bị vô hiệu hoá, đăng nhập Google chỉ dành cho tài khoản đã có trong hệ thống. Tài khoản hiện có vẫn đăng nhập bình thường.
                    </span>
                </span>
            </label>
        </div>

        <x-button variant="ink" icon="fa-floppy-disk">Lưu cài đặt</x-button>
    </form>

    {{-- Máy chủ gửi email dùng chung --}}
    <section id="email" class="max-w-2xl mt-8 bg-white rounded-lg border border-[var(--line)] p-4 sm:p-5 scroll-mt-20" aria-labelledby="h-mail">
        <div class="flex flex-wrap items-center gap-2">
            <h2 id="h-mail" class="font-display text-lg font-bold tracking-tight">Email gửi khách (Resend)</h2>
            <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ ['platform' => 'bg-emerald-50 text-emerald-700', 'env' => 'bg-sky-50 text-sky-700', 'none' => 'bg-amber-50 text-amber-800'][$mailSource] }}">
                {{ ['platform' => 'Đang dùng Resend cấu hình ở đây', 'env' => 'Đang dùng cấu hình trong .env ('.config('mail.default').')', 'none' => 'Chưa gửi được email'][$mailSource] }}
            </span>
        </div>
        <p class="text-sm text-[var(--muted)] mt-1 leading-relaxed">
            Resend chỉ cho gửi tới khách khi địa chỉ gửi thuộc <span class="font-semibold text-[var(--ink-2)]">tên miền đã xác thực</span> (onboarding@resend.dev chỉ gửi được cho chính chủ tài khoản Resend). Vì vậy mọi studio gửi qua key + tên miền của Nét: người gửi hiển thị là tên studio, khách trả lời về email của studio. Cấu hình ở đây ghi đè <code class="font-mono text-xs">MAIL_MAILER</code>/<code class="font-mono text-xs">RESEND_API_KEY</code> trong .env.
        </p>
        <ol class="mt-3 ml-5 list-decimal text-sm text-[var(--ink-2)] space-y-1">
            <li>Vào <a href="https://resend.com/domains" target="_blank" rel="noopener" class="font-semibold underline">resend.com/domains</a> → thêm tên miền (nên dùng tên miền phụ, VD <span class="font-mono text-xs">mail.{{ parse_url(url('/'), PHP_URL_HOST) }}</span>) → thêm các bản ghi DNS → chờ “Verified”.</li>
            <li>Tạo API key (Full access để Nét tự kiểm tra tên miền, hoặc Sending access).</li>
            <li>Dán key và địa chỉ gửi trên tên miền đó vào đây.</li>
        </ol>

        <form method="POST" action="{{ route('admin.settings.mail') }}" class="mt-5 space-y-4" autocomplete="off">
            @csrf @method('PUT')
            <div>
                <x-input name="resend_key" type="password" :label="$maskedKey ? 'API key Resend (đang dùng '.$maskedKey.' — để trống nếu không đổi)' : 'API key Resend'" placeholder="re_…" autocomplete="new-password" data-1p-ignore data-lpignore="true" data-bwignore spellcheck="false" maxlength="200" />
                @error('resend_key')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-input name="from_address" type="email" label="Địa chỉ gửi *" :value="old('from_address', $mail['from_address'] ?? config('mail.from.address'))" placeholder="thongbao@mail.ten-mien.vn" autocomplete="off" data-1p-ignore data-lpignore="true" required hint="Phải thuộc tên miền đã xác thực ở Resend." />
                <x-input name="from_name" label="Tên gửi email hệ thống" :value="old('from_name', $mail['from_name'])" placeholder="Nét" maxlength="80" autocomplete="off" data-1p-ignore data-lpignore="true" hint="Email đặt lại mật khẩu, báo lịch hẹn cho studio… Email gửi khách luôn mang tên studio." />
            </div>
            <label class="flex items-start gap-3">
                <input type="hidden" name="per_studio_address" value="0">
                <input type="checkbox" name="per_studio_address" value="1" @checked(old('per_studio_address', $mail['per_studio_address'])) class="mt-1 w-4 h-4 rounded border-neutral-300">
                @php $mailDomain = \App\Services\Mail\PlatformMail::domainOf($mail['from_address'] ?? config('mail.from.address')) ?? 'tên-miền'; @endphp
                <span class="text-sm">Mỗi studio một địa chỉ gửi riêng <span class="font-mono text-xs">{{ '{địa-chỉ-trang}@'.$mailDomain }}</span>
                    <span class="block text-xs text-[var(--muted)]">Cùng tên miền đã xác thực nên Resend vẫn nhận; khách dễ lọc thư theo studio hơn.</span></span>
            </label>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-input name="studio_daily" type="number" min="0" label="Hạn mức mỗi studio / ngày" autocomplete="off" :value="old('studio_daily', $mail['studio_daily'])" required hint="Từng studio sửa riêng được ở trang studio." />
                <x-input name="platform_daily" type="number" min="0" label="Trần cả hệ thống / ngày" autocomplete="off" :value="old('platform_daily', $mail['platform_daily'])" required hint="0 = không giới hạn. Resend miễn phí: 100/ngày, 3.000/tháng." />
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <x-button variant="ink" icon="fa-floppy-disk">Lưu & kiểm tra</x-button>
                @if($maskedKey)
                    <button type="submit" name="remove_key" value="1" class="text-sm text-[var(--muted)] hover:text-red-600" onclick="return confirm('Gỡ API key Resend?')">Gỡ key</button>
                @endif
            </div>
        </form>

        <div class="mt-5 pt-4 border-t border-[var(--line)] flex flex-wrap items-center gap-x-6 gap-y-3">
            <p class="text-sm"><span class="text-[var(--muted)]">Đã gửi hôm nay:</span> <span class="font-semibold tnum">{{ $platformToday }}</span>{{ $mail['platform_daily'] ? ' / '.$mail['platform_daily'] : '' }} · <span class="text-[var(--muted)]">tháng này:</span> <span class="font-semibold tnum">{{ $platformMonth }}</span></p>
            <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="flex flex-wrap items-center gap-2 ml-auto" autocomplete="off">@csrf
                <label class="sr-only" for="mail-test-to">Email nhận thư thử</label>
                <input id="mail-test-to" type="email" name="to" value="{{ auth()->user()->email }}" placeholder="email nhận thư thử" autocomplete="off" data-1p-ignore data-lpignore="true" class="rounded-md border border-[var(--line-strong)] px-2.5 py-1.5 text-sm">
                <x-button size="sm" variant="outline" icon="fa-paper-plane">Gửi email thử</x-button>
            </form>
        </div>
    </section>
@endsection
