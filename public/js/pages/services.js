import { api, query } from '../api.js';
import { getLang, t } from '../i18n.js';
import { $, activeBadge, confirmDialog, debounce, emptyState, formData, html, icon, ministryName, minutesLabel, mount, omr, openModal, raw, renderPagination, serviceName, skeletonRows, toBaisa, toast } from '../ui.js';
import { initPage, run } from '../shell.js';

const LIMIT = 15;

run(async () => {
  const { can, content, blocked } = await initPage({ page: 'services', titleKey: 'svc.title', permission: 'services.view' });
  if (blocked) return;

  const state = { q: '', status: '', offset: 0 };
  const { data: ministries } = await api.get('ministries');
  const canManage = can('services.manage');

  mount(content, html`
    <div class="toolbar">
      <div class="input-group grow">${icon('search')}<input class="input" id="q" type="search" placeholder="${t('svc.search')}" aria-label="${t('common.search')}"></div>
      <select class="select" id="status" aria-label="${t('common.status')}">
        <option value="">${t('common.status')}: ${t('common.all')}</option>
        <option value="active">${t('common.active')}</option>
        <option value="inactive">${t('common.inactive')}</option>
      </select>
      ${canManage ? html`<button class="btn btn-primary" id="new">${icon('plus')}${t('svc.new')}</button>` : ''}
    </div>
    <section class="card">
      <div class="table-wrap">
        <table class="table">
          <thead><tr>
            <th>${t('svc.title')}</th><th class="hide-sm">${t('svc.ministry')}</th>
            <th>${t('tx.official_fee')}</th><th>${t('tx.office_fee')}</th><th class="hide-sm">${t('svc.sla_short')}</th>
            <th>${t('common.status')}</th>${canManage ? html`<th class="actions">${t('common.actions')}</th>` : ''}
          </tr></thead>
          <tbody id="rows"></tbody>
        </table>
      </div>
      <div class="pagination" id="pager" hidden></div>
    </section>`);

  let rows = [];

  async function load() {
    mount($('#rows'), skeletonRows(6, 7));
    const result = await api.get(`services${query({ q: state.q, status: state.status, limit: LIMIT, offset: state.offset })}`);
    rows = result.data;

    mount($('#rows'), rows.length
      ? html`${rows.map((s, i) => html`
          <tr>
            <td><div class="cell-main">${serviceName(s)}</div><div class="cell-sub">${getLang() === 'ar' ? s.name_en ?? '' : s.name_ar}</div></td>
            <td class="hide-sm">${s.ministry ? html`<span title="${ministryName(s.ministry)}">${s.ministry.code ?? ministryName(s.ministry)}</span>` : t('common.none')}</td>
            <td class="num">${omr(s.official_fee_baisa)} <span class="cell-sub">${t('common.omr')}</span></td>
            <td class="num">${omr(s.office_fee_baisa)} <span class="cell-sub">${t('common.omr')}</span></td>
            <td class="hide-sm num">${minutesLabel(s.target_sla_minutes)}</td>
            <td>${activeBadge(s.status)}</td>
            ${canManage ? html`<td class="actions">
              <button class="btn btn-ghost btn-icon btn-sm" data-edit="${i}" aria-label="${t('common.edit')}" title="${t('common.edit')}">${icon('edit')}</button>
              ${s.status === 'active' ? html`<button class="btn btn-ghost btn-icon btn-sm" data-off="${i}" aria-label="${t('common.deactivate')}" title="${t('common.deactivate')}" style="color:var(--danger)">${icon('trash')}</button>` : ''}
            </td>` : ''}
          </tr>`)}`
      : html`<tr><td colspan="7">${emptyState()}</td></tr>`);

    renderPagination($('#pager'), { total: result.meta.total, limit: LIMIT, offset: state.offset }, (offset) => {
      state.offset = offset;
      load().catch((e) => toast(e.message, 'error'));
    });
  }

  function form(s = null) {
    const v = s ?? { status: 'active', official_fee_baisa: 0, office_fee_baisa: 0 };

    return html`
      <div class="form-grid">
        <div class="field"><label class="req" for="s-ar">${t('svc.name_ar')}</label>
          <input class="input" id="s-ar" name="name_ar" maxlength="200" value="${v.name_ar ?? ''}" required></div>
        <div class="field"><label for="s-en">${t('svc.name_en')}</label>
          <input class="input" id="s-en" name="name_en" dir="ltr" maxlength="200" value="${v.name_en ?? ''}"></div>
        <div class="field span-2"><label for="s-min">${t('svc.ministry')}</label>
          <select class="select" id="s-min" name="ministry_public_id">
            <option value="">${t('svc.no_ministry')}</option>
            ${ministries.map((m) => html`<option value="${m.public_id}" ${s?.ministry?.public_id === m.public_id ? raw('selected') : ''}>${ministryName(m)}</option>`)}
          </select></div>
        <div class="field"><label for="s-off">${t('svc.official_fee')}</label>
          <input class="input num" id="s-off" name="official_fee" inputmode="decimal" dir="ltr" value="${(v.official_fee_baisa / 1000).toFixed(3)}"></div>
        <div class="field"><label for="s-fee">${t('svc.office_fee')}</label>
          <input class="input num" id="s-fee" name="office_fee" inputmode="decimal" dir="ltr" value="${(v.office_fee_baisa / 1000).toFixed(3)}"></div>
        <div class="field"><label for="s-sla">${t('svc.sla')}</label>
          <input class="input num" id="s-sla" name="target_sla_minutes" type="number" min="0" dir="ltr" value="${v.target_sla_minutes ?? ''}"></div>
        ${s ? html`<div class="field"><label for="s-status">${t('common.status')}</label>
          <select class="select" id="s-status" name="status">
            <option value="active" ${v.status === 'active' ? raw('selected') : ''}>${t('common.active')}</option>
            <option value="inactive" ${v.status === 'inactive' ? raw('selected') : ''}>${t('common.inactive')}</option>
          </select></div>` : ''}
        <div class="field span-2"><label for="s-dar">${t('svc.desc_ar')}</label>
          <textarea class="textarea" id="s-dar" name="description_ar" maxlength="5000">${v.description_ar ?? ''}</textarea></div>
        <div class="field span-2"><label for="s-den">${t('svc.desc_en')}</label>
          <textarea class="textarea" id="s-den" name="description_en" dir="ltr" maxlength="5000">${v.description_en ?? ''}</textarea></div>
      </div>`;
  }

  function openForm(service = null) {
    openModal({
      title: t(service ? 'svc.edit' : 'svc.new'),
      body: form(service),
      wide: true,
      onSubmit: async (f) => {
        const d = formData(f);
        const official = toBaisa(d.official_fee || '0');
        const office = toBaisa(d.office_fee || '0');

        if (Number.isNaN(official) || Number.isNaN(office)) throw new Error('Invalid fee amount.');

        const body = {
          name_ar: d.name_ar,
          name_en: d.name_en,
          description_ar: d.description_ar,
          description_en: d.description_en,
          ministry_public_id: d.ministry_public_id || null,
          official_fee_baisa: official,
          office_fee_baisa: office,
          target_sla_minutes: d.target_sla_minutes === '' ? null : Number(d.target_sla_minutes),
        };

        if (service) await api.put(`services/${service.public_id}`, { ...body, status: d.status });
        else await api.post('services', body);

        toast(t('common.saved'));
        await load();
      },
    });
  }

  $('#new')?.addEventListener('click', () => openForm());

  $('#rows').addEventListener('click', async (event) => {
    const edit = event.target.closest('[data-edit]');
    const off = event.target.closest('[data-off]');

    if (edit) openForm(rows[Number(edit.dataset.edit)]);

    if (off) {
      const service = rows[Number(off.dataset.off)];
      const ok = await confirmDialog({ title: t('common.deactivate'), message: `${t('svc.deactivate_q')} (${serviceName(service)})`, confirmLabel: t('common.deactivate') });
      if (!ok) return;
      await api.delete(`services/${service.public_id}`);
      toast(t('svc.deactivated'));
      await load();
    }
  });

  $('#q').addEventListener('input', debounce(() => { state.q = $('#q').value.trim(); state.offset = 0; load().catch((e) => toast(e.message, 'error')); }, 300));
  $('#status').addEventListener('change', (e) => { state.status = e.target.value; state.offset = 0; load().catch((er) => toast(er.message, 'error')); });

  await load();
});
