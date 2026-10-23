<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng việc v5:
 *  - orders.status_changed_at: lúc đơn vào giai đoạn hiện tại → báo "đứng yên N ngày" trên thẻ, lọc đơn đứng yên lâu.
 *    Đơn có sẵn lấy tạm updated_at.
 *  - studios.board_prefs: tuỳ chọn Bảng việc của studio (cột ẩn / thu gọn, chế độ xem, sắp xếp, giới hạn số đơn mỗi cột…),
 *    lưu trên máy chủ để mở máy khác vẫn y như cũ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'status_changed_at')) {
                $table->timestamp('status_changed_at')->nullable()->after('status');
            }
        });
        DB::table('orders')->whereNull('status_changed_at')->update(['status_changed_at' => DB::raw('updated_at')]);

        Schema::table('studios', function (Blueprint $table) {
            if (! Schema::hasColumn('studios', 'board_prefs')) {
                $table->text('board_prefs')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'status_changed_at')) {
                $table->dropColumn('status_changed_at');
            }
        });
        Schema::table('studios', function (Blueprint $table) {
            if (Schema::hasColumn('studios', 'board_prefs')) {
                $table->dropColumn('board_prefs');
            }
        });
    }
};
