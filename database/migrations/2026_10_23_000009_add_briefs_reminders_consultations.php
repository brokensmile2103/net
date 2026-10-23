<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nét v6:
 *  - Mẫu brief riêng cho từng dịch vụ: services.brief_questions (câu hỏi), orders.brief_answers (câu trả lời, lưu kèm câu hỏi lúc đặt).
 *  - Tự động nhắc khách qua email: studios.reminder_prefs, orders.reminders_muted (khách bấm "không nhận nhắc"), bảng order_reminders (đã gửi gì, cho ai, lúc nào).
 *  - Đặt lịch gọi tư vấn: studios.consult_prefs (bật/tắt, khung giờ…), bảng consultations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'brief_questions')) {
                $table->text('brief_questions')->nullable();
            }
        });
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'brief_answers')) {
                $table->text('brief_answers')->nullable();
            }
            if (! Schema::hasColumn('orders', 'reminders_muted')) {
                $table->boolean('reminders_muted')->default(false);
            }
        });
        Schema::table('studios', function (Blueprint $table) {
            if (! Schema::hasColumn('studios', 'reminder_prefs')) {
                $table->text('reminder_prefs')->nullable();
            }
            if (! Schema::hasColumn('studios', 'consult_prefs')) {
                $table->text('consult_prefs')->nullable();
            }
        });

        if (! Schema::hasTable('order_reminders')) {
            Schema::create('order_reminders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->string('kind', 20);                 // review | quote | payment
                $table->string('email');
                $table->timestamp('sent_at');
                $table->index(['order_id', 'kind']);
            });
        }

        if (! Schema::hasTable('consultations')) {
            Schema::create('consultations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name', 100);
                $table->string('phone', 20)->nullable();
                $table->string('email')->nullable();
                $table->string('facebook')->nullable();
                $table->string('channel', 20);              // zalo | phone | meet
                $table->text('note')->nullable();
                $table->dateTime('starts_at');
                $table->unsignedSmallInteger('duration');   // phút
                $table->string('status', 12)->default('booked'); // booked | done | cancelled
                $table->string('token', 40)->unique();
                $table->timestamps();
                $table->index(['studio_id', 'starts_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
        Schema::dropIfExists('order_reminders');
        foreach (['services' => ['brief_questions'], 'orders' => ['brief_answers', 'reminders_muted'], 'studios' => ['reminder_prefs', 'consult_prefs']] as $t => $cols) {
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
