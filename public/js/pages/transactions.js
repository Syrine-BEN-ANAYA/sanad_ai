import { api, query } from '../api.js';
import { t } from '../i18n.js';
import {
  $, $$, combobox, date, dateTime, docStatusBadge, emptyState, fileSize, formData, html, icon, isOverdue, mount, omr, openModal,
  priorityBadge, raw, renderPagination, serviceName, skeletonRows, statusBadge, toInputDateTime, toast, debounce,
} from '../ui.js';
import { initPage, run } from '../shell.js';

const LIMIT = 15;
const STATUSES = ['inquiry', 'open', 'waiting_documents', 'in_progress', 'waiting_payment', 'waiting_external', 'waiting_client', 'completed', 'cancelled'];
const DOC_TYPES = ['passport', 'id_card', 'resident_card', 'commercial_register', 'contract', 'power_of_attorney', 'photo', 'other'];

run(async () => {
  const { can, content, blocked } = await initPage({ page: 'transactions', titleKey: 'tx.title', permission: 'transactions.view' });
  if (blocked) return;

  const params = new URLSearchParams(location.search);
  const state = { q: '', status: params.get('status') || '', priority: '', overdue: false, offset: 0 };

  mount(content, html`
    <div class="toolbar">
      <div class="input-group grow">${icon('search')}<input class="input" id="q" type="search" placeholder="${t('common.search')}" aria-label="${t('common.search')}"></div>
      <select class="select" id="status" aria-label="${t('common.status')}">
        <option value="">${t('common.status')}: ${t('common.all')}</option>
        ${STATUSES.map((s) => html`<option value="${s}" ${state.status === s ? raw('selected') : ''}>${t(`status.${s}`)}</option>`)}
      </select>
      <select class="select" id="priority" aria-label="${t('tx.priority')}">
        <option value="">${t('tx.priority')}: ${t('common.all')}</option>
        ${['normal', 'high', 'urgent'].map((p) => html`<option value="${p}">${t(`priority.${p}`)}</option>`)}
      </select>
      <label class="row" style="gap:6px"><input type="checkbox" id="overdue"> ${t('tx.overdue_only')}</label>
      ${can('transactions.create') ? html`<button class="btn btn-primary" id="new">${icon('plus')}${t('tx.new')}</button>` : ''}
    </div>
    <section class="card">
      <div class="table-wrap">
        <table class="table">
          <thead><tr>
            <th>${t('tx.subject')}</th><th class="hide-sm">${t('tx.client')}</th><th class="hide-sm">${t('tx.service')}</th>
            <th>${t('common.status')}</th><th class="hide-sm">${t('tx.priority')}</th><th class="hide-sm">${t('tx.due')}</th>
          </tr></thead>
          <tbody id="rows"></tbody>
        </table>
      </div>
      <div class="pagination" id="pager" hidden></div>
    </section>`);

  let rows = [];

  async function load() {
    mount($('#rows'), skeletonRows(7, 6));
    const result = await api.get(`transactions${query({
      q: state.q, status: state.status, priority: state.priority, overdue: state.overdue ? '1' : '', limit: LIMIT, offset: state.offset,
    })}`);
    rows = result.data;

    mount($('#rows'), rows.length
      ? html`${rows.map((tx, i) => html`
          <tr class="clickable" data-open="${i}" tabindex="0">
            <td><div class="cell-main">${tx.title}</div><div class="cell-sub ref">${tx.reference}</div></td>
            <td class="hide-sm">${tx.client.full_name}</td>
            <td class="hide-sm">${serviceName(tx.service)}</td>
            <td>${statusBadge(tx.status)}</td>
            <td class="hide-sm">${priorityBadge(tx.priority)}</td>
            <td class="hide-sm" ${isOverdue(tx) ? raw('style="color:var(--danger);font-weight:500"') : ''}>${date(tx.due_at)}</td>
          </tr>`)}`
      : html`<tr><td colspan="6">${emptyState()}</td></tr>`);

    renderPagination($('#pager'), { total: result.meta.total, limit: LIMIT, offset: state.offset }, (offset) => {
      state.offset = offset;
      load().catch((e) => toast(e.message, 'error'));
    });
  }

  const reload = () => load().catch((e) => toast(e.message, 'error'));

  /* ---------------- create / edit ---------------- */

  const clientFetcher = async (q) => (await api.get(`clients${query({ q, status: 'active', limit: 8 })}`)).data
    .map((c) => ({ id: c.public_id, label: c.full_name, sub: c.company_name || c.phone || '' }));
  const serviceFetcher = async (q) => (await api.get(`services${query({ q, status: 'active', limit: 8 })}`)).data
    .map((s) => ({ id: s.public_id, label: serviceName(s), sub: `${t('tx.total_fee')}: ${omr(s.official_fee_baisa + s.office_fee_baisa)} ${t('common.omr')}` }));

  async function openForm(tx = null) {
    const assignable = can('transactions.assign') ? (await api.get('users/assignable')).data : [];

    const modal = openModal({
      title: t(tx ? 'tx.edit' : 'tx.new'),
      wide: true,
      body: html`
        <div class="form-grid">
          <div class="field span-2"><label class="req">${t('tx.client')}</label><div id="c-client"></div></div>
          <div class="field span-2"><label class="req">${t('tx.service')}</label><div id="c-service"></div></div>
          <div class="field span-2"><label class="req" for="t-title">${t('tx.subject')}</label>
            <input class="input" id="t-title" name="title" maxlength="255" value="${tx?.title ?? ''}" required></div>
          <div class="field"><label for="t-pri">${t('tx.priority')}</label>
            <select class="select" id="t-pri" name="priority">
              ${['normal', 'high', 'urgent'].map((p) => html`<option value="${p}" ${(tx?.priority ?? 'normal') === p ? raw('selected') : ''}>${t(`priority.${p}`)}</option>`)}
            </select></div>
          <div class="field"><label for="t-due">${t('tx.due')}</label>
            <input class="input" id="t-due" name="due_at" type="datetime-local" dir="ltr" value="${toInputDateTime(tx?.due_at)}"></div>
          ${assignable.length ? html`<div class="field span-2"><label for="t-as">${t('tx.assignee')}</label>
            <select class="select" id="t-as" name="assigned_user_public_id">
              <option value="">${t('tx.unassigned')}</option>
              ${assignable.map((u) => html`<option value="${u.public_id}" ${tx?.assignee?.public_id === u.public_id ? raw('selected') : ''}>${u.full_name} — ${t(`role.${u.role}`)}</option>`)}
            </select></div>` : ''}
          <div class="field span-2"><label for="t-desc">${t('tx.description')}</label>
            <textarea class="textarea" id="t-desc" name="description" maxlength="5000">${tx?.description ?? ''}</textarea></div>
        </div>`,
      onSubmit: async (f) => {
        const d = formData(f);
        const body = {
          client_public_id: d.client_public_id,
          service_public_id: d.service_public_id,
          title: d.title,
          priority: d.priority,
          description: d.description || null,
          due_at: d.due_at || null,
        };
        if ('assigned_user_public_id' in d) body.assigned_user_public_id = d.assigned_user_public_id || null;

        if (tx) await api.put(`transactions/${tx.public_id}`, body);
        else await api.post('transactions', body);

        toast(t('common.saved'));
        await load();
      },
    });

    combobox($('#c-client', modal.el), {
      name: 'client_public_id', placeholder: t('tx.pick_client'), fetcher: clientFetcher,
      value: tx ? { id: tx.client.public_id, label: tx.client.full_name } : null,
    });
    combobox($('#c-service', modal.el), {
      name: 'service_public_id', placeholder: t('tx.pick_service'), fetcher: serviceFetcher,
      value: tx ? { id: tx.service.public_id, label: serviceName(tx.service) } : null,
    });
  }

  /* ---------------- detail ---------------- */

  function openDetail(publicId) {
    const modal = openModal({ title: t('tx.details'), body: html`<div class="empty"><span class="spinner" style="width:26px;height:26px"></span></div>`, wide: true, hideFooter: true });
    const body = $('.modal-body', modal.el);

    async function refresh() {
      const [detail, docs] = await Promise.all([
        api.get(`transactions/${publicId}`),
        can('documents.view') ? api.get(`transactions/${publicId}/documents`) : Promise.resolve({ data: [] }),
      ]);
      draw(detail, docs.data);
    }

    function draw({ data: tx, history, allowed_transitions: next }, docs) {
      const closed = ['completed', 'cancelled'].includes(tx.status);
      const total = tx.service.official_fee_baisa + tx.service.office_fee_baisa;

      mount(body, html`
        <div class="grid-detail">
          <div class="stack">
            <div>
              <div class="row" style="margin-bottom:6px">${statusBadge(tx.status)}${priorityBadge(tx.priority)}<span class="ref">${tx.reference}</span></div>
              <h3 style="font-size:20px">${tx.title}</h3>
              ${tx.description ? html`<p style="color:var(--muted);margin-top:6px;white-space:pre-wrap">${tx.description}</p>` : ''}
            </div>

            <dl class="dl">
              <dt>${t('tx.client')}</dt><dd>${tx.client.full_name}${tx.client.phone ? html` · <span class="ltr num">${tx.client.phone}</span>` : ''}</dd>
              <dt>${t('tx.service')}</dt><dd>${serviceName(tx.service)}</dd>
              <dt>${t('tx.fees')}</dt><dd class="num">${omr(tx.service.official_fee_baisa)} + ${omr(tx.service.office_fee_baisa)} = <b>${omr(total)}</b> ${t('common.omr')}</dd>
              <dt>${t('tx.assignee')}</dt><dd>${tx.assignee?.full_name ?? t('tx.unassigned')}</dd>
              <dt>${t('tx.opened')}</dt><dd>${dateTime(tx.opened_at)}</dd>
              <dt>${t('tx.due')}</dt><dd ${isOverdue(tx) ? raw('style="color:var(--danger);font-weight:500"') : ''}>${tx.due_at ? dateTime(tx.due_at) : t('common.none')}</dd>
            </dl>

            ${can('transactions.update') ? (closed
              ? html`<div class="alert alert-info">${t('tx.closed')}</div>`
              : html`
                <div class="dropzone">
                  <span class="label">${t('tx.change_status')}</span>
                  <div class="row" id="moves">${next.map((s) => html`<button type="button" class="btn btn-sm" data-move="${s}">${t(`status.${s}`)}</button>`)}</div>
                  <input class="input" id="reason" maxlength="500" placeholder="${t('tx.reason')}">
                  <div><button type="button" class="btn btn-sm" id="edit">${icon('edit')}${t('common.edit')}</button></div>
                </div>`) : ''}

            ${can('documents.view') ? html`
              <div>
                <div class="row" style="margin-bottom:8px"><h3 style="font-size:16px;flex:1">${t('doc.title')}</h3></div>
                ${docs.length ? docs.map((d) => html`
                  <div class="doc">
                    <span style="color:var(--muted)">${icon('paperclip')}</span>
                    <div class="doc-main"><strong>${d.original_name}</strong><span>${t(`doc.type.${d.document_type}`) === `doc.type.${d.document_type}` ? d.document_type : t(`doc.type.${d.document_type}`)} · ${fileSize(d.size_bytes)}${d.rejection_reason ? ` · ${d.rejection_reason}` : ''}</span></div>
                    ${docStatusBadge(d.status)}
                    <a class="btn btn-ghost btn-icon btn-sm" href="api/documents/${d.public_id}/download" aria-label="${t('doc.download')}" title="${t('doc.download')}">${icon('download')}</a>
                    ${can('documents.verify') && d.status !== 'verified' ? html`
                      <button type="button" class="btn btn-ghost btn-icon btn-sm" data-verify="${d.public_id}" aria-label="${t('doc.verify')}" title="${t('doc.verify')}" style="color:var(--success)">${icon('check')}</button>
                      <button type="button" class="btn btn-ghost btn-icon btn-sm" data-reject="${d.public_id}" aria-label="${t('doc.reject')}" title="${t('doc.reject')}" style="color:var(--danger)">${icon('x')}</button>` : ''}
                  </div>`) : html`<p style="color:var(--muted)">${t('doc.none')}</p>`}
                ${can('documents.upload') && !closed ? html`
                  <div class="dropzone" style="margin-top:12px">
                    <div class="form-grid">
                      <div class="field"><label for="d-type">${t('doc.type')}</label>
                        <select class="select" id="d-type">${DOC_TYPES.map((k) => html`<option value="${k}">${t(`doc.type.${k}`)}</option>`)}</select></div>
                      <div class="field"><label for="d-file">${t('doc.file')}</label>
                        <input class="input" id="d-file" type="file" accept=".pdf,.png,.jpg,.jpeg,.webp" style="padding:6px"></div>
                    </div>
                    <span class="hint" style="color:var(--faint);font-size:12px">${t('doc.file_hint')}</span>
                    <div><button type="button" class="btn btn-primary btn-sm" id="upload">${icon('upload')}${t('doc.upload')}</button></div>
                  </div>` : ''}
              </div>` : ''}
          </div>

          <div>
            <h3 style="font-size:16px;margin-bottom:14px">${t('tx.history')}</h3>
            <ul class="timeline">${[...history].reverse().map((h) => html`
              <li>
                <div>${h.from_status ? html`${t(`status.${h.from_status}`)} → ` : ''}<b>${t(`status.${h.to_status}`)}</b></div>
                <div class="when">${dateTime(h.created_at)}${h.changed_by ? ` · ${t('tx.by')} ${h.changed_by}` : ''}</div>
                ${h.reason ? html`<div class="why">${h.reason === 'Transaction created.' ? t('tx.created_note') : h.reason}</div>` : ''}
              </li>`)}</ul>
          </div>
        </div>`);

      const guard = (button, fn) => button.addEventListener('click', async () => {
        button.disabled = true;
        try { await fn(); } catch (e) { toast(e.message, 'error'); } finally { button.disabled = false; }
      });

      $$('[data-move]', body).forEach((b) => guard(b, async () => {
        await api.post(`transactions/${publicId}/status`, { status: b.dataset.move, reason: $('#reason', body).value.trim() || null });
        toast(t('tx.status_changed'));
        await refresh();
        reload();
      }));

      $('#edit', body)?.addEventListener('click', () => { modal.close(); openForm(tx); });

      $('#upload', body) && guard($('#upload', body), async () => {
        const file = $('#d-file', body).files[0];
        if (!file) throw new Error(t('doc.choose'));
        const form = new FormData();
        form.set('document_type', $('#d-type', body).value);
        form.set('document', file);
        await api.upload(`transactions/${publicId}/documents`, form);
        toast(t('doc.uploaded'));
        await refresh();
      });

      $$('[data-verify]', body).forEach((b) => guard(b, async () => {
        await api.post(`documents/${b.dataset.verify}/status`, { status: 'verified' });
        toast(t('doc.updated'));
        await refresh();
      }));

      $$('[data-reject]', body).forEach((b) => b.addEventListener('click', () => {
        openModal({
          title: t('doc.reject'),
          danger: true,
          submitLabel: t('doc.reject'),
          body: html`<div class="field"><label class="req" for="rr">${t('doc.reject_reason')}</label><input class="input" id="rr" name="reason" maxlength="500" required></div>`,
          onSubmit: async (f) => {
            await api.post(`documents/${b.dataset.reject}/status`, { status: 'rejected', reason: formData(f).reason });
            toast(t('doc.updated'));
            await refresh();
          },
        });
      }));
    }

    refresh().catch((e) => { toast(e.message, 'error'); modal.close(); });
  }

  /* ---------------- wiring ---------------- */

  $('#new')?.addEventListener('click', () => openForm());
  $('#rows').addEventListener('click', (event) => {
    const row = event.target.closest('[data-open]');
    if (row) openDetail(rows[Number(row.dataset.open)].public_id);
  });
  $('#rows').addEventListener('keydown', (event) => {
    if (event.key === 'Enter') event.target.closest('[data-open]')?.click();
  });

  $('#q').addEventListener('input', debounce(() => { state.q = $('#q').value.trim(); state.offset = 0; reload(); }, 300));
  $('#status').addEventListener('change', (e) => { state.status = e.target.value; state.offset = 0; reload(); });
  $('#priority').addEventListener('change', (e) => { state.priority = e.target.value; state.offset = 0; reload(); });
  $('#overdue').addEventListener('change', (e) => { state.overdue = e.target.checked; state.offset = 0; reload(); });

  await load();

  if (params.get('open')) openDetail(params.get('open'));
});
