import { api } from '../api.js';
import { t } from '../i18n.js';
import { STATUS_COLOR, date, emptyState, html, icon, isOverdue, mount, omr, priorityBadge, serviceName, statusBadge, $ } from '../ui.js';
import { initPage, run } from '../shell.js';

const ORDER = ['open', 'waiting_documents', 'in_progress', 'waiting_payment', 'waiting_external', 'waiting_client', 'inquiry', 'completed', 'cancelled'];

run(async () => {
  const { me, content, blocked } = await initPage({ page: 'dashboard', titleKey: 'dash.title', permission: 'transactions.view' });
  if (blocked) return;

  mount(content, html`
    <p style="color:var(--muted);margin-bottom:-8px">${t('dash.welcome')}، <strong style="color:var(--ink)">${me.user.full_name}</strong></p>
    <section class="kpis" id="kpis">${[1, 2, 3, 4].map(() => html`<div class="card kpi"><div class="skeleton" style="height:14px;width:60%"></div><div class="skeleton" style="height:32px;width:40%"></div></div>`)}</section>
    <div class="grid-2">
      <section class="card">
        <div class="card-head"><h2>${t('dash.recent')}</h2><a class="btn btn-sm" href="transactions.html">${t('dash.view_all')} ${icon('arrow', 'flip')}</a></div>
        <div class="table-wrap" id="recent"></div>
      </section>
      <section class="card">
        <div class="card-head"><h2>${t('dash.by_status')}</h2></div>
        <div class="card-body" id="bars"></div>
      </section>
    </div>`);

  const [{ data: stats }, recent] = await Promise.all([
    api.get('transactions/stats'),
    api.get('transactions?limit=8'),
  ]);

  const activeCount = ['open', 'waiting_documents', 'in_progress', 'waiting_payment', 'waiting_external', 'waiting_client']
    .reduce((sum, key) => sum + (stats.by_status[key] ?? 0), 0);

  const kpi = (label, value, color, note = '', unit = '') => html`
    <div class="card kpi" style="--kpi:${color}">
      <span class="kpi-label">${label}</span>
      <span class="kpi-value num">${value}${unit ? html`<small>${unit}</small>` : ''}</span>
      ${note ? html`<span class="kpi-note">${note}</span>` : ''}
    </div>`;

  mount($('#kpis'), html`
    ${kpi(t('dash.total'), stats.total, 'var(--primary)')}
    ${kpi(t('dash.active'), activeCount, 'var(--info)')}
    ${kpi(t('dash.overdue'), stats.overdue, stats.overdue > 0 ? 'var(--danger)' : 'var(--success)', t('dash.overdue_note'))}
    ${kpi(t('dash.completed_month'), stats.completed_this_month, 'var(--success)')}
    ${kpi(t('dash.fees_month'), omr(stats.office_fees_baisa_this_month), 'var(--accent)', '', t('common.omr'))}`);

  // status distribution
  const max = Math.max(1, ...Object.values(stats.by_status));
  const rows = ORDER.filter((key) => stats.by_status[key]);

  mount($('#bars'), rows.length
    ? html`<div class="bars">${rows.map((key) => html`
        <a class="bar-row" href="transactions.html?status=${key}" style="color:inherit;text-decoration:none">
          <span>${t(`status.${key}`)}</span>
          <span class="bar-track"><span class="bar-fill" style="display:block;width:${(stats.by_status[key] / max) * 100}%;--bar:${STATUS_COLOR[key]}"></span></span>
          <b class="num">${stats.by_status[key]}</b>
        </a>`)}</div>`
    : emptyState(t('dash.empty'), t('dash.empty_hint'), 'transactions'));

  // latest transactions
  mount($('#recent'), recent.data.length
    ? html`<table class="table">
        <thead><tr><th>${t('tx.subject')}</th><th class="hide-sm">${t('tx.service')}</th><th>${t('common.status')}</th><th class="hide-sm">${t('tx.due')}</th></tr></thead>
        <tbody>${recent.data.map((tx) => html`
          <tr class="clickable" data-href="transactions.html?open=${tx.public_id}">
            <td><div class="cell-main">${tx.title}</div><div class="cell-sub"><span class="ref">${tx.reference}</span> · ${tx.client.full_name}</div></td>
            <td class="hide-sm">${serviceName(tx.service)}</td>
            <td>${statusBadge(tx.status)}</td>
            <td class="hide-sm" ${isOverdue(tx) ? html`style="color:var(--danger);font-weight:500"` : ''}>${date(tx.due_at)}</td>
          </tr>`)}</tbody></table>`
    : emptyState(t('dash.empty'), t('dash.empty_hint'), 'transactions'));

  $('#recent').addEventListener('click', (event) => {
    const row = event.target.closest('[data-href]');
    if (row) window.location.href = row.dataset.href;
  });
});
