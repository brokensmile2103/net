// Nét không cache gì: trạng thái đơn, tiền, phương án phải luôn mới nhất.
// Service worker này chỉ để trình duyệt cho phép "Thêm vào màn hình chính".
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {
    // Không chặn request — trình duyệt tự đi ra mạng như bình thường.
});
