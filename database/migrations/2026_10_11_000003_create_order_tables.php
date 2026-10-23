<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 20);                            // N0001 — duy nhất trong 1 studio
            $table->string('title', 160);
            $table->string('status', 20)->default('new');
            $table->string('priority', 10)->default('normal');
            $table->string('source', 10)->default('manual');       // manual | form (khách tự đặt)
            $table->string('cover_path')->nullable();

            // Brief.
            $table->text('brief');
            $table->string('style', 500)->nullable();              // phong cách mong muốn
            $table->string('colors', 255)->nullable();
            $table->string('specs', 500)->nullable();              // kích thước, định dạng, số lượng
            $table->text('copy_text')->nullable();                 // nội dung chữ trên thiết kế
            $table->string('budget_note', 120)->nullable();        // ngân sách khách nêu khi đặt

            $table->date('due_on')->nullable();                    // hạn bàn giao đã chốt
            $table->date('client_due_on')->nullable();             // hạn khách mong muốn khi đặt
            $table->unsignedTinyInteger('revision_rounds')->default(2);
            $table->unsignedTinyInteger('deposit_percent')->default(50);
            $table->boolean('lock_delivery')->default(true);       // giấu file bàn giao tới khi thu đủ

            $table->timestamp('quote_sent_at')->nullable();
            $table->timestamp('quote_accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();

            $table->string('tracking_token', 40)->unique();
            $table->text('internal_note')->nullable();              // ghi chú riêng — khách không thấy
            $table->decimal('total_amount', 14, 2)->default(0);    // cache = tổng order_charges
            $table->decimal('paid_amount', 14, 2)->default(0);     // cache = tổng payments
            $table->timestamps();

            $table->unique(['studio_id', 'code']);
            $table->index(['studio_id', 'status']);
            $table->index(['studio_id', 'due_on']);
        });

        Schema::create('order_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12)->default('base');
            $table->string('label', 160);
            $table->decimal('amount', 14, 2);                       // luôn dương — 'discount' được trừ khi cộng tổng
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('order_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12)->default('reference');      // reference | source | other
            $table->string('label', 160)->nullable();
            $table->string('url', 2000);
            $table->boolean('from_client')->default(false);
            $table->timestamps();
        });

        // Vòng gửi phương án: V1, V2 (sau góp ý lần 1)...
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->text('note')->nullable();                      // lời nhắn kèm khi gửi
            $table->string('status', 20)->default('draft');        // draft | sent | approved | changes
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('response_note')->nullable();             // góp ý chung của khách khi duyệt / yêu cầu sửa
            $table->timestamps();

            $table->unique(['order_id', 'number']);
        });

        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80);                           // "Phương án A" / "Hướng tối giản"
            $table->text('note')->nullable();
            $table->string('drive_url', 2000)->nullable();
            $table->boolean('is_chosen')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('proposal_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('width')->default(0);
            $table->unsignedSmallInteger('height')->default(0);
            $table->unsignedInteger('bytes')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Góp ý: của khách (trên trang theo dõi) hoặc của bạn. Có thể ghim vào 1 điểm trên ảnh (toạ độ %).
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('round_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('proposal_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('image_id')->nullable()->constrained('proposal_images')->cascadeOnDelete();
            $table->string('author', 10)->default('client');       // client | designer
            $table->text('body');
            $table->decimal('pin_x', 5, 2)->nullable();
            $table->decimal('pin_y', 5, 2)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'round_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_on');
            $table->string('method', 15)->default('chuyen_khoan');
            $table->boolean('is_deposit')->default(false);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['studio_id', 'paid_on']);
        });

        // Dòng thời gian của đơn. visible = khách có thấy trên trang theo dõi không.
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('actor', 10)->default('designer');      // designer | client | system
            $table->boolean('visible')->default(true);
            $table->string('message', 500);
            $table->timestamp('created_at')->nullable();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('proposal_images');
        Schema::dropIfExists('proposals');
        Schema::dropIfExists('rounds');
        Schema::dropIfExists('order_links');
        Schema::dropIfExists('order_charges');
        Schema::dropIfExists('orders');
    }
};
