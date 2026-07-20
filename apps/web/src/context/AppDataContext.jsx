import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { apiGet } from '../api/client.js';

const AppDataContext = createContext(null);

const initialLogFilters = {
  q: '',
  client_id: '',
  status: '',
  opened: '',
  page: 1,
  per_page: 25,
};

const initialContactFilters = {
  q: '',
  client_id: '',
  status: '',
  audience_id: '',
  page: 1,
  per_page: 25,
};

export function AppDataProvider({ children }) {
  const [options, setOptions] = useState({
    clients: [],
    audiences: [],
    domains: [],
    emailAccounts: [],
    emailTemplates: [],
    marketingSenders: [],
    marketingTemplates: [],
    emailLogStatuses: [],
    marketingContactStatuses: [],
  });
  const [filters, setFilters] = useState(initialLogFilters);
  const [contactFilters, setContactFilters] = useState(initialContactFilters);
  const [logs, setLogs] = useState({ data: [], meta: { page: 1, perPage: 25, total: 0, lastPage: 1 } });
  const [contacts, setContacts] = useState({ data: [], meta: { page: 1, perPage: 25, total: 0, lastPage: 1 } });
  const [loading, setLoading] = useState({ logs: true, contacts: true });
  const [error, setError] = useState({ logs: '', contacts: '', options: '' });

  const loadOptions = useCallback(() => apiGet('/options')
      .then(setOptions)
      .catch((requestError) => {
        setError((current) => ({ ...current, options: requestError.message }));
        throw requestError;
      }), []);

  useEffect(() => {
    loadOptions().catch(() => {});
  }, [loadOptions]);

  const loadLogs = useCallback(() => {
    setLoading((current) => ({ ...current, logs: true }));
    setError((current) => ({ ...current, logs: '' }));

    return apiGet('/email-logs', filters)
      .then(setLogs)
      .catch((requestError) => setError((current) => ({ ...current, logs: requestError.message })))
      .finally(() => setLoading((current) => ({ ...current, logs: false })));
  }, [filters]);

  useEffect(() => {
    loadLogs();
  }, [loadLogs]);

  const loadContacts = useCallback(() => {
    setLoading((current) => ({ ...current, contacts: true }));
    setError((current) => ({ ...current, contacts: '' }));

    return apiGet('/marketing/contacts', contactFilters)
      .then(setContacts)
      .catch((requestError) => setError((current) => ({ ...current, contacts: requestError.message })))
      .finally(() => setLoading((current) => ({ ...current, contacts: false })));
  }, [contactFilters]);

  useEffect(() => {
    loadContacts();
  }, [loadContacts]);

  useEffect(() => {
    function refreshSharedData() {
      loadOptions().catch(() => {});
      loadLogs();
      loadContacts();
    }

    window.addEventListener('powermail:data-changed', refreshSharedData);
    return () => window.removeEventListener('powermail:data-changed', refreshSharedData);
  }, [loadContacts, loadLogs, loadOptions]);

  const updateFilter = useCallback((name, value) => {
    setFilters((current) => ({
      ...current,
      [name]: value,
      page: name === 'page' ? value : 1,
    }));
  }, []);

  const resetFilters = useCallback(() => {
    setFilters(initialLogFilters);
  }, []);

  const updateContactFilter = useCallback((name, value) => {
    setContactFilters((current) => ({
      ...current,
      [name]: value,
      page: name === 'page' ? value : 1,
    }));
  }, []);

  const resetContactFilters = useCallback(() => {
    setContactFilters(initialContactFilters);
  }, []);

  const value = useMemo(() => ({
    options,
    filters,
    contactFilters,
    logs,
    contacts,
    loading,
    error,
    updateFilter,
    updateContactFilter,
    resetFilters,
    resetContactFilters,
    refresh: loadLogs,
    refreshContacts: loadContacts,
    refreshOptions: loadOptions,
  }), [
    contactFilters,
    contacts,
    error,
    filters,
    loadContacts,
    loadLogs,
    loadOptions,
    loading,
    logs,
    options,
    resetContactFilters,
    resetFilters,
    updateContactFilter,
    updateFilter,
  ]);

  return (
    <AppDataContext.Provider value={value}>
      {children}
    </AppDataContext.Provider>
  );
}

export function useAppData() {
  const context = useContext(AppDataContext);

  if (!context) {
    throw new Error('useAppData must be used inside AppDataProvider');
  }

  return context;
}
