<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    // Khai báo thẳng thay vì chỉ trông vào package auto-discovery: trên hosting upload code thủ công,
    // file bootstrap/cache/packages.php cũ (tạo trước khi cài Socialite) làm Socialite không được nạp
    // → bấm "Tiếp tục với Google" là lỗi 500. Khai báo trùng với auto-discovery không sao — Laravel chỉ nạp 1 lần.
    Laravel\Socialite\SocialiteServiceProvider::class,
];
