import { api, setCsrf } from './api.js';
import { applyDocumentLang, getLang, setLang, t } from './i18n.js';
import { $, html, icon, initials, mount, toast } from './ui.js';

const NAV = [
  { page: 'dashboard', href: 'index.html', icon: 'dashboard', label: 'nav.dashboard', permission: 'transactions.view' },
  { page: 'transactions', href: 'transactions.html', icon: 'transactions', label: 'nav.transactions', permission: 'transactions.view' },
  { page: 'clients', href: 'clients.html', icon: 'clients', label: 'nav.clients', permission: 'clients.view' },
  { page: 'services', href: 'services.html', icon: 'services', label: 'nav.services', permission: 'services.view' },
  { page: 'payments', href: 'payments.html', icon: 'payments', label: 'nav.payments', permission: 'payments.view' },
  { page: 'invoices', href: 'invoices.html', icon: 'invoice', label: 'nav.invoices', permission: 'payments.view' },
  { page: 'attendance', href: 'attendance.html', icon: 'attendance', label: 'nav.attendance', permission: 'attendance.view' },
  { page: 'clearance', href: 'clearance.html', icon: 'clearance', label: 'nav.clearance', permission: 'clearance.view' },
  { page: 'whatsapp', href: 'whatsapp.html', icon: 'whatsapp', label: 'nav.whatsapp', permission: 'whatsapp.view' },
  { page: 'discussions', href: 'discussions.html', icon: 'discussion', label: 'nav.discussions', permission: 'discussions.view' },
  { page: 'reports', href: 'reports.html', icon: 'reports', label: 'nav.reports', permission: 'reports.view' },
  { page: 'users', href: 'users.html', icon: 'users', label: 'nav.users', permission: 'users.view' },
  { page: 'settings', href: 'settings.html', icon: 'settings', label: 'nav.settings', permission: null },
];

function storage(key, value) {
  try {
    if (value === undefined) return localStorage.getItem(key);
    localStorage.setItem(key, value);
  } catch { /* storage unavailable (private mode) */ }
  return null;
}

export function applyTheme() {
  const saved = storage('sanad.theme');
  const theme = saved || (window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  document.documentElement.dataset.theme = theme;
  return theme;
}

export function toggleTheme() {
  const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
  storage('sanad.theme', next);
  applyTheme();
}

export function switchLanguage() {
  setLang(getLang() === 'ar' ? 'en' : 'ar');
  window.location.reload();
}

/**
 * Boots an authenticated page: loads the session, draws the shell,
 * checks the page permission and returns helpers to the page script.
 */
export async function initPage({ page, titleKey, permission = null }) {
  applyDocumentLang();
  applyTheme();
  document.title = `${t(titleKey)} — ${t('app.name')}`;

  const root = $('#app');
  mount(root, html`<div class="empty" style="min-height:100vh"><span class="spinner" style="width:28px;height:28px"></span></div>`);

  const me = await api.get('me');
  setCsrf(me.csrf_token);

  const granted = new Set(me.permissions ?? []);
  const can = (permissionName) => granted.has(permissionName);

  mount(root, html`
    <div class="shell">
      <aside class="sidebar" id="sidebar">
        <div class="brand">
          <div class="brand-mark" aria-hidden="true">${getLang() === 'ar' ? 'س' : 'S'}</div>
          <div class="brand-text"><strong>${t('app.name')}</strong><span>${me.tenant?.name ?? t('app.tagline')}</span></div>
        </div>
        <nav class="nav" aria-label="${t('nav.menu')}">
          ${NAV.filter((item) => item.permission === null || can(item.permission)).map((item) => html`
            <a href="${item.href}" ${item.page === page ? html`aria-current="page"` : ''}>${icon(item.icon)}<span>${t(item.label)}</span></a>`)}
        </nav>
        <div class="sidebar-foot">
          <div class="avatar" aria-hidden="true">${initials(me.user.full_name)}</div>
          <div class="who"><strong>${me.user.full_name}</strong><span>${t(`role.${me.user.role}`)}</span></div>
          <button class="btn btn-ghost btn-icon btn-sm" id="logout" title="${t('nav.logout')}" aria-label="${t('nav.logout')}">${icon('logout')}</button>
        </div>
      </aside>
      <div class="backdrop" id="backdrop"></div>
      <div class="main">
        <header class="topbar">
          <button class="btn btn-ghost btn-icon menu-btn" id="menu" aria-label="${t('nav.menu')}">${icon('menu')}</button>
          <h1 id="page-title">${t(titleKey)}</h1>
          <button class="btn btn-ghost btn-sm" id="lang" aria-label="${t('lang.toggle')}">${icon('globe')}<span class="hide-sm">${t('lang.toggle')}</span></button>
          <button class="btn btn-ghost btn-icon btn-sm" id="theme" aria-label="${t('theme.toggle')}" title="${t('theme.toggle')}">${icon(document.documentElement.dataset.theme === 'dark' ? 'sun' : 'moon')}</button>
        </header>
        <main class="content" id="content"></main>
      </div>
    </div>`);

  $('#menu').addEventListener('click', () => document.body.classList.toggle('nav-open'));
  $('#backdrop').addEventListener('click', () => document.body.classList.remove('nav-open'));
  $('#lang').addEventListener('click', switchLanguage);
  $('#theme').addEventListener('click', () => {
    toggleTheme();
    $('#theme').innerHTML = icon(document.documentElement.dataset.theme === 'dark' ? 'sun' : 'moon').value;
  });
  $('#logout').addEventListener('click', async () => {
    try { await api.post('logout'); } catch { /* session already gone */ }
    window.location.replace('login.html');
  });

  const content = $('#content');

  // Platform admins have no office, so tenant pages have nothing to show.
  if (!me.tenant && page !== 'settings') {
    mount(content, html`<div class="card"><div class="empty">${icon('shield')}<strong>${t('platform.title')}</strong><span class="ltr" style="max-width:520px">${t('platform.body')}</span></div></div>`);
    return { me, can, content, blocked: true };
  }

  if (permission && !can(permission)) {
    mount(content, html`<div class="card"><div class="empty">${icon('shield')}<strong>${t('common.noaccess')}</strong></div></div>`);
    return { me, can, content, blocked: true };
  }

  return { me, can, content, blocked: false };
}

/** Run a page entry point and surface unexpected errors as a toast. */
export async function run(entry) {
  try {
    await entry();
  } catch (error) {
    if (error.status === 401) return;
    toast(error.message || t('common.error'), 'error');
    const content = $('#content');
    if (content && !content.children.length) {
      mount(content, html`<div class="card"><div class="empty">${icon('alert')}<strong>${error.message || t('common.error')}</strong><button class="btn" onclick="location.reload()">${t('common.retry')}</button></div></div>`);
    }
  }
}
