<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Freelancer TỰ XOÁ tài khoản: xoá studio (cascade ở tầng database lo các bảng con: khách, dịch vụ, đơn,
 * phương án, góp ý, khoản thu...), xoá thư mục ảnh của studio trên đĩa, cuối cùng xoá tài khoản.
 */
class AccountDeletionService
{
    public function delete(User $owner): void
    {
        $studio = $owner->studio;
        $dir = $studio?->storageDir();

        DB::transaction(function () use ($owner, $studio) {
            // orders.client_id là restrictOnDelete — xoá đơn trước rồi mới tới khách (qua cascade của studio).
            $studio?->orders()->delete();
            $studio?->delete();
            $owner->delete();
        });

        if ($dir) {
            Storage::disk('public')->deleteDirectory($dir);
        }
    }
}
