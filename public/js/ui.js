import { getLang, t } from './i18n.js';

/* ------------------------------------------------------------------
 * Safe templating: `html` escapes every interpolated value unless it
 * was produced by `html` itself or wrapped in raw().
 * ---------------------------------------------------------------- */

class Raw {
  constructor(value) { this.value = value; }
  toString() { return this.value; }
}

export const raw = (value) => new Raw(String(value));

export function esc(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function render(value) {
  if (value instanceof Raw) return value.value;
  if (Array.isArray(value)) return value.map(render).join('');
  if (value === null || value === undefined || value === false) return '';
  return esc(value);
}

export function html(strings, ...values) {
  let out = '';
  strings.forEach((chunk, i) => {
    out += chunk;
    if (i < values.length) out += render(values[i]);
  });
  return new Raw(out);
}

export function mount(element, template) {
  element.innerHTML = template instanceof Raw ? template.value : String(template);
  return element;
}

export const $ = (selector, root = document) => root.querySelector(selector);
export const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export function debounce(fn, wait = 300) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), wait);
  };
}

/* ------------------------------------------------------------------
 * Icons (Lucide-style strokes)
 * ---------------------------------------------------------------- */

const ICONS = {
  dashboard: '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
  transactions: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/>',
  clients: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
  payments: '<circle cx="9" cy="9" r="6"/><path d="M9 6v6l3 2"/><path d="M15 14h6M18 11v6"/>',
  invoice: '<path d="M6 2h9l4 4v16H6z"/><path d="M15 2v5h5M9 12h6M9 16h6"/>',
  attendance: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  clearance: '<path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/>',
  whatsapp: '<path d="M20 11.5a8 8 0 0 1-11.8 7L4 20l1.5-4.2A8 8 0 1 1 20 11.5z"/><path d="M8.5 9.5c.4 2 2 3.6 4 4"/>',
  discussion: '<path d="M4 5h16v11H8l-4 4z"/><path d="M8 9h8M8 12h5"/>',
  reports: '<path d="M4 19V5M4 19h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>',
  services: '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><path d="M2 13h20"/>',
  users: '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
  settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
  logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  search: '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
  edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
  trash: '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
  upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
  download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
  check: '<path d="M20 6L9 17l-5-5"/>',
  x: '<path d="M18 6L6 18M6 6l12 12"/>',
  menu: '<path d="M3 6h18M3 12h18M3 18h18"/>',
  sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
  moon: '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
  globe: '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20z"/>',
  eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
  eyeOff: '<path d="M17.9 17.9A10.1 10.1 0 0 1 12 19c-6.5 0-10-7-10-7a18 18 0 0 1 4.1-5.1M9.9 5.2A9.7 9.7 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-2.2 3.2M1 1l22 22M14.1 14.1a3 3 0 1 1-4.2-4.2"/>',
  clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
  alert: '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/>',
  inbox: '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.1L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.9A2 2 0 0 0 16.7 4H7.3a2 2 0 0 0-1.8 1.1z"/>',
  bolt: '<path d="M13 2L3 14h8l-1 8 10-12h-8z"/>',
  shield: '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
  coins: '<circle cx="8" cy="8" r="6"/><path d="M18.1 10.4A6 6 0 1 1 10.4 18M7 6h1v4"/>',
  file: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
  paperclip: '<path d="M21.4 11.1l-9.2 9.2a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5"/>',
  arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>',
};

export function icon(name, extraClass = '') {
  const body = ICONS[name] ?? '';
  return raw(`<svg class="icon ${extraClass}" viewBox="0 0 24 24" aria-hidden="true">${body}</svg>`);
}

/* ------------------------------------------------------------------
 * Formatting
 * ---------------------------------------------------------------- */

const locale = () => (getLang() === 'ar' ? 'ar-OM-u-nu-latn' : 'en-GB');

/** Baisa (integer) -> "12.500". */
export function omr(baisa) {
  return (Number(baisa || 0) / 1000).toLocaleString(locale(), {
    minimumFractionDigits: 3,
    maximumFractionDigits: 3,
  });
}

/** "12.5" typed by the user -> 12500 baisa. */
export function toBaisa(text) {
  const n = Number(String(text).replace(',', '.').trim());
  return Number.isFinite(n) && n >= 0 ? Math.round(n * 1000) : NaN;
}

/** Server timestamps are plain "YYYY-MM-DD HH:MM:SS" in the office time zone. */
function parse(value) {
  if (!value) return null;
  const match = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(value);
  if (!match) return null;
  const [, y, m, d, hh = '0', mm = '0'] = match;
  return new Date(Number(y), Number(m) - 1, Number(d), Number(hh), Number(mm));
}

export function date(value) {
  const d = parse(value);
  return d ? d.toLocaleDateString(locale(), { year: 'numeric', month: 'short', day: 'numeric' }) : t('common.none');
}

export function dateTime(value) {
  const d = parse(value);
  return d
    ? d.toLocaleString(locale(), { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
    : t('common.none');
}

/** Value for <input type="datetime-local">. */
export function toInputDateTime(value) {
  const d = parse(value);
  if (!d) return '';
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}

export function isOverdue(tx) {
  if (!tx.due_at || ['completed', 'cancelled'].includes(tx.status)) return false;
  const d = parse(tx.due_at);
  return d !== null && d.getTime() < Date.now();
}

export function minutesLabel(minutes) {
  if (minutes === null || minutes === undefined) return t('common.none');
  if (minutes >= 1440 && minutes % 1440 === 0) return `${minutes / 1440} ${t('common.days')}`;
  if (minutes >= 60 && minutes % 60 === 0) return `${minutes / 60} ${t('common.hours')}`;
  return `${minutes} ${t('common.minutes')}`;
}

export function fileSize(bytes) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function initials(name) {
  const parts = String(name || '?').trim().split(/\s+/);
  return (parts[0]?.[0] ?? '?') + (parts.length > 1 ? parts[parts.length - 1][0] : '');
}

/* Name pickers for bilingual records. */
export const serviceName = (s) => (getLang() === 'en' && s.name_en ? s.name_en : s.name_ar);
export const ministryName = (m) => (m ? (getLang() === 'en' && m.name_en ? m.name_en : m.name_ar) : '');

/* ------------------------------------------------------------------
 * Badges
 * ---------------------------------------------------------------- */

const STATUS_TONE = {
  inquiry: 'neutral', open: 'info', waiting_documents: 'warn', in_progress: 'primary',
  waiting_payment: 'accent', waiting_external: 'warn', waiting_client: 'warn',
  completed: 'success', cancelled: 'danger',
};

export const STATUS_COLOR = {
  inquiry: 'var(--faint)', open: 'var(--info)', waiting_documents: 'var(--warn)', in_progress: 'var(--primary)',
  waiting_payment: 'var(--accent)', waiting_external: '#8e6bd6', waiting_client: '#d6804b',
  completed: 'var(--success)', cancelled: 'var(--danger)',
};

export const badge = (label, tone = 'neutral') => html`<span class="badge badge-${tone}">${label}</span>`;
export const statusBadge = (status) => badge(t(`status.${status}`), STATUS_TONE[status] ?? 'neutral');

export function priorityBadge(priority) {
  const tone = { normal: 'neutral', high: 'warn', urgent: 'danger' }[priority] ?? 'neutral';
  return badge(t(`priority.${priority}`), tone);
}

export function docStatusBadge(status) {
  const tone = { required: 'neutral', received: 'info', verified: 'success', rejected: 'danger', expired: 'warn' }[status] ?? 'neutral';
  return badge(t(`docstatus.${status}`), tone);
}

export const activeBadge = (status) =>
  status === 'active' ? badge(t('common.active'), 'success') : badge(t('common.inactive'), 'neutral');

/* ------------------------------------------------------------------
 * Toasts
 * ---------------------------------------------------------------- */

export function toast(message, type = 'success') {
  let host = $('.toasts');
  if (!host) {
    host = document.createElement('div');
    host.className = 'toasts';
    host.setAttribute('role', 'status');
    host.setAttribute('aria-live', 'polite');
    document.body.append(host);
  }

  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.textContent = message;
  host.append(el);
  setTimeout(() => el.remove(), type === 'error' ? 6000 : 3500);
}

/* ------------------------------------------------------------------
 * Modal
 * ---------------------------------------------------------------- */

/**
 * @param {{title: string, body: any, submitLabel?: string, danger?: boolean,
 *          wide?: boolean, onSubmit?: (modal: HTMLElement) => Promise<void|false>,
 *          hideFooter?: boolean}} options
 * @returns {{close: () => void, el: HTMLElement}}
 */
export function openModal({ title, body, submitLabel, danger = false, wide = false, onSubmit, hideFooter = false }) {
  const previouslyFocused = document.activeElement;
  const overlay = document.createElement('div');
  overlay.className = 'overlay';

  mount(overlay, html`
    <form class="modal" role="dialog" aria-modal="true" aria-label="${title}" ${wide ? raw('style="width:min(860px,100%)"') : ''} novalidate>
      <div class="modal-head">
        <h2>${title}</h2>
        <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="${t('common.close')}">${icon('x')}</button>
      </div>
      <div class="modal-body">${body}</div>
      <div class="alert alert-danger" data-error hidden style="margin:0 22px 16px"></div>
      ${hideFooter ? '' : html`
        <div class="modal-foot">
          <button type="button" class="btn" data-close>${t('common.cancel')}</button>
          <button type="submit" class="btn ${danger ? 'btn-danger' : 'btn-primary'}">${submitLabel ?? t('common.save')}</button>
        </div>`}
    </form>`);

  const form = $('form', overlay);
  const errorBox = $('[data-error]', overlay);

  const close = () => {
    overlay.remove();
    document.removeEventListener('keydown', onKey);
    if (previouslyFocused instanceof HTMLElement) previouslyFocused.focus();
  };

  const onKey = (event) => { if (event.key === 'Escape') close(); };
  document.addEventListener('keydown', onKey);

  overlay.addEventListener('mousedown', (event) => { if (event.target === overlay) close(); });
  $$('[data-close]', overlay).forEach((b) => b.addEventListener('click', close));

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!onSubmit) return;

    errorBox.hidden = true;
    const submit = $('[type="submit"]', form);
    const label = submit.innerHTML;
    submit.disabled = true;
    submit.innerHTML = '<span class="spinner"></span>';

    try {
      const result = await onSubmit(form);
      if (result !== false) close();
    } catch (error) {
      errorBox.textContent = error.message || t('common.error');
      errorBox.hidden = false;
      submit.disabled = false;
      submit.innerHTML = label;
    }
  });

  document.body.append(overlay);
  (overlay.querySelector('input:not([type=hidden]), select, textarea') || form.querySelector('button')).focus();

  return { close, el: form };
}

export function confirmDialog({ title, message, confirmLabel, danger = true }) {
  return new Promise((resolve) => {
    let settled = false;
    const done = (value) => { if (!settled) { settled = true; resolve(value); } };

    const modal = openModal({
      title,
      body: html`<p>${message}</p>`,
      submitLabel: confirmLabel ?? t('common.confirm'),
      danger,
      onSubmit: async () => { done(true); },
    });

    // Closing without confirming resolves false.
    new MutationObserver((_, observer) => {
      if (!document.body.contains(modal.el)) { done(false); observer.disconnect(); }
    }).observe(document.body, { childList: true });
  });
}

/* ------------------------------------------------------------------
 * Table helpers
 * ---------------------------------------------------------------- */

export function skeletonRows(rows = 6, cols = 5) {
  return html`${Array.from({ length: rows }, () => html`<tr>${Array.from({ length: cols }, () => html`<td><div class="skeleton" style="height:16px"></div></td>`)}</tr>`)}`;
}

export function emptyState(title = t('common.no_results'), hint = t('common.no_results_hint'), iconName = 'inbox') {
  return html`<div class="empty">${icon(iconName)}<strong>${title}</strong><span>${hint}</span></div>`;
}

export function renderPagination(host, { total, limit, offset }, go) {
  if (total <= limit) { host.innerHTML = ''; host.hidden = true; return; }
  host.hidden = false;
  const from = offset + 1;
  const to = Math.min(offset + limit, total);

  mount(host, html`
    <span>${t('common.showing')} <b class="num">${from}–${to}</b> ${t('common.of')} <b class="num">${total}</b></span>
    <span class="spacer"></span>
    <button class="btn btn-sm" data-prev ${offset === 0 ? raw('disabled') : ''}>${t('common.prev')}</button>
    <button class="btn btn-sm" data-next ${to >= total ? raw('disabled') : ''}>${t('common.next')}</button>`);

  $('[data-prev]', host)?.addEventListener('click', () => go(Math.max(0, offset - limit)));
  $('[data-next]', host)?.addEventListener('click', () => go(offset + limit));
}

/* ------------------------------------------------------------------
 * Combobox (search-as-you-type picker). Stores the selected public id
 * in a hidden input named `name`.
 * ---------------------------------------------------------------- */

/**
 * @param {HTMLElement} host
 * @param {{name: string, placeholder: string, fetcher: (q: string) => Promise<Array<{id: string, label: string, sub?: string}>>,
 *          value?: {id: string, label: string} | null}} options
 */
export function combobox(host, { name, placeholder, fetcher, value = null }) {
  host.classList.add('combo');
  mount(host, html`
    <input type="hidden" name="${name}" value="${value?.id ?? ''}">
    <input type="text" class="input" autocomplete="off" role="combobox" aria-expanded="false" placeholder="${placeholder}" value="${value?.label ?? ''}">
    <div class="combo-list" role="listbox" hidden></div>`);

  const hidden = $('input[type=hidden]', host);
  const input = $('input[type=text]', host);
  const list = $('.combo-list', host);
  let items = [];

  const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); };

  const draw = () => {
    mount(list, items.length
      ? html`${items.map((item, i) => html`<button type="button" class="combo-item" role="option" data-i="${i}">${item.label}${item.sub ? html`<small>${item.sub}</small>` : ''}</button>`)}`
      : html`<div class="combo-empty">${t('common.no_results')}</div>`);
    list.hidden = false;
    input.setAttribute('aria-expanded', 'true');
  };

  const search = debounce(async () => {
    try { items = await fetcher(input.value.trim()); } catch { items = []; }
    draw();
  }, 220);

  input.addEventListener('input', () => { hidden.value = ''; search(); });
  input.addEventListener('focus', () => { if (!hidden.value) search(); });
  input.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
  list.addEventListener('mousedown', (event) => {
    const button = event.target.closest('[data-i]');
    if (!button) return;
    event.preventDefault();
    const item = items[Number(button.dataset.i)];
    hidden.value = item.id;
    input.value = item.label;
    close();
  });
  document.addEventListener('click', (event) => { if (!host.contains(event.target)) close(); });
}

/** Read a form into a plain object of trimmed strings. */
export function formData(form) {
  const out = {};
  for (const [key, value] of new FormData(form).entries()) {
    if (typeof value === 'string') out[key] = value.trim();
  }
  return out;
}
