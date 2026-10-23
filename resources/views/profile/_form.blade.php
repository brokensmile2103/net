{{-- Dùng chung cho freelancer (layouts.app) và Super Admin (layouts.admin). --}}
<div class="space-y-6">
    <section class="rounded-lg border border-[var(--line)] bg-white p-5 md:p-6" aria-labelledby="h-me">
        <h2 id="h-me" class="font-display text-lg font-bold tracking-tight">Thông tin đăng nhập</h2>
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4 mt-4">
            @csrf @method('PUT')
            <x-input name="name" label="Tên của bạn" :value="old('name', $user->name)" required autocomplete="name" maxlength="100" />
            <div>
                <p class="block text-sm font-medium text-[var(--ink-2)] mb-1.5">Số điện thoại đăng nhập</p>
                <p class="rounded-lg border border-[var(--line)] bg-stone-50 text-[var(--muted)] py-2.5 px-3.5 tnum">{{ $user->phone }}</p>
                <p class="mt-1 text-xs text-[var(--muted)]">Không đổi được ở đây — liên hệ quản trị nếu cần.</p>
            </div>
            <x-input name="email" type="email" label="Email khôi phục" :value="old('email', $user->email)" autocomplete="email" placeholder="ban@example.com"
                     hint="Dùng để lấy lại mật khẩu khi quên. Chưa điền thì không dùng được “Quên mật khẩu”." />
            <x-button icon="fa-check">Lưu</x-button>
        </form>
    </section>

    @if(\App\Http\Controllers\Auth\GoogleAuthController::enabled() || $user->google_id)
        <section class="rounded-lg border border-[var(--line)] bg-white p-5 md:p-6" aria-labelledby="h-google">
            <h2 id="h-google" class="font-display text-lg font-bold tracking-tight">Đăng nhập bằng Google</h2>
            @error('google')<p class="mt-3 rounded-lg border border-red-200 bg-red-50 text-red-700 px-3 py-2 text-sm" role="alert">{{ $message }}</p>@enderror
            @if($user->google_id)
                <p class="text-sm text-[var(--ink-2)] mt-2"><i class="fa-solid fa-circle-check text-emerald-600 mr-1" aria-hidden="true"></i>Đã liên kết — bấm “Tiếp tục với Google” ở trang đăng nhập là vào thẳng.</p>
                @if($user->has_password)
                    <form method="POST" action="{{ route('profile.google.unlink') }}" class="mt-3" onsubmit="return confirm('Gỡ liên kết Google? Bạn vẫn đăng nhập được bằng số điện thoại và mật khẩu.')">
                        @csrf @method('DELETE')
                        <x-button size="sm" variant="outline">Gỡ liên kết</x-button>
                    </form>
                @else
                    <p class="text-xs text-[var(--muted)] mt-2">Muốn gỡ liên kết, hãy đặt mật khẩu trước để không bị khoá ngoài.</p>
                @endif
            @else
                <p class="text-sm text-[var(--muted)] mt-2">Liên kết tài khoản Google để đăng nhập bằng một chạm, khỏi nhớ mật khẩu.</p>
                <x-button size="sm" variant="outline" class="mt-3" :href="route('auth.google', ['lien-ket' => 1])"><i class="fa-brands fa-google" aria-hidden="true"></i>Liên kết Google</x-button>
            @endif
        </section>
    @endif

    <section class="rounded-lg border border-[var(--line)] bg-white p-5 md:p-6" aria-labelledby="h-pw"
             x-data="{ open: {{ $errors->has('current_password') || $errors->has('password') ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open" :aria-expanded="open" class="w-full flex items-center gap-2 text-left">
            <h2 id="h-pw" class="font-display text-lg font-bold tracking-tight">{{ $user->has_password ? 'Đổi mật khẩu' : 'Đặt mật khẩu' }}</h2>
            <i class="fa-solid fa-chevron-down text-xs text-[var(--muted)] ml-auto transition" :class="open && 'rotate-180'" aria-hidden="true"></i>
        </button>
        <form x-show="open" x-cloak method="POST" action="{{ route('profile.password') }}" class="space-y-4 mt-4">
            @csrf @method('PUT')
            @if($user->has_password)
                <x-input type="password" name="current_password" label="Mật khẩu hiện tại" required autocomplete="current-password" />
            @else
                <p class="text-sm text-[var(--muted)]">Bạn đang đăng nhập bằng Google. Đặt thêm mật khẩu để đăng nhập được bằng số điện thoại khi cần.</p>
            @endif
            <x-input type="password" name="password" label="Mật khẩu mới (tối thiểu 8 ký tự)" required autocomplete="new-password" minlength="8" />
            <x-input type="password" name="password_confirmation" label="Nhập lại mật khẩu mới" required autocomplete="new-password" />
            <x-button variant="ink" icon="fa-key">{{ $user->has_password ? 'Đổi mật khẩu' : 'Đặt mật khẩu' }}</x-button>
        </form>
    </section>

    @if($user->isOwner() && ! $user->isPlatformAdmin())
        <section id="xoa-tai-khoan" class="rounded-lg border border-red-200 bg-white p-5 md:p-6 scroll-mt-20" aria-labelledby="h-del"
                 x-data="{ open: {{ $errors->has('delete_password') || $errors->has('confirmation') ? 'true' : 'false' }}, text: '' }"
                 x-init="if (location.hash === '#xoa-tai-khoan') open = true">
            <button type="button" @click="open = !open" :aria-expanded="open" class="w-full flex items-center gap-2 text-left text-red-700">
                <h2 id="h-del" class="font-display text-lg font-bold tracking-tight">Xoá tài khoản</h2>
                <i class="fa-solid fa-chevron-down text-xs ml-auto transition" :class="open && 'rotate-180'" aria-hidden="true"></i>
            </button>
            <div x-show="open" x-cloak class="mt-4 space-y-4">
                <p class="text-sm text-[var(--ink-2)] leading-relaxed">Xoá <strong>vĩnh viễn</strong> tài khoản cùng studio của bạn: toàn bộ đơn, khách, bản thảo, ảnh đã tải lên, lịch sử thu tiền. Link theo dõi đã gửi cho khách sẽ ngừng hoạt động. <strong>Không thể khôi phục.</strong></p>
                <p class="text-sm text-[var(--muted)]">File trên Google Drive của bạn không bị ảnh hưởng.</p>
                <a href="{{ route('settings.export') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--accent-ink)]"><i class="fa-solid fa-download" aria-hidden="true"></i>Tải dữ liệu về trước khi xoá</a>
                <form method="POST" action="{{ route('profile.destroy') }}" class="space-y-4"
                      onsubmit="return confirm('Xoá VĨNH VIỄN tài khoản và toàn bộ dữ liệu? Không thể hoàn tác.')">
                    @csrf @method('DELETE')
                    @if($user->has_password)
                        <x-input type="password" name="delete_password" label="Mật khẩu hiện tại" required autocomplete="current-password" />
                    @endif
                    <div>
                        <label for="confirmation" class="block text-sm font-medium text-[var(--ink-2)] mb-1.5">Gõ <span class="font-mono font-bold text-red-600">XOA TAI KHOAN</span> để xác nhận</label>
                        <input type="text" name="confirmation" id="confirmation" x-model="text" autocomplete="off" placeholder="XOA TAI KHOAN"
                               class="w-full rounded-lg border border-[var(--line-strong)] bg-white py-2.5 px-3.5 font-mono focus:outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-400">
                        @error('confirmation')<p class="mt-1.5 text-xs text-red-600"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i>{{ $message }}</p>@enderror
                    </div>
                    <x-button variant="danger" icon="fa-trash" x-bind:disabled="text.trim() !== 'XOA TAI KHOAN'">Xoá vĩnh viễn tài khoản</x-button>
                </form>
            </div>
        </section>
    @endif
</div>
