const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:4000/api';

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
  requestEvent('powermail:request-start', { id: requestId, method: options.method || 'GET', path });

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

    if (response.status === 204) {
      return null;
    }

    return response.json();
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
