import { api } from '../api.js';
import { getLang, setLang, t } from '../i18n.js';
import { $, badge, html, mount, raw, toast } from '../ui.js';
import { applyTheme, initPage, run } from '../shell.js';

run(async () => {
  const { me, content } = await initPage({ page: 'settings', titleKey: 'set.title' });

  const theme = document.documentElement.dataset.theme;

  mount(content, html`
    <div class="grid-detail">
      <div class="stack">
        <section class="card">
          <div class="card-head"><h2>${t('set.profile')}</h2></div>
          <div class="card-body">
            <dl class="dl">
              <dt>${t('user.full_name')}</dt><dd>${me.user.full_name}</dd>
              <dt>${t('user.username')}</dt><dd class="ltr">${me.user.username}</dd>
              <dt>${t('user.role')}</dt><dd>${badge(t(`role.${me.user.role}`), 'primary')}</dd>
              ${me.tenant ? html`<dt>${t('common.office')}</dt><dd>${me.tenant.name}</dd>` : ''}
            </dl>
          </div>
        </section>

        <section class="card">
          <div class="card-head"><h2>${t('set.password')}</h2></div>
          <form class="card-body stack" id="pw" novalidate>
            <div class="alert alert-danger" id="pw-error" hidden></div>
            <div class="field"><label for="p-cur">${t('set.current_password')}</label>
              <input class="input" id="p-cur" name="current_password" type="password" autocomplete="current-password" required></div>
            <div class="form-grid">
              <div class="field"><label for="p-new">${t('set.new_password')}</label>
                <input class="input" id="p-new" name="new_password" type="password" autocomplete="new-password" required>
                <span class="hint">${t('user.password_hint')}</span></div>
              <div class="field"><label for="p-conf">${t('set.confirm_password')}</label>
                <input class="input" id="p-conf" name="confirm" type="password" autocomplete="new-password" required></div>
            </div>
            <div><button class="btn btn-primary" type="submit">${t('common.save')}</button></div>
          </form>
        </section>
      </div>

      <div class="stack">
        <section class="card">
          <div class="card-head"><h2>${t('set.appearance')}</h2></div>
          <div class="card-body stack">
            <div class="field"><label for="a-lang">${t('set.language')}</label>
              <select class="select" id="a-lang">
                <option value="ar" ${getLang() === 'ar' ? raw('selected') : ''}>العربية</option>
                <option value="en" ${getLang() === 'en' ? raw('selected') : ''}>English</option>
              </select></div>
            <div class="field"><label for="a-theme">${t('set.theme')}</label>
              <select class="select" id="a-theme">
                <option value="light" ${theme === 'light' ? raw('selected') : ''}>${t('set.theme_light')}</option>
                <option value="dark" ${theme === 'dark' ? raw('selected') : ''}>${t('set.theme_dark')}</option>
              </select></div>
          </div>
        </section>

        <section class="card">
          <div class="card-head"><h2>${t('set.permissions')}</h2></div>
          <div class="card-body row">${me.permissions.map((p) => html`<span class="badge ltr">${p}</span>`)}</div>
        </section>
      </div>
    </div>`);

  $('#a-lang').addEventListener('change', (e) => { setLang(e.target.value); location.reload(); });
  $('#a-theme').addEventListener('change', (e) => {
    try { localStorage.setItem('sanad.theme', e.target.value); } catch { /* ignore */ }
    applyTheme();
  });

  const form = $('#pw');
  const error = $('#pw-error');

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    error.hidden = true;

    const data = Object.fromEntries(new FormData(form));

    if (data.new_password !== data.confirm) {
      error.textContent = t('set.mismatch');
      error.hidden = false;
      return;
    }

    try {
      await api.post('me/password', { current_password: data.current_password, new_password: data.new_password });
      form.reset();
      toast(t('set.password_changed'));
    } catch (e) {
      error.textContent = e.message;
      error.hidden = false;
    }
  });
});
