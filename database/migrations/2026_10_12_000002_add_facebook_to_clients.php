<?php

use App\Support\Facebook;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facebook là 1 trong 3 cách nhận diện khách (cùng SĐT và email) — nhiều freelancer chốt đơn hoàn toàn qua Messenger.
 * SĐT không còn bắt buộc. Link Facebook cũ nằm ở "Link liên hệ" được chuyển sang cột mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'facebook')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('facebook')->nullable()->after('email');
                $table->index(['studio_id', 'facebook']);
                $table->index(['studio_id', 'email']);
            });
        }

        // Chuyển link Facebook đang nằm ở "Link liên hệ" sang cột Facebook (chỉ khi cột Facebook còn trống).
        DB::table('clients')->whereNull('facebook')->whereNotNull('contact_url')
            ->select(['id', 'contact_url'])
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $facebook = Facebook::normalize($row->contact_url);
                    if (Facebook::isLink($facebook)) {
                        DB::table('clients')->where('id', $row->id)->update(['facebook' => $facebook, 'contact_url' => null]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'facebook')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropIndex(['studio_id', 'facebook']);
                $table->dropIndex(['studio_id', 'email']);
                $table->dropColumn('facebook');
            });
        }
    }
};
