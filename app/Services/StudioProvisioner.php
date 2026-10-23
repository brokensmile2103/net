<?php

namespace App\Services;

use App\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tạo studio đầu tiên cho 1 chủ (kèm dịch vụ mẫu) — dùng cho trình cài đặt và việc cấp
 * studio riêng cho Super Admin (Super Admin cũng dùng được mọi tính năng như freelancer).
 * Bản đã cài từ trước (Super Admin chưa có studio) sẽ được tạo tự động ở lần đăng nhập đầu tiên.
 */
class StudioProvisioner
{
    public function __construct(private StudioSetup $setup) {}

    public function createFor(User $owner, string $name, ?string $slug = null): Studio
    {
        $studio = new Studio(['name' => $name, 'slug' => $slug ?: $this->freeSlug()]);
        $studio->owner_id = $owner->id;
        $studio->is_active = true;
        $studio->save();

        $this->setup->seedServices($studio);

        return $studio;
    }

    /** Chỉ tạo khi Super Admin hoàn toàn chưa có studio (studio đang bị khoá vẫn tính là có). */
    public function ensureFor(User $user): ?Studio
    {
        if (! $user->isPlatformAdmin()) {
            return null;
        }

        $studio = $user->studio()->first()
            ?? DB::transaction(fn () => $this->createFor($user, 'Studio của '.$user->name));

        $user->setRelation('studio', $studio);

        return $studio;
    }

    /** Địa chỉ trang đặt đơn tạm (đổi được trong Cài đặt): studio-xxxxxx, không trùng và không thuộc danh sách dành riêng. */
    private function freeSlug(): string
    {
        do {
            $slug = 'studio-'.strtolower(Str::random(6));
        } while (Studio::where('slug', $slug)->exists() || in_array($slug, config('net.reserved_slugs'), true));

        return $slug;
    }
}
