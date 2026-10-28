import { api, query } from '../api.js';
import { t } from '../i18n.js';
import { $, $$, activeBadge, badge, confirmDialog, debounce, emptyState, formData, html, icon, mount, openModal, raw, renderPagination, skeletonRows, toast } from '../ui.js';
import { initPage, run } from '../shell.js';

const LIMIT = 15;

run(async () => {
  const { can, content, blocked } = await initPage({ page: 'clients', titleKey: 'client.title', permission: 'clients.view' });
  if (blocked) return;

  const state = { q: '', status: '', offset: 0 };

  mount(content, html`
    <div class="toolbar">
      <div class="input-group grow">${icon('search')}<input class="input" id="q" type="search" placeholder="${t('client.search')}" aria-label="${t('common.search')}"></div>
      <select class="select" id="status" aria-label="${t('common.status')}">
        <option value="">${t('common.status')}: ${t('common.all')}</option>
        <option value="active">${t('common.active')}</option>
        <option value="inactive">${t('common.inactive')}</option>
      </select>
      ${can('clients.create') ? html`<button class="btn btn-primary" id="new">${icon('plus')}${t('client.new')}</button>` : ''}
    </div>
    <section class="card">
      <div class="table-wrap">
        <table class="table">
          <thead><tr>
            <th>${t('client.name')}</th><th>${t('client.type')}</th><th class="hide-sm">${t('client.contact')}</th>
            <th>${t('common.status')}</th><th class="actions">${t('common.actions')}</th>
          </tr></thead>
          <tbody id="rows"></tbody>
        </table>
      </div>
      <div class="pagination" id="pager" hidden></div>
    </section>`);

  let rows = [];

  async function load() {
    mount($('#rows'), skeletonRows(6, 5));

    const result = await api.get(`clients${query({ q: state.q, status: state.status, limit: LIMIT, offset: state.offset })}`);
    rows = result.data;

    mount($('#rows'), rows.length
      ? html`${rows.map((c, i) => html`
          <tr>
            <td><div class="cell-main">${c.full_name}</div>${c.company_name ? html`<div class="cell-sub">${c.company_name}</div>` : ''}</td>
            <td>${badge(t(c.client_type === 'company' ? 'client.company' : 'client.person'), c.client_type === 'company' ? 'accent' : 'primary')}</td>
            <td class="hide-sm">
              ${c.phone ? html`<div class="ltr num">${c.phone}</div>` : ''}
              ${c.email ? html`<div class="cell-sub ltr">${c.email}</div>` : ''}
              ${!c.phone && !c.email ? t('common.none') : ''}
            </td>
            <td>${activeBadge(c.status)}</td>
            <td class="actions">
              ${can('clients.update') ? html`<button class="btn btn-ghost btn-icon btn-sm" data-edit="${i}" aria-label="${t('common.edit')}" title="${t('common.edit')}">${icon('edit')}</button>` : ''}
              ${can('clients.delete') && c.status === 'active' ? html`<button class="btn btn-ghost btn-icon btn-sm" data-off="${i}" aria-label="${t('common.deactivate')}" title="${t('common.deactivate')}" style="color:var(--danger)">${icon('trash')}</button>` : ''}
            </td>
          </tr>`)}`
      : html`<tr><td colspan="5">${emptyState()}</td></tr>`);

    renderPagination($('#pager'), { total: result.meta.total, limit: LIMIT, offset: state.offset }, (offset) => {
      state.offset = offset;
      load().catch((e) => toast(e.message, 'error'));
    });
  }

  function form(client = null) {
    const c = client ?? { client_type: 'person', preferred_language: 'ar', status: 'active' };

    return html`
      <div class="form-grid">
        <div class="field"><label class="req" for="f-type">${t('client.type')}</label>
          <select class="select" id="f-type" name="client_type">
            <option value="person" ${c.client_type === 'person' ? raw('selected') : ''}>${t('client.person')}</option>
            <option value="company" ${c.client_type === 'company' ? raw('selected') : ''}>${t('client.company')}</option>
          </select></div>
        <div class="field"><label class="req" for="f-name">${t('client.name')}</label>
          <input class="input" id="f-name" name="full_name" maxlength="150" value="${c.full_name ?? ''}" required></div>
        <div class="field span-2" id="company-field"><label for="f-company">${t('client.company_name')}</label>
          <input class="input" id="f-company" name="company_name" maxlength="200" value="${c.company_name ?? ''}"></div>
        <div class="field"><label for="f-phone">${t('client.phone')}</label>
          <input class="input" id="f-phone" name="phone" type="tel" dir="ltr" maxlength="30" value="${c.phone ?? ''}" placeholder="+968…"></div>
        <div class="field"><label for="f-wa">${t('client.whatsapp')}</label>
          <input class="input" id="f-wa" name="whatsapp_number" type="tel" dir="ltr" maxlength="30" value="${c.whatsapp_number ?? ''}" placeholder="+968…"></div>
        <div class="field"><label for="f-email">${t('client.email')}</label>
          <input class="input" id="f-email" name="email" type="email" dir="ltr" maxlength="190" value="${c.email ?? ''}"></div>
        <div class="field"><label for="f-lang">${t('client.language')}</label>
          <select class="select" id="f-lang" name="preferred_language">
            <option value="ar" ${c.preferred_language === 'ar' ? raw('selected') : ''}>${t('lang.ar')}</option>
            <option value="en" ${c.preferred_language === 'en' ? raw('selected') : ''}>${t('lang.en')}</option>
          </select></div>
        ${client ? html`<div class="field"><label for="f-status">${t('common.status')}</label>
          <select class="select" id="f-status" name="status">
            <option value="active" ${c.status === 'active' ? raw('selected') : ''}>${t('common.active')}</option>
            <option value="inactive" ${c.status === 'inactive' ? raw('selected') : ''}>${t('common.inactive')}</option>
          </select></div>` : ''}
        <div class="field span-2"><label for="f-notes">${t('common.notes')}</label>
          <textarea class="textarea" id="f-notes" name="notes" maxlength="5000">${c.notes ?? ''}</textarea></div>
      </div>`;
  }

  function openForm(client = null) {
    const modal = openModal({
      title: t(client ? 'client.edit' : 'client.new'),
      body: form(client),
      submitLabel: t('common.save'),
      onSubmit: async (f) => {
        const body = formData(f);
        if (client) await api.put(`clients/${client.public_id}`, body);
        else await api.post('clients', body);
        toast(t('common.saved'));
        await load();
      },
    });

    const type = $('#f-type', modal.el);
    const sync = () => { $('#company-field', modal.el).style.opacity = type.value === 'company' ? '1' : '.6'; };
    type.addEventListener('change', sync);
    sync();
  }

  $('#new')?.addEventListener('click', () => openForm());

  $('#rows').addEventListener('click', async (event) => {
    const edit = event.target.closest('[data-edit]');
    const off = event.target.closest('[data-off]');

    if (edit) openForm(rows[Number(edit.dataset.edit)]);

    if (off) {
      const client = rows[Number(off.dataset.off)];
      const ok = await confirmDialog({ title: t('common.deactivate'), message: `${t('client.deactivate_q')} (${client.full_name})`, confirmLabel: t('common.deactivate') });
      if (!ok) return;
      await api.delete(`clients/${client.public_id}`);
      toast(t('client.deactivated'));
      await load();
    }
  });

  const search = debounce(() => { state.q = $('#q').value.trim(); state.offset = 0; load().catch((e) => toast(e.message, 'error')); }, 300);
  $('#q').addEventListener('input', search);
  $('#status').addEventListener('change', (e) => { state.status = e.target.value; state.offset = 0; load().catch((er) => toast(er.message, 'error')); });

  await load();
});
