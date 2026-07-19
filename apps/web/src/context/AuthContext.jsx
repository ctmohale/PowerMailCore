import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { apiGet, apiPost, getAuthToken, setAuthToken } from '../api/client.js';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [token, setToken] = useState(getAuthToken());
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(Boolean(getAuthToken()));
  const [error, setError] = useState('');

  useEffect(() => {
    if (!token) {
      setLoading(false);
      return;
    }

    setLoading(true);
    apiGet('/auth/me')
      .then((response) => setUser(response.user))
      .catch(() => {
        setAuthToken('');
        setToken('');
        setUser(null);
      })
      .finally(() => setLoading(false));
  }, [token]);

  const login = useCallback(async (email, password) => {
    setError('');
    const response = await apiPost('/auth/login', { email, password })
      .catch((requestError) => {
        setError(requestError.message);
        throw requestError;
      });

    setAuthToken(response.token);
    setToken(response.token);
    setUser(response.user);
  }, []);

  const logout = useCallback(() => {
    setAuthToken('');
    setToken('');
    setUser(null);
  }, []);

  const value = useMemo(() => ({
    authenticated: Boolean(token && user),
    error,
    loading,
    login,
    logout,
    token,
    user,
  }), [error, loading, login, logout, token, user]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used inside AuthProvider');
  }

  return context;
}
