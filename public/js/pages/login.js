import { api, setCsrf } from '../api.js';
import { applyDocumentLang, getLang, setLang, t } from '../i18n.js';
import { $, html, icon, mount } from '../ui.js';
import { applyTheme, toggleTheme } from '../shell.js';

applyDocumentLang();
applyTheme();
document.title = `${t('login.title')} — ${t('app.name')}`;

mount($('#app'), html`
  <div class="auth">
    <section class="auth-art" aria-hidden="false">
      <div class="brand">
        <div class="brand-mark">${getLang() === 'ar' ? 'س' : 'S'}</div>
        <div class="brand-text"><strong>${t('app.name')}</strong><span>${t('app.tagline')}</span></div>
      </div>
      <div>
        <h2>${t('login.hero')}</h2>
        <p class="lead">${t('login.lead')}</p>
      </div>
      <div class="auth-points">
        <div>${icon('check')}<span>${t('login.p1')}</span></div>
        <div>${icon('shield')}<span>${t('login.p2')}</span></div>
        <div>${icon('users')}<span>${t('login.p3')}</span></div>
      </div>
    </section>

    <section class="auth-panel">
      <div class="auth-tools">
        <button class="btn btn-ghost btn-sm" id="lang">${icon('globe')}<span>${t('lang.toggle')}</span></button>
        <button class="btn btn-ghost btn-icon btn-sm" id="theme" aria-label="${t('theme.toggle')}">${icon('moon')}</button>
      </div>

      <form class="auth-card" id="form" novalidate>
        <div>
          <h1>${t('login.title')}</h1>
          <p class="sub">${t('login.subtitle')}</p>
        </div>

        <div class="alert alert-danger" id="error" role="alert" hidden></div>

        <div class="field">
          <label for="username">${t('login.username')}</label>
          <div class="input-group">
            ${icon('clients')}
            <input class="input" id="username" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
          </div>
        </div>

        <div class="field">
          <label for="password">${t('login.password')}</label>
          <div class="input-group">
            ${icon('shield')}
            <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
            <button type="button" class="btn btn-ghost btn-icon btn-sm reveal" id="reveal" aria-label="${t('login.show')}">${icon('eye')}</button>
          </div>
        </div>

        <button class="btn btn-primary" id="submit" type="submit">${t('login.submit')}</button>
        <div class="demo-box" id="demo" hidden></div>
        <p class="auth-foot">© ${new Date().getFullYear()} ${t('app.name')} — ${t('login.footer')}</p>
      </form>
    </section>
  </div>`);

$('#lang').addEventListener('click', () => { setLang(getLang() === 'ar' ? 'en' : 'ar'); location.reload(); });
$('#theme').addEventListener('click', toggleTheme);

$('#reveal').addEventListener('click', () => {
  const input = $('#password');
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  $('#reveal').innerHTML = icon(show ? 'eyeOff' : 'eye').value;
  $('#reveal').setAttribute('aria-label', t(show ? 'login.hide' : 'login.show'));
});

const form = $('#form');
const errorBox = $('#error');
const submit = $('#submit');

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  errorBox.hidden = true;

  const username = $('#username').value.trim();
  const password = $('#password').value;

  if (!username || !password) {
    errorBox.textContent = t('login.subtitle');
    errorBox.hidden = false;
    return;
  }

  submit.disabled = true;
  submit.innerHTML = '<span class="spinner"></span>';

  try {
    const result = await api.post('login', { username, password });
    setCsrf(result.csrf_token);
    window.location.replace('index.html');
  } catch (error) {
    errorBox.textContent = error.message;
    errorBox.hidden = false;
    submit.disabled = false;
    submit.textContent = t('login.submit');
    $('#password').value = '';
    $('#password').focus();
  }
});

// Already signed in? Skip the form.
api.probe('me').then(() => window.location.replace('index.html')).catch(() => {});

// Demo logins (only returned by the server outside production, once seeded).
api.probe('demo-accounts').then((result) => {
  const accounts = result.accounts || [];
  if (!accounts.length) return;

  const box = $('#demo');
  mount(box, html`
    <div class="demo-head"><strong>${t('login.demo.title')}</strong><span>${t('login.demo.hint')}</span></div>
    <div class="demo-list">
      ${accounts.map((a, i) => html`
        <button type="button" class="demo-item" data-i="${i}">
          <span class="demo-role">${t('role.' + a.role)}</span>
          <code dir="ltr">${a.username}</code>
        </button>`)}
    </div>
    <p class="demo-pass">${t('login.demo.password')}: <code dir="ltr">${accounts[0].password}</code></p>`);
  box.hidden = false;

  box.addEventListener('click', (event) => {
    const button = event.target.closest('.demo-item');
    if (!button) return;
    const account = accounts[Number(button.dataset.i)];
    $('#username').value = account.username;
    $('#password').value = account.password;
    errorBox.hidden = true;
    $('#submit').focus();
  });
}).catch(() => {});
