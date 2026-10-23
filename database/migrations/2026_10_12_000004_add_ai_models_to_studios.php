<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mô hình Gemini studio tự chọn (theo thứ tự thử). Trống = dùng mặc định của hệ thống (NET_AI_MODELS). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('studios', 'ai_models')) {
            Schema::table('studios', function (Blueprint $table) {
                $table->text('ai_models')->nullable()->after('gemini_api_key');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('studios', 'ai_models')) {
            Schema::table('studios', function (Blueprint $table) {
                $table->dropColumn('ai_models');
            });
        }
    }
};
