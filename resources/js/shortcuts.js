/**
 * Phím tắt trong khu làm việc (theo thói quen Linear / GitHub / Gmail):
 *   Alt + N (⌥ N)      tạo đơn mới
 *   Ctrl + K (⌘ K)     bảng lệnh: tìm đơn theo mã / tên / khách, tìm khách, mở nhanh mọi trang
 *   /                  tìm trên trang (ô tìm kiếm của Bảng việc, Tất cả đơn, Khách hàng) — trang không có ô tìm → mở bảng lệnh
 *   G rồi H/B/D/L/K/V/R/C  chuyển trang: Hôm nay, Bảng việc, Danh sách đơn, Lịch, Khách hàng, Dịch vụ, Báo cáo, Cài đặt
 *   E                  sửa đơn (trong trang đơn)
 *   ?                  xem tất cả phím tắt
 * Phím 1 chữ không chạy khi đang gõ vào ô nhập. Phím tắt của Bảng việc nằm trong resources/js/board.js.
 *
 * Đường dẫn lấy từ <body data-shortcuts='{"new": "...", ...}'> (layouts/app.blade.php) để không phải viết cứng URL.
 */
export const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent || '');

const GO = { h: 'today', b: 'board', d: 'orders', l: 'calendar', k: 'clients', v: 'services', r: 'reports', c: 'settings' };

const isTyping = (el) => el instanceof Element && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));

function routes() {
    try {
        return JSON.parse(document.body.dataset.shortcuts || '{}');
    } catch (_) {
        return {};
    }
}

function go(url) {
    if (url && url !== window.location.href) window.location.href = url;
}

let pendingG = null;

function onKeydown(e) {
    if (e.defaultPrevented) return;
    const r = routes();
    if (!r.new) return;
    const mod = isMac ? e.metaKey : e.ctrlKey;

    // Ctrl/⌘ + K: bảng lệnh (chạy cả khi đang gõ).
    if (mod && !e.altKey && !e.shiftKey && e.code === 'KeyK') {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('net-cmdk'));
        return;
    }
    // Alt/⌥ + N: đơn mới. Dùng e.code vì ⌥ + N trên Mac gõ ra "˜".
    if (e.altKey && !e.ctrlKey && !e.metaKey && e.code === 'KeyN') {
        e.preventDefault();
        go(r.new);
        return;
    }

    if (e.ctrlKey || e.metaKey || e.altKey || isTyping(e.target) || e.repeat) return;
    if (document.querySelector('[data-modal-open]')) return; // bảng lệnh / hộp phím tắt đang mở tự xử lý phím

    const key = e.key.toLowerCase();
    if (pendingG) {
        clearTimeout(pendingG);
        pendingG = null;
        if (GO[key]) {
            e.preventDefault();
            go(r[GO[key]]);
        }
        return;
    }
    if (e.key === '?') {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('net-shortcuts'));
        return;
    }
    if (e.key === '/') {
        e.preventDefault();
        const search = [...document.querySelectorAll('[data-search-focus]')].find((el) => el.getClientRects().length);
        if (search) {
            search.focus();
            search.select?.();
        } else {
            window.dispatchEvent(new CustomEvent('net-cmdk'));
        }
        return;
    }
    if (key === 'g' && !e.shiftKey) {
        pendingG = setTimeout(() => (pendingG = null), 1500);
        return;
    }
    if (key === 'e' && !e.shiftKey) {
        const edit = document.querySelector('[data-shortcut="edit"]');
        if (edit) {
            e.preventDefault();
            edit.click();
        }
    }
}

/** Bảng lệnh (Ctrl + K): lệnh có sẵn + kết quả tìm đơn / khách từ máy chủ. */
export function commandPalette(searchUrl, commands = []) {
    return {
        open: false,
        q: '',
        results: { orders: [], clients: [] },
        active: 0,
        loading: false,
        timer: null,
        seq: 0,
        isMac,

        show() {
            this.back = document.activeElement;
            this.open = true;
            this.q = '';
            this.results = { orders: [], clients: [] };
            this.active = 0;
            this.$nextTick(() => this.$refs.input?.focus());
        },
        close() {
            this.open = false;
            this.$refs.input?.blur();
            // Trả focus về chỗ cũ để phím tắt 1 chữ chạy tiếp ngay.
            if (this.back instanceof HTMLElement && this.back.isConnected) this.back.focus({ preventScroll: true });
        },
        get items() {
            const words = this.q.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');
            const cmds = commands.filter((c) => !words || c.search.includes(words))
                .map((c) => ({ ...c, group: 'Đi tới' }));
            const orders = this.results.orders.map((o) => ({ group: 'Đơn', label: o.code + ' · ' + o.title, detail: [o.client, o.status].filter(Boolean).join(' · '), url: o.url, cover: o.cover, icon: 'fa-file-lines' }));
            const clients = this.results.clients.map((c) => ({ group: 'Khách hàng', label: c.name, detail: c.detail, url: c.url, icon: 'fa-user' }));
            // Đang tìm thì đưa kết quả đơn lên trước lệnh.
            return words ? [...orders, ...clients, ...cmds] : cmds;
        },
        input() {
            this.active = 0;
            clearTimeout(this.timer);
            const q = this.q.trim();
            if (!q) { this.results = { orders: [], clients: [] }; return; }
            this.timer = setTimeout(() => this.search(q), 160);
        },
        async search(q) {
            const seq = ++this.seq;
            this.loading = true;
            try {
                const res = await fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
                const data = await res.json();
                if (seq === this.seq) this.results = { orders: data.orders || [], clients: data.clients || [] };
            } catch (_) {
                /* mất mạng: vẫn còn danh sách lệnh */
            } finally {
                if (seq === this.seq) this.loading = false;
            }
        },
        keydown(e) {
            const n = this.items.length;
            if (e.key === 'ArrowDown') { e.preventDefault(); this.active = n ? (this.active + 1) % n : 0; this.scrollActive(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); this.active = n ? (this.active - 1 + n) % n : 0; this.scrollActive(); }
            else if (e.key === 'Enter') { e.preventDefault(); this.run(this.items[this.active], e); }
            else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); this.close(); }
        },
        scrollActive() {
            this.$nextTick(() => this.$refs.list?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' }));
        },
        run(item, e = null) {
            if (!item) return;
            if (e && (e.ctrlKey || e.metaKey)) window.open(item.url, '_blank');
            else window.location.href = item.url;
            this.close();
        },
    };
}

/** Nhãn phím trên giao diện: Mac hiện ⌥ / ⌘ thay cho Alt / Ctrl. */
function renderKeyLabels() {
    if (!isMac) return;
    document.querySelectorAll('[data-kbd-alt]').forEach((el) => (el.textContent = el.textContent.replace('Alt ', '⌥')));
    document.querySelectorAll('[data-kbd-mod]').forEach((el) => (el.textContent = el.textContent.replace('Ctrl ', '⌘')));
}

export function initShortcuts() {
    document.addEventListener('keydown', onKeydown);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', renderKeyLabels);
    else renderKeyLabels();
}
