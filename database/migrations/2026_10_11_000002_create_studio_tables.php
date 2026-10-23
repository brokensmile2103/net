<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mỗi Studio (1 freelancer) CHÍNH LÀ 1 tenant — mọi bảng nghiệp vụ đều có studio_id (trực tiếp hoặc qua đơn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 40)->unique();                 // địa chỉ trang đặt đơn: /dat/{slug}
            $table->string('tagline', 160)->nullable();           // VD: "Logo & nhận diện cho quán nhỏ"
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('contact_url')->nullable();            // Zalo / Facebook / Behance...
            $table->string('accent_color', 20)->default('magenta');
            $table->boolean('is_active')->default(true);

            // Trang đặt đơn công khai.
            $table->boolean('order_form_enabled')->default(true);
            $table->text('order_form_note')->nullable();          // lời dặn hiện đầu form đặt đơn
            $table->text('policy')->nullable();                   // quy định: cọc, số lần sửa, bàn giao — hiện ở form & trang theo dõi

            // Mặc định cho đơn mới.
            $table->unsignedTinyInteger('default_deposit_percent')->default(50);
            $table->unsignedTinyInteger('default_revision_rounds')->default(2);
            $table->boolean('lock_delivery_until_paid')->default(true);

            // Nhận chuyển khoản (mã VietQR trên trang theo dõi).
            $table->string('bank_code', 20)->nullable();
            $table->string('bank_account', 40)->nullable();
            $table->string('bank_holder', 100)->nullable();

            $table->unsignedInteger('order_seq')->default(0);    // số thứ tự để sinh mã đơn N0001
            $table->unsignedBigInteger('storage_bytes')->default(0); // cache tổng dung lượng ảnh
            $table->timestamps();
        });

        // Danh mục dịch vụ & bảng giá (hiện trên trang đặt đơn).
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2)->nullable();          // null = báo giá sau
            $table->boolean('price_is_from')->default(false);     // hiện "từ 1.500.000đ"
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->unsignedTinyInteger('revision_rounds')->nullable();  // null = theo mặc định studio
            $table->unsignedTinyInteger('deposit_percent')->nullable();  // null = theo mặc định studio
            $table->text('brief_hint')->nullable();               // gợi ý khách cần chuẩn bị gì cho brief
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['studio_id', 'is_public']);
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('company', 120)->nullable();
            $table->string('contact_url')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['studio_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
        Schema::dropIfExists('services');
        Schema::dropIfExists('studios');
    }
};
