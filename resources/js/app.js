import Alpine from 'alpinejs';
import { initPasteUpload } from './paste-upload';
import board from './board';
import { initShortcuts, commandPalette } from './shortcuts';

window.Alpine = Alpine;

/** Sao chép văn bản (tin nhắn gửi khách, link theo dõi). navigator.clipboard chỉ chạy trên HTTPS → có cách dự phòng. */
window.copyText = function (text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }

    return new Promise((resolve, reject) => {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.top = '-1000px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy') ? resolve() : reject(new Error('copy failed'));
        } catch (e) {
            reject(e);
        } finally {
            document.body.removeChild(ta);
        }
    });
};

/** "Mây Studio Đà Lạt" → "may-studio-da-lat" (gợi ý địa chỉ trang đặt đơn). */
window.slugify = function (text) {
    return String(text || '')
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'd')
        .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);
};

// Bảng việc (kanban): resources/js/board.js
Alpine.data('board', board);
// Bảng lệnh Ctrl + K (resources/js/shortcuts.js).
Alpine.data('cmdk', commandPalette);

/**
 * Trình soạn "Mẫu brief" của dịch vụ (services/_brief-builder). Mỗi câu: label, type, options (mỗi dòng 1 lựa chọn),
 * required, hint. Gửi lên máy chủ dạng JSON (ô ẩn brief_questions).
 */
Alpine.data('briefBuilder', (initial = [], presets = {}, types = {}, max = 12) => ({
    questions: [],
    presets,
    types,
    max,
    seq: 0,
    init() {
        this.questions = initial.map((q) => this.row(q));
    },
    row(q = {}) {
        return {
            key: ++this.seq, label: q.label || '', type: q.type || 'short', hint: q.hint || '', required: !!q.required,
            optionsText: (q.options || []).join('\n'),
        };
    },
    add() {
        if (this.questions.length < this.max) this.questions.push(this.row());
        this.$nextTick(() => this.$el.querySelector('ol li:last-child input')?.focus());
    },
    move(i, d) {
        const j = i + d;
        if (j < 0 || j >= this.questions.length) return;
        const list = [...this.questions];
        [list[i], list[j]] = [list[j], list[i]];
        this.questions = list;
    },
    usePreset(key) {
        const preset = this.presets[key];
        if (!preset) return;
        if (this.questions.length && !confirm(`Thay ${this.questions.length} câu hỏi hiện có bằng mẫu “${preset.label}”?`)) return;
        this.questions = preset.questions.map((q) => this.row(q));
    },
    get json() {
        return JSON.stringify(this.questions.filter((q) => q.label.trim()).map((q) => ({
            label: q.label.trim(), type: q.type, hint: q.hint.trim(), required: q.required,
            options: q.optionsText.split('\n').map((o) => o.trim()).filter(Boolean),
        })));
    },
}));

/**
 * Ghim góp ý lên ảnh phương án (trang theo dõi của khách): bấm vào ảnh → thêm 1 ghim tại vị trí đó
 * (toạ độ theo % để đúng trên mọi kích thước màn hình) kèm nội dung góp ý.
 */
Alpine.data('pinboard', () => ({
    pins: [],
    pinMode: false,
    active: null,

    add(e, imageId) {
        if (!this.pinMode) return;
        const rect = e.currentTarget.getBoundingClientRect();
        const x = Math.round(((e.clientX - rect.left) / rect.width) * 10000) / 100;
        const y = Math.round(((e.clientY - rect.top) / rect.height) * 10000) / 100;
        this.pins.push({ image_id: imageId, x, y, body: '' });
        this.active = this.pins.length - 1;
        this.$nextTick(() => document.getElementById('pin-input-' + this.active)?.focus());
    },
    remove(i) {
        this.pins.splice(i, 1);
        this.active = null;
    },
    pinsFor(imageId) {
        return this.pins.map((p, i) => ({ ...p, i })).filter((p) => p.image_id === imageId);
    },
    get filled() {
        return this.pins.filter((p) => p.body.trim() !== '');
    },
}));

/** Xem trước ảnh đã chọn trước khi tải lên (ảnh bìa, ảnh phương án). */
Alpine.data('filePreview', (max = 8) => ({
    previews: [],
    tooMany: false,
    pick(e) {
        this.previews.forEach((p) => URL.revokeObjectURL(p.url));
        const files = Array.from(e.target.files || []);
        this.tooMany = files.length > max;
        this.previews = files.slice(0, max).map((f) => ({ url: URL.createObjectURL(f), name: f.name, kb: Math.round(f.size / 1024) }));
    },
}));

Alpine.start();

// Ctrl + V / ⌘ + V để dán ảnh vào các ô tải ảnh (ảnh bìa, ảnh phương án, tác phẩm, ảnh đại diện).
initPasteUpload();

// Phím tắt: Alt + N tạo đơn, Ctrl + K bảng lệnh, G rồi chữ cái để chuyển trang, ? xem tất cả (resources/js/shortcuts.js).
initShortcuts();

/**
 * Ô nhập tiền VNĐ: tự thêm dấu chấm phân cách nghìn khi gõ, trả về số sạch ngay trước khi gửi form.
 * Dùng: class="money-input" + type="text" inputmode="numeric".
 */
function initMoneyInputs(root = document) {
    root.querySelectorAll('.money-input').forEach((input) => {
        if (input.dataset.moneyFormatted) return;
        input.dataset.moneyFormatted = '1';

        const format = () => {
            const cursorFromEnd = input.value.length - input.selectionStart;
            const digits = input.value.replace(/\D/g, '');
            input.value = digits ? Number(digits).toLocaleString('vi-VN') : '';
            const pos = Math.max(0, input.value.length - cursorFromEnd);
            input.setSelectionRange(pos, pos);
        };

        if (input.value) input.value = Number(input.value.replace(/\D/g, '')).toLocaleString('vi-VN');
        input.addEventListener('input', format);

        const form = input.closest('form');
        if (form && !form.dataset.moneyCleanupBound) {
            form.dataset.moneyCleanupBound = '1';
            form.addEventListener('submit', (e) => {
                if (e.defaultPrevented) return; // bấm "Huỷ" ở hộp confirm() → giữ nguyên số đang hiển thị
                form.querySelectorAll('.money-input').forEach((el) => (el.value = el.value.replace(/\D/g, '')));
            });
        }
    });
}
window.initMoneyInputs = initMoneyInputs;
document.addEventListener('DOMContentLoaded', () => initMoneyInputs());
document.addEventListener('alpine:initialized', () => initMoneyInputs());

/**
 * Chống bấm 2 lần (mạng chậm): form POST đã gửi thì bỏ qua các lần submit tiếp theo cho tới khi trang mới tải.
 * Tự mở lại sau 8 giây và khi quay lại trang bằng nút Back. Form GET (bộ lọc) không bị ảnh hưởng.
 */
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (e.defaultPrevented || !(form instanceof HTMLFormElement)) return;
    if ((form.getAttribute('method') || 'get').toLowerCase() === 'get' || form.hasAttribute('data-allow-resubmit')) return;
    if (form.dataset.submitting) {
        e.preventDefault();
        return;
    }
    form.dataset.submitting = '1';
    setTimeout(() => delete form.dataset.submitting, 8000);
});
window.addEventListener('pageshow', () => document.querySelectorAll('form[data-submitting]').forEach((f) => delete f.dataset.submitting));

// Cho phép "Thêm vào màn hình chính" ở khu làm việc (layout có <link rel="manifest">).
if ('serviceWorker' in navigator && document.querySelector('link[rel="manifest"]') && window.isSecureContext) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
