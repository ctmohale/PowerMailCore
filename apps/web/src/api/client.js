const API_BASE_URL = new URL(
  import.meta.env.VITE_API_BASE_URL || '/api',
  window.location.origin,
).toString().replace(/\/$/, '');

let authToken = window.localStorage.getItem('powermail_node_token') || '';
let requestSequence = 0;

function requestEvent(name, detail) {
  window.dispatchEvent(new CustomEvent(name, { detail }));
}

export function setAuthToken(token) {
  authToken = token || '';

  if (authToken) {
    window.localStorage.setItem('powermail_node_token', authToken);
  } else {
    window.localStorage.removeItem('powermail_node_token');
  }
}

export function getAuthToken() {
  return authToken;
}

async function apiRequest(path, options = {}) {
  const requestId = ++requestSequence;
  const method = String(options.method || 'GET').toUpperCase();
  requestEvent('powermail:request-start', { id: requestId, method, path });

  try {
    const response = await fetch(`${API_BASE_URL}${path}`, {
      ...options,
      headers: {
        Accept: 'application/json',
        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
        ...(options.headers || {}),
      },
    });

    if (!response.ok) {
      const body = await response.json().catch(() => null);
      throw new Error(body?.error || body?.message || `Request failed with ${response.status}`);
    }

    const result = response.status === 204 ? null : await response.json();

    if (method !== 'GET' && method !== 'HEAD') {
      requestEvent('powermail:data-changed', { method, path });
    }

    return result;
  } finally {
    requestEvent('powermail:request-end', { id: requestId });
  }
}

export async function apiGet(path, params = {}) {
  const url = new URL(`${API_BASE_URL}${path}`);

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, value);
    }
  });

  return apiRequest(`${url.pathname.replace('/api', '')}${url.search}`);
}

export async function apiPost(path, body = {}) {
  return apiRequest(path, {
    method: 'POST',
    body: JSON.stringify(body),
  });
}

export async function apiPatch(path, body = {}) {
  return apiRequest(path, {
    method: 'PATCH',
    body: JSON.stringify(body),
  });
}

export async function apiDelete(path, body = null) {
  return apiRequest(path, {
    method: 'DELETE',
    ...(body ? { body: JSON.stringify(body) } : {}),
  });
}
