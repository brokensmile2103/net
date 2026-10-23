<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StudioProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Luồng cài đặt kiểu WordPress — chạy hoàn toàn qua trình duyệt, KHÔNG cần SSH/CLI,
 * để tương thích với hosting giá rẻ (cPanel) là môi trường phổ biến nhất của khách hàng mục tiêu.
 *
 * Các bước: welcome -> requirements -> database -> (đợi F5 để nạp config mới) -> account -> finish
 */
class InstallController extends Controller
{
    public function welcome(): View
    {
        return view('install.welcome');
    }

    public function requirements(): View
    {
        $checks = [
            'PHP >= 8.3' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'Extension pdo_mysql' => extension_loaded('pdo_mysql'),
            'Extension mbstring' => extension_loaded('mbstring'),
            'Extension openssl' => extension_loaded('openssl'),
            'Extension tokenizer' => extension_loaded('tokenizer'),
            'Extension ctype' => extension_loaded('ctype'),
            'Extension fileinfo (nhận dạng ảnh tải lên)' => extension_loaded('fileinfo'),
            'Extension gd (nén ảnh bản thảo)' => extension_loaded('gd'),
            'GD hỗ trợ WebP (khuyến nghị, nếu không sẽ dùng JPEG)' => extension_loaded('gd') && function_exists('imagewebp'),
            'Thư mục storage/ ghi được' => is_writable(storage_path()),
            'Thư mục bootstrap/cache ghi được' => is_writable(base_path('bootstrap/cache')),
            'File .env ghi được' => is_writable(base_path('.env')) || is_writable(base_path()),
        ];

        // Mục "khuyến nghị" chỉ để báo, không chặn cài đặt.
        $allPassed = ! in_array(false, array_filter($checks, fn ($label) => ! str_contains($label, 'khuyến nghị'), ARRAY_FILTER_USE_KEY), true);

        return view('install.requirements', compact('checks', 'allPassed'));
    }

    public function showDatabaseForm(): View
    {
        return view('install.database');
    }

    /**
     * Test kết nối DB, ghi vào .env, sinh APP_KEY, rồi chạy migrate ngay
     * (Artisan::call chạy được qua request web, không cần quyền CLI trên server).
     */
    public function saveDatabase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'db_host' => 'required|string',
            'db_port' => 'required|string',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
        ]);

        try {
            config([
                'database.connections.mysql.host' => $data['db_host'],
                'database.connections.mysql.port' => $data['db_port'],
                'database.connections.mysql.database' => $data['db_database'],
                'database.connections.mysql.username' => $data['db_username'],
                'database.connections.mysql.password' => $data['db_password'] ?? '',
            ]);

            \DB::purge('mysql');
            \DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            // Thông báo bên dưới hứa "chi tiết lỗi đã ghi vào log" — trước đây lỗi không hề được ghi.
            \Log::warning('Cài đặt: không kết nối được database: '.$e->getMessage());

            return back()->withInput()->withErrors([
                'db_host' => 'Không kết nối được database. Kiểm tra lại thông tin (chi tiết lỗi đã ghi vào log).',
            ]);
        }

        $this->writeEnv([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => $data['db_password'] ?? '',
            // Địa chỉ thật của website (link trong email đặt lại mật khẩu, sitemap…) — .env mẫu để http://localhost:8000.
            'APP_URL' => $request->getSchemeAndHttpHost(),
        ]);

        if (empty(env('APP_KEY'))) {
            Artisan::call('key:generate', ['--force' => true]);
        }

        Artisan::call('config:clear');
        Artisan::call('migrate', ['--force' => true]);

        // Tạo symlink public/storage -> storage/app/public để ảnh upload (món, sau
        // này có thể có thêm avatar...) truy cập công khai được. Một số hosting rẻ
        // tắt hàm symlink() vì lý do bảo mật — không để lỗi này chặn cả quá trình cài,
        // chỉ ghi log, người dùng có thể tự tạo bằng tay theo hướng dẫn trong README.
        try {
            Artisan::call('storage:link');
        } catch (\Throwable $e) {
            \Log::warning('Không thể tự tạo storage:link, cần tạo thủ công: '.$e->getMessage());
        }

        return redirect()->route('install.account');
    }

    public function showAccountForm(): View
    {
        return view('install.account');
    }

    public function saveAccount(Request $request): RedirectResponse
    {
        $request->merge(['phone' => normalize_phone($request->input('phone'))]);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?\d{8,15}$/', 'unique:users,phone'],
            'password' => 'required|string|min:8|confirmed',
            'studio_name' => 'nullable|string|max:100',
        ]);

        // Tài khoản tạo ở bước cài đặt là SUPER ADMIN quản lý cả hệ thống, đồng thời là chủ của studio đầu tiên —
        // dùng được ngay mọi tính năng mà không cần tạo thêm tài khoản khác. Freelancer khác tự đăng ký qua /dang-ky
        // (Super Admin có thể đóng đăng ký trong Cài đặt hệ thống).
        [$admin, $studio] = DB::transaction(function () use ($data) {
            $admin = User::forceCreate([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role' => 'platform_admin',
                'is_active' => true,
            ]);

            $studio = app(StudioProvisioner::class)->createFor($admin, trim($data['studio_name'] ?? '') ?: 'Studio của '.$data['name']);

            return [$admin, $studio];
        });

        file_put_contents(storage_path('installed.lock'), now()->toDateTimeString());

        auth()->login($admin);

        return redirect()->route('install.finish');
    }

    public function finish(): View
    {
        return view('install.finish');
    }

    /**
     * Giá trị an toàn cho file .env: mật khẩu chứa khoảng trắng, #, $, dấu nháy… phải được bọc nháy,
     * nếu không phần sau dấu # bị coi là chú thích và "${...}" bị hiểu thành biến.
     */
    private static function envValue(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+-]+$/', $value)) {
            return $value;
        }

        // Nháy đơn: giữ nguyên từng ký tự, không thay biến — dùng được khi giá trị không chứa nháy đơn.
        if (! str_contains($value, "'") && ! str_contains($value, "\n")) {
            return "'".$value."'";
        }

        return '"'.str_replace(['\\', '"', '$', "\n"], ['\\\\', '\\"', '\\$', '\\n'], $value).'"';
    }

    private function writeEnv(array $values): void
    {
        $envPath = base_path('.env');
        $content = file_exists($envPath) ? file_get_contents($envPath) : file_get_contents(base_path('.env.example'));

        foreach ($values as $key => $value) {
            $escaped = preg_quote($key, '/');
            $line = $key.'='.self::envValue((string) $value);

            // preg_replace_callback: giá trị chứa "$1", "\\" (VD trong mật khẩu) không bị hiểu thành tham chiếu regex.
            if (preg_match("/^{$escaped}=.*/m", $content)) {
                $content = preg_replace_callback("/^{$escaped}=.*/m", fn () => $line, $content);
            } else {
                $content = rtrim($content, "\n")."\n{$line}\n";
            }
        }

        file_put_contents($envPath, $content);
    }
}
