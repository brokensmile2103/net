/**
 * Bảng việc (kanban) — kéo thả bằng Pointer Events (chuột, bút, cảm ứng) thay cho HTML5 drag & drop để có:
 *  - thẻ "nhấc lên" bám theo con trỏ + khoảng trống báo đúng chỗ sẽ thả;
 *  - tự cuộn ngang khi kéo tới mép bảng, tự cuộn dọc trong cột dài;
 *  - Esc để huỷ kéo; kéo nhầm thì "Hoàn tác" ngay trên thông báo;
 *  - máy chủ từ chối (VD: chưa có link bàn giao) → thẻ tự về chỗ cũ, báo lý do;
 *  - cảm ứng: giữ thẻ ~0,3 giây rồi kéo (vuốt bình thường vẫn cuộn trang);
 *  - chuột: nắm vùng trống của bảng để kéo ngang cả bảng.
 *
 * v5 — chịu được rất nhiều đơn:
 *  - mỗi cột chỉ tải 1 trang thẻ, "Tải thêm" lấy tiếp (gửi id đã có, không lệch trang khi vừa kéo thẻ);
 *  - số đơn + tổng tiền mỗi cột do máy chủ đếm (đúng cả khi chưa tải hết), cập nhật tại chỗ khi chuyển thẻ;
 *  - ẩn cột / thu gọn cột (vẫn thả thẻ vào được), giới hạn số đơn mỗi cột (WIP), 3 chế độ xem;
 *  - chọn nhiều thẻ (ô chọn / phím X) rồi chuyển một lượt, có Hoàn tác;
 *  - bàn phím: ←↑↓→ đi giữa các thẻ, Enter mở đơn, Shift + ←/→ chuyển cột, X chọn, 1/2/3 đổi chế độ xem, Esc bỏ chọn.
 * Tuỳ chọn (xem, cột, giới hạn) lưu trên máy chủ: mở máy khác vẫn y như cũ.
 * Nút ⇄ trên thẻ vẫn là cách chuyển giai đoạn không cần kéo (kể cả sang "Đã huỷ" — cột không hiện trên bảng).
 */
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

/** 1.250.000 → "1,3tr", 125.000 → "125k" (giống money_compact() phía máy chủ). */
export function compactMoney(v) {
    if (v >= 999500) return (Math.round(v / 100000) / 10).toLocaleString('vi-VN').replace(/,0$/, '') + 'tr';
    if (v >= 1000) return Math.round(v / 1000).toLocaleString('vi-VN') + 'k';
    return Math.round(v).toLocaleString('vi-VN');
}

const isTyping = (el) => el instanceof Element && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));

export default (statusUrlTemplate, labels = {}, config = {}) => ({
    drag: null,       // phiên kéo đang diễn ra
    press: null,      // vừa nhấn, chưa đủ để thành kéo
    over: null,       // cột đang rê tới
    blocked: false,   // cột đang rê tới không nhận thẻ này (VD: "Đã bàn giao" khi chưa có link)
    toast: null,      // { text, tone, undo }
    toastTimer: null,
    pan: null,
    live: '',

    view: config.view || 'cards',
    hidden: [...(config.hidden || [])],
    collapsed: [...(config.collapsed || [])],
    limits: { ...(config.limits || {}) },
    counts: JSON.parse(JSON.stringify(config.counts || {})),
    remaining: { ...(config.remaining || {}) },
    loading: {},
    selected: [],
    busy: false,

    init() {
        // Giữ gốc bảng + vùng cuộn: gọi từ trong thẻ (có x-data riêng cho menu) thì $root/$refs trỏ vào thẻ, không phải bảng.
        this.boardEl = this.$el;
        this.scrollerEl = this.$refs.scroller;
        this.onMove = (e) => this.pointerMove(e);
        this.onUp = (e) => this.pointerUp(e);
        this.onKey = (e) => this.boardKey(e);
        // Đang kéo bằng cảm ứng: chặn trình duyệt cuộn trang thay vì để thẻ đi theo ngón tay.
        this.onTouchMove = (e) => { if (this.drag) e.preventDefault(); };
        this.onCancel = () => { if (this.drag) this.cancel(); else this.release(); };
        window.addEventListener('keydown', this.onKey);
        window.addEventListener('touchmove', this.onTouchMove, { passive: false });
        this.$nextTick(() => this.toggleEmpty());
    },
    destroy() {
        window.removeEventListener('keydown', this.onKey);
        window.removeEventListener('touchmove', this.onTouchMove);
    },

    money: (v) => compactMoney(v),
    column(status) { return this.boardEl.querySelector('[data-column="' + status + '"]'); },
    list(status) { return this.column(status)?.querySelector('[data-cards]'); },
    isOpenColumn(status) { return !this.hidden.includes(status) && !this.collapsed.includes(status); },
    overLimit(status) { return !!this.limits[status] && (this.counts[status]?.count || 0) > this.limits[status]; },

    // ---------- Tuỳ chọn (lưu máy chủ) ----------
    async savePrefs(patch) {
        try {
            const res = await fetch(config.prefsUrl, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify(patch),
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                this.notify(data.message || 'Chưa lưu được tuỳ chọn Bảng việc.', 'error');
                return false;
            }
            return true;
        } catch (_) {
            this.notify('Mất kết nối — tuỳ chọn chưa được lưu.', 'error');
            return false;
        }
    },
    setView(view) {
        if (this.view === view) return;
        this.view = view;
        this.savePrefs({ view });
    },
    async toggleHidden(status) {
        const hiding = !this.hidden.includes(status);
        if (hiding && this.hidden.length + 1 >= Object.keys(this.counts).length) {
            this.notify('Phải để lại ít nhất 1 cột.', 'error');
            return;
        }
        this.hidden = hiding ? [...this.hidden, status] : this.hidden.filter((s) => s !== status);
        if (hiding) this.selected = this.selected.filter((id) => this.cardEl(id)?.dataset.status !== status);
        await this.savePrefs({ hidden: this.hidden });
        if (!hiding) this.ensureLoaded(status);
    },
    toggleCollapsed(status) {
        const collapsing = !this.collapsed.includes(status);
        this.collapsed = collapsing ? [...this.collapsed, status] : this.collapsed.filter((s) => s !== status);
        this.savePrefs({ collapsed: this.collapsed });
        if (!collapsing) this.ensureLoaded(status);
    },
    showAll() {
        const reopen = [...this.hidden, ...this.collapsed];
        this.hidden = [];
        this.collapsed = [];
        this.savePrefs({ hidden: [], collapsed: [] });
        reopen.forEach((s) => this.ensureLoaded(s));
    },
    setLimit(status, label) {
        const cur = this.limits[status] || '';
        const answer = prompt(`Cột “${label}” tối đa bao nhiêu đơn cùng lúc?\nVượt giới hạn thì tiêu đề cột chuyển đỏ để bạn biết nên xử lý bớt.\nĐể trống = không giới hạn.`, cur);
        if (answer === null) return;
        const n = parseInt(String(answer).replace(/\D/g, ''), 10) || 0;
        const limits = { ...this.limits };
        if (n > 0) limits[status] = Math.min(999, n); else delete limits[status];
        this.limits = limits;
        // Gửi cả cột bị bỏ giới hạn (0) để máy chủ xoá.
        this.savePrefs({ limits: { ...Object.fromEntries(Object.keys(this.counts).map((k) => [k, 0])), ...limits } });
    },

    // ---------- Tải thêm ----------
    /** Cột vừa mở lại (trước đó ẩn / thu gọn nên chưa tải thẻ) → tải trang đầu. */
    ensureLoaded(status) {
        const list = this.list(status);
        if (list && !list.querySelector('[data-card]') && this.remaining[status] > 0) this.loadMore(status);
    },
    async loadMore(status) {
        if (this.loading[status]) return;
        const list = this.list(status);
        if (!list) return;
        this.loading = { ...this.loading, [status]: true };
        const body = new FormData();
        body.append('status', status);
        list.querySelectorAll(':scope > [data-card]').forEach((c) => body.append('except[]', c.dataset.id));
        const q = config.query || {};
        if (q.q) body.append('q', q.q);
        if (q.dv) body.append('dv', q.dv);
        (q.loc || []).forEach((f) => body.append('loc[]', f));
        try {
            const res = await fetch(config.moreUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            const tpl = document.createElement('template');
            tpl.innerHTML = data.html;
            // Bỏ thẻ đã có (VD: vừa được kéo vào cột khi cột còn thu gọn).
            tpl.content.querySelectorAll('[data-card]').forEach((c) => { if (this.cardEl(c.dataset.id)) c.remove(); });
            list.append(tpl.content);
            this.remaining = { ...this.remaining, [status]: data.remaining };
            this.toggleEmpty();
        } catch (_) {
            this.notify('Không tải thêm được — thử lại sau nhé.', 'error');
        } finally {
            this.loading = { ...this.loading, [status]: false };
        }
    },

    // ---------- Nhấn xuống ----------
    pointerDown(e) {
        if (e.button !== 0 || this.drag) return;
        const card = e.target.closest('[data-card]');
        if (card && !e.target.closest('[data-no-drag]')) {
            // Chuột: chặn bôi đen chữ khi bắt đầu kéo (cú click mở đơn vẫn hoạt động bình thường).
            if (e.pointerType === 'mouse') e.preventDefault();
            this.press = { card, x: e.clientX, y: e.clientY, id: e.pointerId, type: e.pointerType, timer: null };
            if (e.pointerType === 'touch') {
                // Cảm ứng: giữ yên ~300ms mới nhấc thẻ, để vuốt cuộn trang không bị coi là kéo.
                this.press.timer = setTimeout(() => this.press && this.begin(this.press.x, this.press.y), 300);
            }
            window.addEventListener('pointermove', this.onMove, { passive: false });
            window.addEventListener('pointerup', this.onUp);
            window.addEventListener('pointercancel', this.onCancel);
            return;
        }
        // Nắm vùng trống để kéo ngang cả bảng (chỉ chuột).
        if (e.pointerType === 'mouse' && !e.target.closest('a,button,input,select,textarea,label,[data-card],[data-no-drag]')) {
            const sc = this.scrollerEl;
            this.pan = { x: e.clientX, left: sc.scrollLeft };
            sc.classList.add('cursor-grabbing');
            window.addEventListener('pointermove', this.onMove);
            window.addEventListener('pointerup', this.onUp);
        }
    },

    pointerMove(e) {
        if (this.pan) {
            this.scrollerEl.scrollLeft = this.pan.left - (e.clientX - this.pan.x);
            return;
        }
        if (this.press && !this.drag) {
            const dist = Math.hypot(e.clientX - this.press.x, e.clientY - this.press.y);
            if (this.press.type === 'touch') {
                if (dist > 10) this.release(); // vuốt → để trình duyệt cuộn
                return;
            }
            if (dist < 6) return;
            this.begin(e.clientX, e.clientY);
        }
        if (!this.drag) return;
        e.preventDefault();
        this.drag.x = e.clientX;
        this.drag.y = e.clientY;
        this.place();
    },

    pointerUp() {
        if (this.pan) {
            this.scrollerEl.classList.remove('cursor-grabbing');
            this.pan = null;
            this.unlisten();
            return;
        }
        if (this.drag) {
            this.drop();
        } else {
            this.release();
        }
    },

    release() {
        if (this.press?.timer) clearTimeout(this.press.timer);
        this.press = null;
        this.unlisten();
    },

    unlisten() {
        window.removeEventListener('pointermove', this.onMove);
        window.removeEventListener('pointerup', this.onUp);
        window.removeEventListener('pointercancel', this.onCancel);
    },

    // ---------- Bắt đầu kéo ----------
    begin(x, y) {
        const card = this.press.card;
        if (this.press.timer) clearTimeout(this.press.timer);
        this.press = null;
        const rect = card.getBoundingClientRect();

        const ghost = card.cloneNode(true);
        ghost.removeAttribute('id');
        ghost.setAttribute('aria-hidden', 'true');
        Object.assign(ghost.style, {
            position: 'fixed', left: '0', top: '0', width: rect.width + 'px', margin: '0', zIndex: '60', pointerEvents: 'none',
            transform: `translate(${rect.left}px, ${rect.top}px)`, boxShadow: '0 18px 40px rgba(0,0,0,.22)', borderRadius: '8px',
        });
        ghost.classList.add('board-ghost');
        // Thẻ ma nằm ngoài bảng → mang theo chế độ xem để giữ đúng hình dạng.
        const wrap = document.createElement('div');
        wrap.dataset.view = this.view;
        wrap.className = 'board-ghost-wrap';
        wrap.setAttribute('x-ignore', ''); // bản sao chỉ để nhìn — Alpine không khởi tạo lại các directive bên trong
        wrap.appendChild(ghost);
        document.body.appendChild(wrap);

        const placeholder = document.createElement('div');
        placeholder.className = 'board-placeholder rounded-md border-2 border-dashed border-[var(--accent)] bg-[var(--accent-soft)]/50';
        placeholder.style.height = rect.height + 'px';
        card.after(placeholder);
        card.hidden = true;

        this.drag = {
            card, ghost: wrap, ghostCard: ghost, placeholder, x, y,
            dx: x - rect.left, dy: y - rect.top,
            from: card.dataset.status, fromList: card.parentElement, fromNext: placeholder.nextElementSibling,
            raf: null,
        };
        document.body.classList.add('select-none', 'cursor-grabbing');
        if (this.drag && navigator.vibrate) { try { navigator.vibrate(8); } catch (_) { /* không hỗ trợ */ } }
        this.tick();
        this.place();
    },

    /** Mỗi khung hình: dời thẻ ma theo con trỏ (nghiêng nhẹ theo hướng kéo) + tự cuộn ở mép. */
    tick() {
        const d = this.drag;
        if (!d) return;
        const tilt = Math.max(-4, Math.min(4, (d.x - (d.lastX ?? d.x)) * 0.6));
        d.lastX = d.x;
        d.ghostCard.style.transform = `translate(${d.x - d.dx}px, ${d.y - d.dy}px) rotate(${tilt}deg)`;

        const sc = this.scrollerEl.getBoundingClientRect();
        const edge = 70;
        if (d.x < sc.left + edge) this.scrollerEl.scrollLeft -= Math.ceil((sc.left + edge - d.x) / 5);
        else if (d.x > sc.right - edge) this.scrollerEl.scrollLeft += Math.ceil((d.x - (sc.right - edge)) / 5);

        const list = this.over && this.list(this.over);
        if (list && list.offsetParent) {
            const r = list.getBoundingClientRect();
            if (d.y < r.top + 40) list.scrollTop -= 8;
            else if (d.y > r.bottom - 40) list.scrollTop += 8;
        }
        d.raf = requestAnimationFrame(() => this.tick());
    },

    /** Tìm cột dưới con trỏ và đặt khoảng trống vào đúng vị trí giữa các thẻ. */
    place() {
        const d = this.drag;
        const el = document.elementFromPoint(d.x, d.y);
        const column = el?.closest('[data-column]');
        if (!column) { this.over = null; this.blocked = false; return; }

        const status = column.dataset.column;
        this.over = status;
        this.blocked = status === 'delivered' && d.card.dataset.canDeliver !== '1' && d.from !== 'delivered';

        const list = column.querySelector('[data-cards]');
        // Cột thu gọn: thả vào là xong, không cần chọn vị trí.
        if (this.collapsed.includes(status)) {
            if (d.placeholder.parentElement !== list) list.prepend(d.placeholder);
            this.toggleEmpty();
            return;
        }
        const cards = [...list.querySelectorAll(':scope > [data-card]:not([hidden])')];
        const after = cards.find((c) => {
            const r = c.getBoundingClientRect();
            return d.y < r.top + r.height / 2;
        });
        if (after) {
            if (d.placeholder.nextElementSibling !== after) list.insertBefore(d.placeholder, after);
        } else if (list.lastElementChild !== d.placeholder) {
            list.appendChild(d.placeholder);
        }
        this.toggleEmpty();
    },

    // ---------- Thả ----------
    async drop() {
        const d = this.drag;
        const target = this.over;
        const blocked = this.blocked;
        this.finishVisual();

        if (!target || blocked) {
            this.restore(d);
            if (blocked) this.notify(labels.needSource || 'Thêm link file bàn giao trước khi chuyển sang “Đã bàn giao”.', 'error');
            return;
        }

        // Đặt thẻ vào chỗ khoảng trống (kể cả chỉ đổi thứ tự trong cùng cột — chỉ là hiển thị).
        d.placeholder.replaceWith(d.card);
        d.card.hidden = false;
        this.flash(d.card);
        if (target === d.from) { this.toggleEmpty(); return; }
        await this.commit(d.card, d.from, target, { list: d.fromList, next: d.fromNext });
    },

    cancel() {
        const d = this.drag;
        this.finishVisual();
        this.restore(d);
    },

    finishVisual() {
        const d = this.drag;
        if (!d) return;
        cancelAnimationFrame(d.raf);
        d.ghost.remove();
        document.body.classList.remove('select-none', 'cursor-grabbing');
        this.unlisten();
        // Chặn cú "click" sinh ra ngay sau khi thả (không mở trang đơn ngoài ý muốn).
        const swallow = (ev) => { ev.preventDefault(); ev.stopPropagation(); };
        window.addEventListener('click', swallow, { capture: true, once: true });
        setTimeout(() => window.removeEventListener('click', swallow, { capture: true }), 50);
        this.drag = null;
        this.over = null;
        this.blocked = false;
    },

    restore(d) {
        d.placeholder.remove();
        d.card.hidden = false;
        if (d.fromNext && d.fromNext.parentElement === d.fromList) d.fromList.insertBefore(d.card, d.fromNext);
        else d.fromList.appendChild(d.card);
        this.toggleEmpty();
    },

    // ---------- Gọi máy chủ ----------
    /** Cộng/trừ số đơn + tổng tiền của 2 cột khi 1 thẻ chuyển (cột không có trên bảng — VD "Đã huỷ" — bỏ qua). */
    shift(from, to, amount) {
        const counts = { ...this.counts };
        if (counts[from]) counts[from] = { count: Math.max(0, counts[from].count - 1), sum: Math.max(0, counts[from].sum - amount) };
        if (counts[to]) counts[to] = { count: counts[to].count + 1, sum: counts[to].sum + amount };
        this.counts = counts;
    },

    /** Đổi giai đoạn trên máy chủ. Lỗi → đưa thẻ về chỗ cũ. Thành công → thông báo kèm "Hoàn tác". */
    async commit(card, from, to, origin, { undoable = true, reason = null, quiet = false } = {}) {
        card.dataset.status = to;
        card.classList.add('opacity-60');
        this.toggleEmpty();
        const result = await this.request(card.dataset.id, to, reason);
        card.classList.remove('opacity-60');

        if (!result.ok) {
            card.dataset.status = from;
            card.hidden = false;
            if (origin.next && origin.next.parentElement === origin.list) origin.list.insertBefore(card, origin.next);
            else origin.list.appendChild(card);
            this.toggleEmpty();
            if (!quiet) this.notify(result.message, 'error');
            return result;
        }

        this.shift(from, to, parseFloat(card.dataset.amount) || 0);
        if (quiet) return result;
        const name = card.dataset.code + ' sang “' + (labels.statuses?.[to] || to) + '”';
        this.live = 'Đã chuyển ' + name;
        this.notify('Đã chuyển ' + name + '.', 'ok', undoable && from !== 'cancelled' ? async () => {
            const back = this.list(from);
            const here = { list: card.parentElement, next: card.nextElementSibling };
            if (back) { back.prepend(card); card.hidden = false; this.flash(card); }
            await this.commit(card, to, from, here, { undoable: false });
        } : null);
        return result;
    },

    async request(id, status, reason = null) {
        try {
            const res = await fetch(statusUrlTemplate.replace('__ID__', id), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ status, reason }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) return { ok: true };
            return { ok: false, message: data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Không đổi được giai đoạn.') };
        } catch (err) {
            return { ok: false, message: 'Mất kết nối — thẻ đã về chỗ cũ, thử lại sau nhé.' };
        }
    },

    cardEl(id) { return this.boardEl.querySelector('[data-card][data-id="' + id + '"]'); },

    /**
     * Chuyển 1 thẻ sang giai đoạn khác (menu ⇄, bàn phím, chọn nhiều). Cột đích không hiện (Đã huỷ, cột bị ẩn)
     * → thẻ rời khỏi bảng. Trả về kết quả để chuyển hàng loạt gom thông báo.
     */
    async move(id, status, { reason = null, quiet = false } = {}) {
        const card = this.cardEl(id);
        if (!card || card.dataset.status === status) return { ok: true, skipped: true };

        if (status === 'cancelled' && reason === null) {
            reason = prompt(labels.cancelPrompt || 'Lý do huỷ đơn (khách sẽ thấy):');
            if (reason === null) return { ok: false, aborted: true };
        }
        if (status === 'delivered' && card.dataset.canDeliver !== '1') {
            const message = labels.needSource || 'Thêm link file bàn giao trước khi chuyển sang “Đã bàn giao”.';
            if (!quiet) this.notify(message, 'error');
            return { ok: false, message };
        }

        const from = card.dataset.status;
        const origin = { list: card.parentElement, next: card.nextElementSibling };
        const visible = this.list(status) && !this.hidden.includes(status);
        if (visible) {
            this.list(status).prepend(card);
            this.flash(card);
            if (!quiet && this.isOpenColumn(status)) card.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
        } else {
            card.hidden = true;
        }
        const result = await this.commit(card, from, status, origin, { reason, undoable: !!visible, quiet });
        if (result.ok && !visible) {
            // Cột ẩn: giữ thẻ trong cột (ẩn) để mở cột ra là thấy; Đã huỷ: bỏ hẳn.
            const list = this.list(status);
            if (list) { list.prepend(card); card.hidden = false; } else card.remove();
            this.selected = this.selected.filter((x) => x !== id);
        }
        if (result.ok && visible && !quiet && this.isOpenColumn(status)) card.focus({ preventScroll: true });
        return { ...result, from, card };
    },

    // ---------- Chọn nhiều ----------
    toggleSelect(id) {
        this.selected = this.selected.includes(id) ? this.selected.filter((x) => x !== id) : [...this.selected, id];
    },
    clearSelection() { this.selected = []; },
    selectColumn(status) {
        const ids = [...(this.list(status)?.querySelectorAll(':scope > [data-card]:not([hidden])') || [])].map((c) => +c.dataset.id);
        this.selected = [...new Set([...this.selected, ...ids])];
    },
    async bulkMove(status) {
        if (!status || !this.selected.length || this.busy) return;
        let reason = null;
        if (status === 'cancelled') {
            if (!confirm(`Huỷ ${this.selected.length} đơn đã chọn?`)) return;
            reason = prompt(labels.cancelPrompt || 'Lý do huỷ đơn (khách sẽ thấy):');
            if (reason === null) return;
        }
        this.busy = true;
        const done = [];
        const failed = [];
        for (const id of [...this.selected]) {
            const r = await this.move(id, status, { reason, quiet: true });
            if (r.ok && !r.skipped) done.push(r);
            else if (!r.ok && !r.aborted) failed.push((this.cardEl(id)?.dataset.code || '#' + id) + (r.message ? ': ' + r.message : ''));
        }
        this.busy = false;
        this.selected = failed.length ? this.selected.filter((id) => this.cardEl(id)?.dataset.status !== status) : [];
        const label = labels.statuses?.[status] || status;
        const text = `Đã chuyển ${done.length} đơn sang “${label}”.` + (failed.length ? ` ${failed.length} đơn chưa chuyển được — ${failed[0]}` : '');
        this.live = text;
        const undo = done.length && status !== 'cancelled' ? async () => {
            for (const r of done) {
                const back = this.list(r.from);
                const here = { list: r.card.parentElement, next: r.card.nextElementSibling };
                if (back) { back.prepend(r.card); r.card.hidden = false; }
                await this.commit(r.card, status, r.from, here, { undoable: false, quiet: true });
            }
            this.notify('Đã hoàn tác.', 'ok');
        } : null;
        this.notify(text, failed.length && !done.length ? 'error' : 'ok', undo);
    },

    // ---------- Bàn phím ----------
    /** Phím trên 1 thẻ đang chọn (focus). */
    cardKey(e, id) {
        if (e.target !== e.currentTarget || e.altKey || e.ctrlKey || e.metaKey) return;
        const card = e.currentTarget;
        if (e.shiftKey && (e.key === 'ArrowRight' || e.key === 'ArrowLeft')) {
            e.preventDefault();
            const cols = this.visibleColumns();
            const next = cols[cols.indexOf(card.dataset.status) + (e.key === 'ArrowRight' ? 1 : -1)];
            if (next) this.move(id, next);
            return;
        }
        if (e.shiftKey) return;
        switch (e.key) {
            case 'Enter': e.preventDefault(); window.location.href = card.dataset.url; break;
            case 'x': case 'X': case ' ': e.preventDefault(); this.toggleSelect(id); break;
            case 'ArrowDown': case 'ArrowUp': {
                e.preventDefault();
                const cards = this.cardsIn(card.dataset.status);
                const i = cards.indexOf(card) + (e.key === 'ArrowDown' ? 1 : -1);
                if (cards[i]) this.focusCard(cards[i]);
                else if (e.key === 'ArrowDown' && this.remaining[card.dataset.status] > 0) this.loadMore(card.dataset.status);
                break;
            }
            case 'ArrowRight': case 'ArrowLeft': {
                e.preventDefault();
                const cols = this.visibleColumns().filter((s) => this.isOpenColumn(s));
                const index = this.cardsIn(card.dataset.status).indexOf(card);
                let ci = cols.indexOf(card.dataset.status);
                // Bỏ qua cột trống, đi tiếp sang cột kế.
                while ((ci += e.key === 'ArrowRight' ? 1 : -1) >= 0 && ci < cols.length) {
                    const cards = this.cardsIn(cols[ci]);
                    if (cards.length) { this.focusCard(cards[Math.min(index, cards.length - 1)]); break; }
                }
                break;
            }
            default:
        }
    },
    /** Phím trên cả bảng (khi không gõ chữ). */
    boardKey(e) {
        if (e.key === 'Escape') {
            if (this.drag) { this.cancel(); return; }
            if (this.selected.length && !document.querySelector('.cmdk[open], [data-modal-open]')) this.clearSelection();
            return;
        }
        if (isTyping(e.target) || e.altKey || e.ctrlKey || e.metaKey || e.repeat) return;
        const views = { 1: 'cards', 2: 'compact', 3: 'rows' };
        if (views[e.key]) { e.preventDefault(); this.setView(views[e.key]); return; }
        // Chưa chọn thẻ nào mà bấm mũi tên → chọn thẻ đầu tiên.
        if (/^Arrow/.test(e.key) && !e.target.closest?.('[data-card]')) {
            const first = this.visibleColumns().filter((s) => this.isOpenColumn(s)).map((s) => this.cardsIn(s)[0]).find(Boolean);
            if (first) { e.preventDefault(); this.focusCard(first); }
        }
    },
    visibleColumns() {
        return [...this.boardEl.querySelectorAll('[data-column]')].map((c) => c.dataset.column).filter((s) => !this.hidden.includes(s));
    },
    cardsIn(status) { return [...(this.list(status)?.querySelectorAll(':scope > [data-card]:not([hidden])') || [])]; },
    focusCard(card) {
        card.focus({ preventScroll: true });
        card.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    },

    // ---------- Hiển thị ----------
    notify(text, tone = 'ok', undo = null) {
        clearTimeout(this.toastTimer);
        this.toast = { text, tone, undo };
        this.toastTimer = setTimeout(() => (this.toast = null), undo ? 7000 : 4500);
    },
    async runUndo() {
        const fn = this.toast?.undo;
        this.toast = null;
        if (fn) await fn();
    },
    flash(card) {
        card.classList.remove('board-drop');
        void card.offsetWidth;
        card.classList.add('board-drop');
    },
    toggleEmpty() {
        this.boardEl.querySelectorAll('[data-column]').forEach((col) => {
            const has = col.querySelector('[data-cards] > [data-card]:not([hidden]), [data-cards] > .board-placeholder');
            const empty = col.querySelector('[data-empty]');
            const more = (this.remaining[col.dataset.column] || 0) > 0;
            if (empty) empty.hidden = !!has || more || this.collapsed.includes(col.dataset.column);
        });
    },
    /** Giữ tương thích tên cũ. */
    recount() { this.toggleEmpty(); },
});
