/**
 * Ctrl + V (⌘ + V trên Mac) để dán ảnh vào ô tải ảnh — ảnh chụp màn hình, ảnh copy từ Figma/Photoshop/trình duyệt/Zalo…
 *
 * Cách Nét chọn ô nhận ảnh khi trang có nhiều ô:
 *   1. ô đang được chọn (focus) hoặc vùng chuột đang trỏ vào;
 *   2. vùng vừa bấm vào trong 2 phút gần nhất;
 *   3. cả trang chỉ có đúng 1 ô đang hiện → ô đó;
 *   4. còn lại → hiện hộp nhỏ hỏi "Dán ảnh vào đâu?" (không đoán bừa, vì có ô tự tải lên ngay khi nhận ảnh).
 *
 * Đánh dấu vùng dán trong Blade: data-paste-zone="Tên vùng" trên khối bao quanh <input type="file">.
 * Không có data-paste-zone thì <form> chứa ô file được coi là vùng dán. Ô file đặt data-paste="off" để bỏ qua.
 * Ảnh định dạng ô không nhận (GIF, BMP, TIFF…) được đổi sang PNG ngay trên trình duyệt trước khi gán vào ô.
 */

const RECENT_MS = 2 * 60 * 1000;
const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent || '');

let pointer = null;     // vị trí chuột gần nhất
let recent = null;      // { zone, at } — vùng vừa bấm vào
let chooser = null;     // hộp chọn đang mở

const inputsIn = (zone) => [...zone.querySelectorAll('input[type="file"]')].filter((i) => !i.disabled && i.dataset.paste !== 'off' && acceptsImages(i));

function acceptsImages(input) {
    const accept = (input.getAttribute('accept') || '').trim();
    return accept === '' || /image\//.test(accept);
}

function zoneOf(el) {
    if (!(el instanceof Element)) return null;
    const marked = el.closest('[data-paste-zone]');
    if (marked && inputsIn(marked).length) return marked;
    const form = el.closest('form');
    if (!form || !inputsIn(form).length) return null;
    // Form có 1 vùng được đánh dấu bên trong (VD: khung "Ảnh bìa" trong form Đơn mới) → dùng khung đó cho rõ ràng.
    const inner = [...form.querySelectorAll('[data-paste-zone]')].filter((z) => inputsIn(z).length);
    return inner.length === 1 ? inner[0] : form;
}

/** Vùng đang hiện trên màn hình (không nằm trong <details> đóng, x-show ẩn, modal ẩn…). */
function visible(zone) {
    if (!zone.isConnected || !zone.getClientRects().length) return false;
    const style = getComputedStyle(zone);
    return style.visibility !== 'hidden' && style.display !== 'none';
}

function allZones() {
    const set = new Set();
    document.querySelectorAll('input[type="file"]').forEach((input) => {
        const zone = zoneOf(input);
        if (zone && visible(zone)) set.add(zone);
    });
    return [...set];
}

function zoneLabel(zone) {
    if (zone.dataset.pasteZone) return zone.dataset.pasteZone;
    const input = inputsIn(zone)[0];
    return input?.getAttribute('aria-label') || input?.labels?.[0]?.textContent.trim() || 'Ô tải ảnh';
}

function inViewport(zone) {
    const r = zone.getBoundingClientRect();
    return r.bottom > 0 && r.top < window.innerHeight && r.right > 0 && r.left < window.innerWidth;
}

/** Ảnh trong clipboard (bỏ qua chữ, file không phải ảnh). */
function clipboardImages(data) {
    const files = [];
    for (const item of data?.items || []) {
        if (item.kind === 'file' && item.type.startsWith('image/')) {
            const f = item.getAsFile();
            if (f) files.push(f);
        }
    }
    if (!files.length) {
        for (const f of data?.files || []) if (f.type.startsWith('image/')) files.push(f);
    }
    return files;
}

function isTextField(el) {
    if (!(el instanceof Element)) return false;
    if (el.isContentEditable) return true;
    if (el.tagName === 'TEXTAREA') return true;
    return el.tagName === 'INPUT' && !['file', 'checkbox', 'radio', 'button', 'submit', 'reset', 'image', 'color', 'range'].includes(el.type);
}

function allowedTypes(input) {
    return (input.getAttribute('accept') || '').split(',').map((s) => s.trim().toLowerCase()).filter(Boolean);
}

function typeAllowed(input, type) {
    const list = allowedTypes(input);
    return !list.length || list.includes(type) || list.includes('image/*');
}

const pad = (n) => String(n).padStart(2, '0');
function pastedName(i, ext) {
    const d = new Date();
    const stamp = `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}-${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}`;
    return `anh-dan-${stamp}${i ? '-' + (i + 1) : ''}.${ext}`;
}

const extOf = (type) => ({ 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' }[type] || 'png');

/** Đổi ảnh sang PNG khi ô không nhận định dạng gốc (GIF, BMP, TIFF, AVIF…). */
async function toPng(file) {
    const bitmap = await createImageBitmap(file);
    const canvas = document.createElement('canvas');
    canvas.width = bitmap.width;
    canvas.height = bitmap.height;
    canvas.getContext('2d').drawImage(bitmap, 0, 0);
    bitmap.close?.();
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
    if (!blob) throw new Error('convert failed');
    return blob;
}

async function prepare(files, input) {
    const out = [];
    for (const [i, f] of files.entries()) {
        let blob = f;
        let type = f.type;
        if (!typeAllowed(input, type)) {
            try {
                blob = await toPng(f);
                type = 'image/png';
            } catch (_) {
                continue;
            }
        }
        out.push(new File([blob], pastedName(i, extOf(type)), { type, lastModified: Date.now() }));
    }
    return out;
}

async function deliver(zone, files) {
    const input = inputsIn(zone)[0];
    if (!input) return;
    const prepared = await prepare(files, input);
    if (!prepared.length) {
        toast('Ảnh vừa dán có định dạng không đọc được — hãy lưu thành JPG/PNG rồi chọn file.', 'error');
        return;
    }

    const dt = new DataTransfer();
    if (input.multiple) {
        // Dán nhiều lần vào ô nhiều ảnh: cộng dồn, không thay thế ảnh đã chọn trước đó.
        for (const f of input.files || []) dt.items.add(f);
        prepared.forEach((f) => dt.items.add(f));
    } else {
        dt.items.add(prepared[0]);
    }
    input.files = dt.files;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));

    zone.classList.remove('paste-flash');
    void zone.offsetWidth;
    zone.classList.add('paste-flash');
    const n = input.multiple ? prepared.length : 1;
    toast(`Đã dán ${n} ảnh vào “${zoneLabel(zone)}”.`);
}

function pickZone(e) {
    const zones = allZones();
    if (!zones.length) return { zones };

    const focused = zoneOf(document.activeElement);
    if (focused && visible(focused)) return { zone: focused, zones };

    if (pointer) {
        const under = zoneOf(document.elementFromPoint(pointer.x, pointer.y));
        if (under && visible(under)) return { zone: under, zones };
    }
    if (recent && Date.now() - recent.at < RECENT_MS && visible(recent.zone)) return { zone: recent.zone, zones };

    if (zones.length === 1) return { zone: zones[0], zones };
    // Nhiều ô mà không rõ ý → hỏi (ô đang thấy trên màn hình xếp trước).
    return { zones: [...zones.filter(inViewport), ...zones.filter((z) => !inViewport(z))] };
}

async function onPaste(e) {
    if (e.defaultPrevented) return;
    const files = clipboardImages(e.clipboardData);
    if (!files.length) return;

    // Đang gõ vào ô chữ và clipboard có cả chữ (VD: copy từ Word) → để trình duyệt dán chữ như bình thường.
    const text = e.clipboardData.getData('text/plain');
    if (isTextField(e.target) && text.trim() !== '') return;

    const { zone, zones } = pickZone(e);
    if (!zones.length) return;
    e.preventDefault();

    if (zone) {
        closeChooser();
        await deliver(zone, files);
    } else {
        openChooser(zones, files);
    }
}

// ---------------------------------------------------------------- Thông báo nhỏ

let toastEl = null;
let toastTimer = null;
function toast(message, tone = 'ok') {
    if (!toastEl) {
        toastEl = document.createElement('div');
        toastEl.className = 'paste-toast';
        toastEl.setAttribute('role', 'status');
        toastEl.setAttribute('aria-live', 'polite');
        document.body.appendChild(toastEl);
    }
    toastEl.dataset.tone = tone;
    toastEl.textContent = message;
    toastEl.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (toastEl.hidden = true), 3800);
}

// ---------------------------------------------------------------- Hộp "Dán ảnh vào đâu?"

function openChooser(zones, files) {
    closeChooser();
    const box = document.createElement('div');
    box.className = 'paste-chooser';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    box.setAttribute('aria-labelledby', 'paste-chooser-title');

    const panel = document.createElement('div');
    panel.className = 'paste-chooser-panel';

    const preview = document.createElement('img');
    preview.className = 'paste-chooser-preview';
    preview.alt = '';
    preview.src = URL.createObjectURL(files[0]);

    const title = document.createElement('p');
    title.id = 'paste-chooser-title';
    title.className = 'paste-chooser-title';
    title.textContent = files.length > 1 ? `Dán ${files.length} ảnh vào đâu?` : 'Dán ảnh vào đâu?';

    const list = document.createElement('div');
    list.className = 'paste-chooser-list';
    zones.forEach((zone) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = zoneLabel(zone);
        btn.addEventListener('click', async () => {
            closeChooser();
            zone.scrollIntoView({ behavior: 'smooth', block: 'center' });
            recent = { zone, at: Date.now() };
            await deliver(zone, files);
        });
        list.appendChild(btn);
    });

    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'paste-chooser-cancel';
    cancel.textContent = 'Huỷ';
    cancel.addEventListener('click', closeChooser);

    panel.append(preview, title, list, cancel);
    box.appendChild(panel);
    box.addEventListener('click', (ev) => { if (ev.target === box) closeChooser(); });
    document.body.appendChild(box);
    chooser = { box, url: preview.src, back: document.activeElement };
    list.querySelector('button')?.focus();
}

function closeChooser() {
    if (!chooser) return;
    URL.revokeObjectURL(chooser.url);
    chooser.box.remove();
    if (chooser.back instanceof HTMLElement) chooser.back.focus({ preventScroll: true });
    chooser = null;
}

// ---------------------------------------------------------------- Gợi ý phím tắt

/** Thêm gợi ý "hoặc Ctrl + V để dán ảnh" (⌘ trên Mac) vào các phần tử data-paste-hint. */
function renderHints(root = document) {
    root.querySelectorAll('[data-paste-hint]:not([data-paste-ready])').forEach((el) => {
        el.dataset.pasteReady = '1';
        const kbd = (t) => Object.assign(document.createElement('kbd'), { textContent: t });
        el.replaceChildren(`${el.dataset.pasteHint || 'hoặc'} `, kbd(isMac ? '⌘' : 'Ctrl'), '+', kbd('V'), ' để dán ảnh');
    });
}

export function initPasteUpload() {
    document.addEventListener('paste', onPaste);
    document.addEventListener('pointermove', (e) => { if (e.pointerType === 'mouse') pointer = { x: e.clientX, y: e.clientY }; }, { passive: true });
    document.addEventListener('pointerdown', (e) => {
        const zone = zoneOf(e.target);
        if (zone) recent = { zone, at: Date.now() };
    }, { passive: true });
    document.addEventListener('focusin', (e) => {
        const zone = zoneOf(e.target);
        if (zone) recent = { zone, at: Date.now() };
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && chooser) closeChooser(); });
    // Rời chuột khỏi cửa sổ thì không coi là đang trỏ vào vùng nào.
    document.documentElement.addEventListener('mouseleave', () => (pointer = null));

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => renderHints());
    else renderHints();
    document.addEventListener('alpine:initialized', () => renderHints());
}
