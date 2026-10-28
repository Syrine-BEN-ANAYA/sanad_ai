import { api, query } from '../api.js';
import { t } from '../i18n.js';
import { $, badge, dateTime, debounce, emptyState, formData, html, icon, mount, openModal, raw, renderPagination, skeletonRows, toast } from '../ui.js';
import { initPage, run } from '../shell.js';

const LIMIT = 15;
const STATUS_TONE = { active: 'success', inactive: 'neutral', invited: 'info', locked: 'danger' };

run(async () => {
  const { me, can, content, blocked } = await initPage({ page: 'users', titleKey: 'user.title', permission: 'users.view' });
  if (blocked) return;

  const state = { q: '', offset: 0 };
  const { data: roles } = await api.get('roles');

  mount(content, html`
    <div class="toolbar">
      <div class="input-group grow">${icon('search')}<input class="input" id="q" type="search" placeholder="${t('user.search')}" aria-label="${t('common.search')}"></div>
      ${can('users.create') ? html`<button class="btn btn-primary" id="new">${icon('plus')}${t('user.new')}</button>` : ''}
    </div>
    <section class="card">
      <div class="table-wrap">
        <table class="table">
          <thead><tr>
            <th>${t('user.full_name')}</th><th>${t('user.role')}</th><th class="hide-sm">${t('user.last_login')}</th>
            <th>${t('common.status')}</th>${can('users.update') ? html`<th class="actions">${t('common.actions')}</th>` : ''}
          </tr></thead>
          <tbody id="rows"></tbody>
        </table>
      </div>
      <div class="pagination" id="pager" hidden></div>
    </section>`);

  let rows = [];

  async function load() {
    mount($('#rows'), skeletonRows(5, 5));
    const result = await api.get(`users${query({ q: state.q, limit: LIMIT, offset: state.offset })}`);
    rows = result.data;

    mount($('#rows'), rows.length
      ? html`${rows.map((u, i) => html`
          <tr>
            <td><div class="cell-main">${u.full_name}</div><div class="cell-sub ltr">${u.username}</div></td>
            <td>${badge(t(`role.${u.role}`), 'primary')}</td>
            <td class="hide-sm">${u.last_login_at ? dateTime(u.last_login_at) : t('user.never')}</td>
            <td>${badge(t(`user.status.${u.status}`), STATUS_TONE[u.status] ?? 'neutral')}</td>
            ${can('users.update') ? html`<td class="actions">
              <button class="btn btn-ghost btn-icon btn-sm" data-edit="${i}" aria-label="${t('common.edit')}" title="${t('common.edit')}">${icon('edit')}</button>
            </td>` : ''}
          </tr>`)}`
      : html`<tr><td colspan="5">${emptyState()}</td></tr>`);

    renderPagination($('#pager'), { total: result.meta.total, limit: LIMIT, offset: state.offset }, (offset) => {
      state.offset = offset;
      load().catch((e) => toast(e.message, 'error'));
    });
  }

  const roleOptions = (current) => roles.map((r) => html`<option value="${r.slug}" ${current === r.slug ? raw('selected') : ''}>${t(`role.${r.slug}`)}</option>`);

  function openForm(user = null) {
    const isSelf = user?.public_id === me.user.public_id;
    const roleChoices = user && !roles.some((r) => r.slug === user.role)
      ? html`<option value="${user.role}" selected>${t(`role.${user.role}`)}</option>${roleOptions(user.role)}`
      : roleOptions(user?.role);

    openModal({
      title: t(user ? 'user.edit' : 'user.new'),
      body: html`
        <div class="form-grid">
          <div class="field"><label class="req" for="u-name">${t('user.full_name')}</label>
            <input class="input" id="u-name" name="full_name" maxlength="150" value="${user?.full_name ?? ''}" required></div>
          <div class="field"><label class="req" for="u-user">${t('user.username')}</label>
            <input class="input" id="u-user" name="username" dir="ltr" maxlength="100" autocomplete="off" value="${user?.username ?? ''}" ${user ? raw('disabled') : raw('required')}></div>
          ${user ? '' : html`<div class="field span-2"><label class="req" for="u-pass">${t('user.password')}</label>
            <input class="input" id="u-pass" name="password" type="password" dir="ltr" autocomplete="new-password" required>
            <span class="hint">${t('user.password_hint')}</span></div>`}
          <div class="field"><label class="req" for="u-role">${t('user.role')}</label>
            <select class="select" id="u-role" name="role" ${isSelf ? raw('disabled') : ''}>${roleChoices}</select></div>
          ${user ? html`<div class="field"><label for="u-status">${t('common.status')}</label>
            <select class="select" id="u-status" name="status" ${isSelf ? raw('disabled') : ''}>
              ${['active', 'inactive'].map((s) => html`<option value="${s}" ${user.status === s ? raw('selected') : ''}>${t(`user.status.${s}`)}</option>`)}
            </select></div>` : ''}
        </div>`,
      onSubmit: async (f) => {
        const d = formData(f);
        if (user) {
          await api.put(`users/${user.public_id}`, {
            full_name: d.full_name,
            role: isSelf ? user.role : d.role,
            status: isSelf ? user.status : d.status,
          });
        } else {
          await api.post('users', { username: d.username, password: d.password, full_name: d.full_name, role: d.role });
        }
        toast(t('common.saved'));
        await load();
      },
    });
  }

  $('#new')?.addEventListener('click', () => openForm());
  $('#rows').addEventListener('click', (event) => {
    const edit = event.target.closest('[data-edit]');
    if (edit) openForm(rows[Number(edit.dataset.edit)]);
  });
  $('#q').addEventListener('input', debounce(() => { state.q = $('#q').value.trim(); state.offset = 0; load().catch((e) => toast(e.message, 'error')); }, 300));

  await load();
});
