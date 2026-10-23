<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Studio;
use App\Support\Facebook;
use Closure;
use Illuminate\Http\Request;

/**
 * Một nơi duy nhất quyết định "liên hệ của khách" ở mọi form (trang đặt đơn công khai, trang Khách hàng, tạo đơn tay):
 *  - Chuẩn hoá SĐT / email / Facebook trước khi kiểm tra.
 *  - Bắt buộc có ÍT NHẤT 1 trong 3: Số điện thoại, Email, Facebook (không bắt buộc riêng SĐT).
 *  - Nhận ra khách cũ theo thứ tự tin cậy: SĐT → email → link Facebook.
 *
 * $prefix dùng cho form có tiền tố tên field (VD: "client_" ở form tạo đơn → client_phone, client_email, client_facebook).
 */
class ClientDirectory
{
    public const CONTACT_REQUIRED = 'Cần ít nhất 1 cách liên hệ: số điện thoại, email hoặc Facebook.';

    /** Chuẩn hoá giá trị liên hệ trong request (gọi TRƯỚC validate). */
    public function prepare(Request $request, string $prefix = ''): void
    {
        $phone = $request->input($prefix.'phone');
        $email = $request->input($prefix.'email');
        $facebook = $request->input($prefix.'facebook');

        $request->merge([
            $prefix.'phone' => is_string($phone) && trim($phone) !== '' ? normalize_phone($phone) : null,
            $prefix.'email' => is_string($email) && trim($email) !== '' ? mb_strtolower(trim($email)) : null,
            // Link không phải Facebook giữ nguyên để luật bên dưới báo lỗi rõ ràng.
            $prefix.'facebook' => is_string($facebook) && ! Facebook::isForeignUrl($facebook) ? Facebook::normalize($facebook) : (is_string($facebook) ? trim($facebook) : null),
        ]);
    }

    /** Luật kiểm tra 3 field liên hệ. $required = false khi các field này đang tắt (VD: tạo đơn cho khách có sẵn). */
    public function rules(string $prefix = '', bool $required = true): array
    {
        $others = fn (string $field) => implode(',', array_map(fn ($f) => $prefix.$f, array_diff(['phone', 'email', 'facebook'], [$field])));

        return [
            $prefix.'phone' => array_values(array_filter([$required ? 'required_without_all:'.$others('phone') : null, 'nullable', 'string', 'max:20', 'regex:/^\+?\d{8,15}$/'])),
            $prefix.'email' => ['nullable', 'email', 'max:255'],
            $prefix.'facebook' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) {
                if (is_string($value) && Facebook::isForeignUrl($value)) {
                    $fail('Đây không phải link trang cá nhân Facebook. Dán link dạng facebook.com/ten.cua.ban, hoặc ghi tên Facebook.');
                }
            }],
        ];
    }

    public function messages(string $prefix = ''): array
    {
        return [
            $prefix.'phone.required_without_all' => self::CONTACT_REQUIRED,
            $prefix.'phone.regex' => 'Số điện thoại không hợp lệ (VD: 0912345678).',
            $prefix.'email.email' => 'Email không hợp lệ.',
        ];
    }

    /**
     * Khách có sẵn khớp với liên hệ vừa nhập. SĐT tin cậy nhất, rồi email, rồi link Facebook.
     * Tên Facebook dạng chữ tự do KHÔNG dùng để ghép (quá dễ trùng tên).
     */
    public function find(Studio $studio, ?string $phone, ?string $email, ?string $facebook, ?int $exceptId = null): ?Client
    {
        return $this->match($studio, $phone, $email, $facebook, $exceptId)['client'] ?? null;
    }

    /**
     * Như find() nhưng cho biết khớp theo field nào.
     *
     * @return array{client: Client, by: 'phone'|'email'|'facebook'}|null
     */
    public function match(Studio $studio, ?string $phone, ?string $email, ?string $facebook, ?int $exceptId = null): ?array
    {
        $query = fn () => $studio->clients()->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->latest('id');

        if (filled($phone) && ($client = $query()->where('phone', $phone)->first())) {
            return ['client' => $client, 'by' => 'phone'];
        }

        if (filled($email) && ($client = $query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first())) {
            return ['client' => $client, 'by' => 'email'];
        }

        if (Facebook::isLink($facebook) && ($client = $query()->where('facebook', $facebook)->first())) {
            return ['client' => $client, 'by' => 'facebook'];
        }

        return null;
    }

    public static function matchLabel(string $by): string
    {
        return ['phone' => 'số điện thoại', 'email' => 'email', 'facebook' => 'Facebook'][$by] ?? $by;
    }

    /**
     * Bổ sung liên hệ còn TRỐNG của khách có sẵn — không ghi đè thông tin freelancer đã lưu
     * (người lạ gõ trùng SĐT/email trên trang công khai không thể sửa hồ sơ khách của bạn).
     *
     * @param  array{phone?: ?string, email?: ?string, facebook?: ?string, contact_url?: ?string}  $contacts
     */
    public function fillMissing(Client $client, array $contacts): Client
    {
        foreach (['phone', 'email', 'facebook', 'contact_url'] as $field) {
            if (blank($client->{$field}) && filled($contacts[$field] ?? null)) {
                $client->{$field} = $contacts[$field];
            }
        }

        return $client;
    }
}
