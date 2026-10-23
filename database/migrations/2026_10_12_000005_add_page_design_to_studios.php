<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giao diện trang đặt đơn của studio: mẫu (theme), màu nhấn tự chọn (mã HEX), câu chào lớn, ảnh bìa,
 * và bảng "Tác phẩm tiêu biểu" (portfolio) hiện ngay trên trang đặt đơn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studios', function (Blueprint $table) {
            if (! Schema::hasColumn('studios', 'page_theme')) {
                $table->string('page_theme', 20)->default('paper')->after('accent_color');
            }
            if (! Schema::hasColumn('studios', 'accent_custom')) {
                $table->string('accent_custom', 7)->nullable()->after('page_theme');
            }
            if (! Schema::hasColumn('studios', 'page_headline')) {
                $table->string('page_headline', 160)->nullable()->after('accent_custom');
            }
            if (! Schema::hasColumn('studios', 'cover_path')) {
                $table->string('cover_path')->nullable()->after('avatar_path');
            }
        });

        if (! Schema::hasTable('portfolio_items')) {
            Schema::create('portfolio_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
                $table->string('path');
                $table->unsignedInteger('width')->default(0);
                $table->unsignedInteger('height')->default(0);
                $table->unsignedInteger('bytes')->default(0);
                $table->string('caption', 120)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['studio_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_items');
        Schema::table('studios', function (Blueprint $table) {
            foreach (['page_theme', 'accent_custom', 'page_headline', 'cover_path'] as $col) {
                if (Schema::hasColumn('studios', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
