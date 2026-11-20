<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nét v6.2:
 *  - Thống kê trang đặt đơn: bảng page_visits (1 dòng / người xem / ngày — không cookie, không lưu IP), nguồn của đơn & hẹn
 *    (orders.ref_source, consultations.ref_source).
 *  - Google Analytics riêng của studio: studios.ga_measurement_id (chỉ lưu mã G-…, Nét tự sinh đoạn mã).
 *  - Email gửi khách: studios.email_prefs (tên người gửi, email nhận trả lời, chế độ thử, email theo giai đoạn),
 *    bảng email_logs (hàng chờ + nhật ký gửi của TỪNG studio — đếm hạn mức, xem lại đã gửi gì).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('page_visits')) {
            Schema::create('page_visits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
                $table->date('day');
                $table->char('visitor', 16);              // dấu vân tay đổi theo ngày (không đảo ngược được ra IP)
                $table->string('source', 20);             // facebook | zalo | instagram | tiktok | google | ... | direct | other
                $table->string('device', 10);             // mobile | tablet | desktop
                $table->unsignedInteger('views')->default(1);
                $table->unique(['studio_id', 'day', 'visitor']);
                $table->index(['studio_id', 'day']);
            });
        }

        if (! Schema::hasTable('email_logs')) {
            Schema::create('email_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->string('kind', 30);               // stage:review | reminder:quote | consult | test
                $table->string('to_email');
                $table->string('subject')->nullable();
                $table->string('status', 12);             // pending | sent | sandbox | failed | skipped | cancelled
                $table->string('note')->nullable();       // lý do bỏ qua / lỗi / lời nhắn thêm
                $table->text('payload')->nullable();      // dữ liệu kèm (VD lời nhắn riêng khi gửi tay)
                $table->timestamp('send_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->index(['studio_id', 'created_at']);
                $table->index(['status', 'send_at']);
                $table->index(['order_id', 'kind']);
            });
        }

        Schema::table('studios', function (Blueprint $table) {
            if (! Schema::hasColumn('studios', 'ga_measurement_id')) {
                $table->string('ga_measurement_id', 20)->nullable();
            }
            if (! Schema::hasColumn('studios', 'email_prefs')) {
                $table->text('email_prefs')->nullable();
            }
        });
        foreach (['orders', 'consultations'] as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (! Schema::hasColumn($t, 'ref_source')) {
                    $table->string('ref_source', 20)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('page_visits');
        foreach (['studios' => ['ga_measurement_id', 'email_prefs'], 'orders' => ['ref_source'], 'consultations' => ['ref_source']] as $t => $cols) {
            Schema::table($t, function (Blueprint $table) use ($t, $cols) {
                foreach ($cols as $c) {
                    if (Schema::hasColumn($t, $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
