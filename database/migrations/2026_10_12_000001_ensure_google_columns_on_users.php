<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảo đảm bảng users có đủ cột cho đăng nhập Google.
 *
 * Các cột này từng được thêm thẳng vào migration tạo bảng users (0001_01_01_000000). Server cài Nét
 * TRƯỚC thay đổi đó sẽ không bao giờ có google_id / has_password (Laravel không chạy lại migration đã chạy),
 * nên bấm "Tiếp tục với Google" truy vấn cột không tồn tại → lỗi 500. Migration này chỉ thêm cột còn thiếu,
 * chạy trên DB mới cài thì không làm gì.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'has_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('has_password')->default(true)->after('password');
            });
        }

        if (! Schema::hasColumn('users', 'google_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('google_id')->nullable()->unique()->after('has_password');
            });
        }
    }

    public function down(): void
    {
        // Không gỡ: các cột này thuộc cấu trúc gốc của bảng users.
    }
};
