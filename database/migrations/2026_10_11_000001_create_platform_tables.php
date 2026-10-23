<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Bảng dùng chung của nền tảng: cài đặt hệ thống + 3 trang tĩnh do Super Admin tự sửa. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('meta_description', 160)->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('pages')->insert([
            [
                'slug' => 'gioi-thieu',
                'title' => 'Giới thiệu',
                'content' => '<p>Nét là công cụ quản lý đơn hàng dành cho <strong>người làm thiết kế tự do</strong> — logo, nhận diện thương hiệu, ấn phẩm, bao bì, hình ảnh mạng xã hội, giao diện…</p><p>Khách đặt đơn qua đường link riêng của bạn, theo dõi tiến độ, xem phương án, góp ý và duyệt ngay trên trình duyệt — không cần tài khoản, không cần cài ứng dụng. File gốc vẫn nằm trên Google Drive của bạn.</p><p>Nội dung trang này do quản trị viên nền tảng cập nhật.</p>',
                'meta_description' => 'Nét — quản lý đơn thiết kế cho freelancer: link đặt đơn, theo dõi tiến độ, phương án & góp ý, đặt cọc, bàn giao. Đăng ký miễn phí.',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'slug' => 'dieu-khoan-su-dung',
                'title' => 'Điều khoản sử dụng',
                'content' => '<p><em>Đây là bản khung tạm thời, cần được luật sư rà soát trước khi phát hành chính thức.</em></p><h3>1. Chấp nhận điều khoản</h3><p>Bằng việc đăng ký và sử dụng Nét, bạn đồng ý tuân thủ các điều khoản tại trang này.</p><h3>2. Tài khoản</h3><p>Bạn chịu trách nhiệm bảo mật thông tin đăng nhập và mọi hoạt động diễn ra dưới tài khoản của mình.</p><h3>3. Nội dung của bạn và khách hàng</h3><p>Bạn sở hữu và chịu trách nhiệm về nội dung đưa lên Nét (brief, ảnh xem trước, đường link). Nét không lưu file gốc — file gốc nằm trên dịch vụ lưu trữ bạn tự chọn (Google Drive…). Chỉ chia sẻ đường link theo dõi đơn cho đúng khách hàng của đơn đó.</p><h3>4. Giới hạn trách nhiệm</h3><p>Dịch vụ được cung cấp trên cơ sở "nguyên trạng". Nét là công cụ quản lý, không phải bên trung gian trong thoả thuận giữa bạn và khách hàng, không giữ hộ hay xử lý tiền thanh toán.</p><h3>5. Thay đổi điều khoản</h3><p>Điều khoản có thể được cập nhật theo thời gian. Việc tiếp tục sử dụng dịch vụ sau khi thay đổi đồng nghĩa với việc chấp nhận điều khoản mới.</p>',
                'meta_description' => 'Điều khoản sử dụng dịch vụ Nét — quyền và trách nhiệm của bạn khi dùng công cụ quản lý đơn thiết kế Nét.',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'slug' => 'chinh-sach-bao-mat',
                'title' => 'Chính sách bảo mật',
                'content' => '<p><em>Đây là bản khung tạm thời, cần được luật sư rà soát trước khi phát hành chính thức.</em></p><h3>1. Thông tin thu thập</h3><p>Thông tin bạn cung cấp khi đăng ký (tên, số điện thoại, email, tên studio); nếu bạn đăng nhập bằng Google, chúng tôi chỉ nhận mã định danh tài khoản Google, tên và email để đăng nhập — không truy cập Drive, Gmail hay dữ liệu khác. Cùng với đó là dữ liệu phát sinh khi sử dụng: khách hàng, đơn, brief, ảnh xem trước đã nén, góp ý, khoản thu.</p><h3>2. Thông tin khách hàng của bạn</h3><p>Khách đặt đơn qua trang của bạn để lại tên, số điện thoại, email và nội dung yêu cầu. Thông tin này chỉ bạn xem được và chỉ dùng cho việc thực hiện đơn.</p><h3>3. Chia sẻ thông tin</h3><p>Chúng tôi không bán hoặc cho thuê dữ liệu cá nhân của bạn và khách hàng của bạn cho bên thứ ba.</p><h3>4. Bảo mật</h3><p>Mật khẩu được mã hoá khi lưu trữ; mỗi studio chỉ xem được dữ liệu của chính mình; đường link theo dõi đơn là chuỗi ngẫu nhiên và có thể đổi mới bất kỳ lúc nào.</p><h3>5. Quyền của bạn</h3><p>Bạn có thể tải toàn bộ dữ liệu hoặc xoá tài khoản ngay trong phần Cài đặt / Hồ sơ.</p>',
                'meta_description' => 'Chính sách bảo mật của Nét — cách chúng tôi thu thập, sử dụng và bảo vệ dữ liệu của bạn và khách hàng.',
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::dropIfExists('settings');
    }
};
