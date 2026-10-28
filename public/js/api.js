import { serverMessage, t } from './i18n.js';

/**
 * API client. Paths are relative to the page, so the app works both at
 * the web root and inside a sub-folder (e.g. /sanad-ai/public/).
 */

let csrfToken = null;

export class ApiError extends Error {
  constructor(message, status, data) {
    super(message);
    this.status = status;
    this.data = data;
  }
}

export function setCsrf(token) {
  csrfToken = token || null;
}

async function request(method, path, { body, form, redirect = true } = {}) {
  const headers = { Accept: 'application/json' };
  let payload;

  if (form) {
    payload = form;
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json; charset=utf-8';
    payload = JSON.stringify(body);
  }

  if (method !== 'GET' && csrfToken) {
    headers['X-CSRF-Token'] = csrfToken;
  }

  let response;

  try {
    response = await fetch(`api/${path}`, { method, headers, body: payload, credentials: 'same-origin' });
  } catch {
    throw new ApiError(t('common.network'), 0, null);
  }

  let data = null;

  if ((response.headers.get('content-type') || '').includes('application/json')) {
    try { data = await response.json(); } catch { data = null; }
  }

  if (!response.ok) {
    const isLogin = path === 'login';

    if (response.status === 401 && !isLogin && redirect) {
      window.location.replace('login.html');
    }

    throw new ApiError(serverMessage(data?.message), response.status, data);
  }

  return data;
}

export const api = {
  get: (path) => request('GET', path),
  /** GET that never redirects to the login page on 401. */
  probe: (path) => request('GET', path, { redirect: false }),
  post: (path, body = {}) => request('POST', path, { body }),
  put: (path, body = {}) => request('PUT', path, { body }),
  delete: (path) => request('DELETE', path),
  upload: (path, form) => request('POST', path, { form }),
};

export function query(params) {
  const q = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== '' && value !== null && value !== undefined && value !== false) q.set(key, String(value));
  }
  const s = q.toString();
  return s ? `?${s}` : '';
}
