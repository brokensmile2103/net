<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nội dung chuyển khoản điền sẵn trong mã VietQR — cho phép tuỳ chỉnh.
 * Giá trị mặc định chính là nội dung trước đây (các dòng đã có cũng được điền mặc định);
 * để trống (NULL) = không điền sẵn, ngân hàng tự xử lý như bình thường.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studios', function (Blueprint $table) {
            $table->string('bank_memo', 50)->nullable()->default('NET {code}');
        });
    }

    public function down(): void
    {
        Schema::table('studios', function (Blueprint $table) {
            $table->dropColumn('bank_memo');
        });
    }
};
