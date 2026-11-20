<?php

namespace App\Services\Mail;

/**
 * Nội dung 1 email gửi khách, độc lập với giao diện. StudioMail dựng thành MailMessage: bản HTML theo mẫu giao diện
 * của studio (emails/studio.blade.php) + bản chữ thuần (emails/studio-text.blade.php).
 * Đoạn văn hỗ trợ **in đậm**; mọi thứ khác được escape.
 */
final class EmailContent
{
    /**
     * @param  list<string>  $paragraphs  trước nút bấm
     * @param  list<array{0: string, 1: string}>  $facts  bảng thông tin nhỏ (nhãn, giá trị)
     * @param  ?array{labels: list<string>, current: int}  $steps  thanh tiến độ
     * @param  ?array{0: string, 1: string}  $button  (chữ, link)
     * @param  list<string>  $after  sau nút bấm
     * @param  ?string  $note  lời nhắn riêng của studio (khung trích dẫn)
     * @param  ?string  $footer  dòng chân thư (VD link tắt email tự động) — chấp nhận [chữ](link)
     */
    public function __construct(
        public string $heading,
        public array $paragraphs = [],
        public ?string $eyebrow = null,
        public ?string $preheader = null,
        public array $facts = [],
        public ?array $steps = null,
        public ?array $button = null,
        public array $after = [],
        public ?string $note = null,
        public ?string $footer = null,
        public ?string $greeting = null,
        public ?string $signoff = null,
        public array $attachments = [],
    ) {}

    /** Escape + **đậm** + [chữ](https://link) → HTML an toàn để in trong email. */
    public static function inline(string $text, string $linkColor = 'inherit'): string
    {
        $html = e($text);
        $html = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html);
        $html = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u', fn ($m) => '<a href="'.$m[2].'" style="color:'.$linkColor.';text-decoration:underline">'.$m[1].'</a>', $html);

        return nl2br($html, false);
    }

    /** Bản chữ thuần: bỏ ** và đổi [chữ](link) thành "chữ: link". */
    public static function plain(string $text): string
    {
        $text = str_replace('**', '', $text);

        return preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u', '$1: $2', $text);
    }
}
