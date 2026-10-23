<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** API key Gemini của từng studio cho Trợ lý AI — lưu MÃ HOÁ bằng APP_KEY (cast "encrypted" ở model Studio). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('studios', 'gemini_api_key')) {
            Schema::table('studios', function (Blueprint $table) {
                $table->text('gemini_api_key')->nullable()->after('bank_holder');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('studios', 'gemini_api_key')) {
            Schema::table('studios', function (Blueprint $table) {
                $table->dropColumn('gemini_api_key');
            });
        }
    }
};
