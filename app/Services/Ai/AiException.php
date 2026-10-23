<?php

namespace App\Services\Ai;

use RuntimeException;

/** Lỗi AI có thông báo tiếng Việt hiển thị thẳng cho chủ studio (getMessage()). */
class AiException extends RuntimeException
{
    public const KEY_INVALID = 'key_invalid';

    public const QUOTA = 'quota';

    public const UNAVAILABLE = 'unavailable';

    public const BAD_OUTPUT = 'bad_output';

    public const NO_KEY = 'no_key';

    public function __construct(string $message, public readonly string $reason = self::UNAVAILABLE)
    {
        parent::__construct($message);
    }
}
