<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Install\InstallController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\ConsultationController as PublicConsultationController;
use App\Http\Controllers\Public\OrderFormController;
use App\Http\Controllers\Public\TrackingController;
use App\Http\Controllers\Studio\AiController;
use App\Http\Controllers\Studio\ClientController;
use App\Http\Controllers\Studio\ConsultationController;
use App\Http\Controllers\Studio\OrderController;
use App\Http\Controllers\Studio\QuickSearchController;
use App\Http\Controllers\Studio\OrderMoneyController;
use App\Http\Controllers\Studio\OrderWorkController;
use App\Http\Controllers\Studio\PageDesignController;
use App\Http\Controllers\Studio\ReportController;
use App\Http\Controllers\Studio\RoundController;
use App\Http\Controllers\Studio\ServiceController;
use App\Http\Controllers\Studio\SettingsController;
use App\Http\Controllers\Studio\TodayController;
use Illuminate\Support\Facades\Route;

// ===== CÀI ĐẶT NỀN TẢNG (chạy 1 lần — tạo Super Admin) =====
Route::prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
    Route::get('/requirements', [InstallController::class, 'requirements'])->name('requirements');
    Route::get('/database', [InstallController::class, 'showDatabaseForm'])->name('database');
    Route::post('/database', [InstallController::class, 'saveDatabase'])->name('database.save');
    Route::get('/account', [InstallController::class, 'showAccountForm'])->name('account');
    Route::post('/account', [InstallController::class, 'saveAccount'])->name('account.save');
    Route::get('/finish', [InstallController::class, 'finish'])->name('finish');
});

// ===== ĐĂNG NHẬP / ĐĂNG KÝ =====
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
    Route::middleware('registration.open')->group(function () {
        Route::get('/dang-ky', [RegisterController::class, 'show'])->name('register');
        Route::post('/dang-ky', [RegisterController::class, 'store'])->name('register.store');
        Route::get('/dang-ky/hoan-tat', [RegisterController::class, 'showGoogle'])->name('register.google');
        Route::post('/dang-ky/hoan-tat', [RegisterController::class, 'storeGoogle'])->name('register.google.store');
    });
    Route::get('/quen-mat-khau', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/quen-mat-khau', [ForgotPasswordController::class, 'send'])->name('password.email');
    Route::get('/dat-lai-mat-khau/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/dat-lai-mat-khau', [ResetPasswordController::class, 'update'])->name('password.update');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Google: 1 điểm vào cho đăng nhập, đăng ký và liên kết từ Hồ sơ (?lien-ket=1) — nên không đặt trong nhóm guest.
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->middleware('throttle:google')->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:google')->name('auth.google.callback');
Route::delete('/ho-so/google', [GoogleAuthController::class, 'unlink'])->middleware('auth')->name('profile.google.unlink');

// ===== TRANG TĨNH =====
Route::get('/gioi-thieu', fn (PageController $c) => $c->show('gioi-thieu'))->name('pages.about');
Route::get('/dieu-khoan-su-dung', fn (PageController $c) => $c->show('dieu-khoan-su-dung'))->name('pages.terms');
Route::get('/chinh-sach-bao-mat', fn (PageController $c) => $c->show('chinh-sach-bao-mat'))->name('pages.privacy');
Route::get('/sitemap.xml', [PageController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [PageController::class, 'robots'])->name('robots');

// ===== CÔNG KHAI CHO KHÁCH: trang đặt đơn + trang theo dõi đơn (không cần tài khoản) =====
Route::name('public.')->group(function () {
    Route::get('/dat/{slug}', [OrderFormController::class, 'show'])->where('slug', '[a-z0-9-]{3,40}')->name('order');
    Route::get('/dat/{slug}/anh-chia-se.jpg', [OrderFormController::class, 'shareImage'])->where('slug', '[a-z0-9-]{3,40}')->name('share-image');
    Route::post('/dat/{slug}/hen-tu-van', [PublicConsultationController::class, 'store'])->where('slug', '[a-z0-9-]{3,40}')->middleware('throttle:order-form')->name('consult.store');
    Route::prefix('hen/{token}')->where(['token' => '[A-Za-z0-9]{40}'])->middleware('throttle:track-view')->group(function () {
        Route::get('/', [PublicConsultationController::class, 'show'])->name('consult.show');
        Route::get('/lich.ics', [PublicConsultationController::class, 'ics'])->name('consult.ics');
        Route::post('/huy', [PublicConsultationController::class, 'cancel'])->middleware('throttle:track-action')->name('consult.cancel');
    });
    Route::post('/dat/{slug}', [OrderFormController::class, 'store'])->where('slug', '[a-z0-9-]{3,40}')->middleware('throttle:order-form')->name('order.store');

    Route::prefix('theo-doi/{token}')->where(['token' => '[A-Za-z0-9]{40}'])->middleware('throttle:track-view')->group(function () {
        Route::get('/', [TrackingController::class, 'show'])->name('track');
        Route::post('/dong-y-bao-gia', [TrackingController::class, 'acceptQuote'])->middleware('throttle:track-action')->name('track.accept');
        Route::post('/chon', [TrackingController::class, 'choose'])->middleware('throttle:track-action')->name('track.choose');
        Route::post('/phan-hoi', [TrackingController::class, 'respond'])->middleware('throttle:track-respond')->name('track.respond');
        Route::post('/nhan-tin', [TrackingController::class, 'message'])->middleware('throttle:track-respond')->name('track.message');
        Route::get('/khong-nhan-nhac', [TrackingController::class, 'mute'])->middleware('throttle:track-action')->name('track.mute');
    });
});

// ===== SUPER ADMIN =====
Route::middleware(['auth', 'role:platform_admin'])->prefix('sieu-quan-tri')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/studio/{studio}', [DashboardController::class, 'show'])->whereNumber('studio')->name('studios.show');
    Route::put('/studio/{studio}/khoa-mo', [DashboardController::class, 'toggleActive'])->whereNumber('studio')->name('studios.toggle');
    Route::post('/cap-nhat-database', [DashboardController::class, 'updateSchema'])->name('schema.update');
    Route::get('/cai-dat', [AdminSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/cai-dat', [AdminSettingsController::class, 'update'])->name('settings.update');
    Route::get('/trang/{slug}', [AdminPageController::class, 'edit'])->name('pages.edit');
    Route::put('/trang/{slug}', [AdminPageController::class, 'update'])->name('pages.update');
    Route::get('/nguoi-dung', [AdminUserController::class, 'index'])->name('users.index');
    Route::put('/nguoi-dung/{user}/mat-khau', [AdminUserController::class, 'resetPassword'])->whereNumber('user')->name('users.password');
    Route::put('/nguoi-dung/{user}/khoa-mo', [AdminUserController::class, 'toggleActive'])->whereNumber('user')->name('users.toggle');
});

Route::get('/', [HomeController::class, 'index'])->name('home');

// ===== KHU VỰC LÀM VIỆC CỦA FREELANCER =====
Route::middleware(['auth', 'role:owner'])->group(function () {
    Route::get('/hom-nay', [TodayController::class, 'index'])->name('today');

    // Đơn
    Route::get('/don', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/don/bang', [OrderController::class, 'board'])->name('orders.board');
    Route::post('/don/bang/tai-them', [OrderController::class, 'boardMore'])->name('orders.board.more');
    Route::patch('/don/bang/tuy-chon', [OrderController::class, 'boardPrefs'])->name('orders.board.prefs');
    Route::get('/tim-nhanh', QuickSearchController::class)->name('quick-search');
    Route::get('/don/lich', [OrderController::class, 'calendar'])->name('orders.calendar');
    Route::get('/don/tao', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/don', [OrderController::class, 'store'])->name('orders.store');
    Route::post('/don/ai-len-don', [AiController::class, 'draftOrder'])->middleware('throttle:ai')->name('orders.ai-draft');
    Route::post('/don/{order}/ai', [AiController::class, 'assist'])->whereNumber('order')->middleware('throttle:ai')->name('orders.ai');
    Route::get('/don/{order}', [OrderController::class, 'show'])->whereNumber('order')->name('orders.show');
    Route::get('/don/{order}/sua', [OrderController::class, 'edit'])->whereNumber('order')->name('orders.edit');
    Route::put('/don/{order}', [OrderController::class, 'update'])->whereNumber('order')->name('orders.update');
    Route::delete('/don/{order}', [OrderController::class, 'destroy'])->whereNumber('order')->name('orders.destroy');
    Route::patch('/don/{order}/giai-doan', [OrderController::class, 'status'])->whereNumber('order')->name('orders.status');
    Route::post('/don/{order}/nhan', [OrderController::class, 'accept'])->whereNumber('order')->name('orders.accept');
    Route::post('/don/{order}/anh-bia', [OrderController::class, 'cover'])->whereNumber('order')->name('orders.cover');
    Route::delete('/don/{order}/anh-bia', [OrderController::class, 'removeCover'])->whereNumber('order')->name('orders.cover.remove');
    Route::post('/don/{order}/doi-link', [OrderController::class, 'regenerateToken'])->whereNumber('order')->name('orders.token');

    // Tiền: báo giá, thu tiền
    Route::put('/don/{order}/bao-gia', [OrderMoneyController::class, 'charges'])->whereNumber('order')->name('orders.charges');
    Route::post('/don/{order}/gui-bao-gia', [OrderMoneyController::class, 'sendQuote'])->whereNumber('order')->name('orders.quote');
    Route::post('/don/{order}/thu-tien', [OrderMoneyController::class, 'pay'])->whereNumber('order')->name('orders.pay');
    Route::delete('/thu-tien/{payment}', [OrderMoneyController::class, 'destroyPayment'])->whereNumber('payment')->name('payments.destroy');

    // Công việc: link Drive, trao đổi, bàn giao
    Route::post('/don/{order}/link', [OrderWorkController::class, 'addLink'])->whereNumber('order')->name('orders.links.store');
    Route::delete('/link/{link}', [OrderWorkController::class, 'removeLink'])->whereNumber('link')->name('links.destroy');
    Route::post('/don/{order}/nhan-tin', [OrderWorkController::class, 'message'])->whereNumber('order')->name('orders.message');
    Route::post('/gop-y/{feedback}/xong', [OrderWorkController::class, 'resolve'])->whereNumber('feedback')->name('feedback.resolve');
    Route::post('/don/{order}/ban-giao', [OrderWorkController::class, 'deliver'])->whereNumber('order')->name('orders.deliver');
    Route::patch('/don/{order}/nhac-khach', [OrderWorkController::class, 'reminders'])->whereNumber('order')->name('orders.reminders');

    // Vòng & phương án
    Route::post('/don/{order}/vong', [RoundController::class, 'store'])->whereNumber('order')->name('rounds.store');
    Route::put('/vong/{round}', [RoundController::class, 'update'])->whereNumber('round')->name('rounds.update');
    Route::post('/vong/{round}/gui', [RoundController::class, 'send'])->whereNumber('round')->name('rounds.send');
    Route::delete('/vong/{round}', [RoundController::class, 'destroy'])->whereNumber('round')->name('rounds.destroy');
    Route::post('/vong/{round}/phuong-an', [RoundController::class, 'addProposal'])->whereNumber('round')->name('proposals.store');
    Route::put('/phuong-an/{proposal}', [RoundController::class, 'updateProposal'])->whereNumber('proposal')->name('proposals.update');
    Route::post('/phuong-an/{proposal}/anh', [RoundController::class, 'addImages'])->whereNumber('proposal')->name('proposals.images');
    Route::delete('/phuong-an/{proposal}', [RoundController::class, 'destroyProposal'])->whereNumber('proposal')->name('proposals.destroy');
    Route::delete('/anh/{image}', [RoundController::class, 'destroyImage'])->whereNumber('image')->name('images.destroy');

    // Hẹn gọi tư vấn
    Route::get('/tu-van', [ConsultationController::class, 'index'])->name('consultations.index');
    Route::patch('/tu-van/{consultation}', [ConsultationController::class, 'update'])->whereNumber('consultation')->name('consultations.update');

    // Khách hàng
    Route::get('/khach', [ClientController::class, 'index'])->name('clients.index');
    Route::post('/khach', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/khach/{client}', [ClientController::class, 'show'])->whereNumber('client')->name('clients.show');
    Route::put('/khach/{client}', [ClientController::class, 'update'])->whereNumber('client')->name('clients.update');
    Route::delete('/khach/{client}', [ClientController::class, 'destroy'])->whereNumber('client')->name('clients.destroy');

    // Dịch vụ & bảng giá
    Route::get('/dich-vu', [ServiceController::class, 'index'])->name('services.index');
    Route::post('/dich-vu', [ServiceController::class, 'store'])->name('services.store');
    Route::put('/dich-vu/{service}', [ServiceController::class, 'update'])->whereNumber('service')->name('services.update');
    Route::delete('/dich-vu/{service}', [ServiceController::class, 'destroy'])->whereNumber('service')->name('services.destroy');
    Route::post('/dich-vu/{service}/len', [ServiceController::class, 'moveUp'])->whereNumber('service')->name('services.up');

    // Báo cáo
    Route::get('/bao-cao', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/bao-cao/xuat-csv', [ReportController::class, 'export'])->name('reports.export');

    // Cài đặt studio
    Route::get('/cai-dat', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/cai-dat/studio', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::post('/cai-dat/anh-dai-dien', [SettingsController::class, 'avatar'])->name('settings.avatar');
    Route::put('/cai-dat/dat-don', [SettingsController::class, 'updateOrderForm'])->name('settings.order-form');
    Route::put('/cai-dat/thanh-toan', [SettingsController::class, 'updatePayment'])->name('settings.payment');
    Route::put('/cai-dat/tu-van', [SettingsController::class, 'updateConsult'])->name('settings.consult');
    Route::put('/cai-dat/nhac-khach', [SettingsController::class, 'updateReminders'])->name('settings.reminders');
    Route::post('/cai-dat/nhac-khach/gui-thu', [SettingsController::class, 'testReminder'])->middleware('throttle:6,1')->name('settings.reminders.test');
    Route::put('/cai-dat/giao-dien', [PageDesignController::class, 'update'])->name('settings.appearance');
    Route::post('/cai-dat/anh-bia-trang', [PageDesignController::class, 'cover'])->name('settings.page-cover');
    Route::post('/cai-dat/tac-pham', [PageDesignController::class, 'addWork'])->name('portfolio.store');
    Route::put('/cai-dat/tac-pham/{item}', [PageDesignController::class, 'updateWork'])->whereNumber('item')->name('portfolio.update');
    Route::post('/cai-dat/tac-pham/{item}/len', [PageDesignController::class, 'moveWork'])->whereNumber('item')->name('portfolio.up');
    Route::delete('/cai-dat/tac-pham/{item}', [PageDesignController::class, 'removeWork'])->whereNumber('item')->name('portfolio.destroy');
    Route::get('/cai-dat/xuat-du-lieu', [SettingsController::class, 'export'])->name('settings.export');
    Route::put('/cai-dat/tro-ly-ai', [AiController::class, 'saveKey'])->middleware('throttle:ai')->name('settings.ai');
    Route::delete('/cai-dat/tro-ly-ai', [AiController::class, 'removeKey'])->name('settings.ai.remove');
    Route::put('/cai-dat/tro-ly-ai/mo-hinh', [AiController::class, 'saveModels'])->middleware('throttle:ai')->name('settings.ai.models');
});

// Hồ sơ cá nhân (cả freelancer lẫn Super Admin)
Route::middleware('auth')->group(function () {
    Route::get('/ho-so', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/ho-so', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/ho-so/mat-khau', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/ho-so', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
