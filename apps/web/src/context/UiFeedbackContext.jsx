import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';

const UiFeedbackContext = createContext(null);

function FeedbackIcon({ tone }) {
  if (tone === 'success') {
    return (
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="m5 12 4 4L19 6" />
      </svg>
    );
  }

  if (tone === 'error') {
    return (
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M12 8v5" />
        <path d="M12 17h.01" />
        <circle cx="12" cy="12" r="9" />
      </svg>
    );
  }

  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M12 8h.01" />
      <path d="M11 12h1v5h1" />
      <circle cx="12" cy="12" r="9" />
    </svg>
  );
}

export function UiFeedbackProvider({ children }) {
  const [dialog, setDialog] = useState(null);
  const [toasts, setToasts] = useState([]);
  const dialogResolver = useRef(null);
  const toastSequence = useRef(0);
  const recentToasts = useRef(new Map());

  const removeToast = useCallback((id) => {
    setToasts((current) => current.filter((toast) => toast.id !== id));
  }, []);

  const toast = useCallback((message, tone = 'info', options = {}) => {
    const text = String(message || '').trim();
    if (!text) return;

    const signature = `${tone}:${text}`;
    const now = Date.now();
    if (now - (recentToasts.current.get(signature) || 0) < 900) return;
    recentToasts.current.set(signature, now);

    const id = ++toastSequence.current;
    const duration = options.duration || (text.startsWith('New API key:') ? 14000 : 4800);
    setToasts((current) => [...current.slice(-3), { id, message: text, tone }]);
    window.setTimeout(() => removeToast(id), duration);
  }, [removeToast]);

  const confirmAction = useCallback((options) => new Promise((resolve) => {
    if (dialogResolver.current) {
      dialogResolver.current(false);
    }

    const settings = typeof options === 'string' ? { message: options } : options || {};
    dialogResolver.current = resolve;
    setDialog({
      title: settings.title || 'Confirm action',
      message: settings.message || 'Are you sure you want to continue?',
      confirmLabel: settings.confirmLabel || 'Confirm',
      cancelLabel: settings.cancelLabel || 'Cancel',
      tone: settings.tone || 'danger',
    });
  }), []);

  const closeDialog = useCallback((confirmed) => {
    dialogResolver.current?.(confirmed);
    dialogResolver.current = null;
    setDialog(null);
  }, []);

  useEffect(() => {
    function handleKeyDown(event) {
      if (event.key === 'Escape' && dialogResolver.current) {
        closeDialog(false);
      }
    }

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [closeDialog]);

  useEffect(() => {
    const requestTriggers = new Map();
    const triggerState = new WeakMap();
    let recentTriggers = [];

    function setLoading(element, requestId = null) {
      if (!element?.isConnected) return;
      const state = triggerState.get(element) || { requests: new Set(), timer: null };
      if (requestId) state.requests.add(requestId);
      window.clearTimeout(state.timer);
      state.timer = window.setTimeout(() => {
        if (!state.requests.size) {
          element.classList.remove('ui-action-pending');
          element.removeAttribute('aria-busy');
        }
      }, requestId ? 15000 : 520);
      triggerState.set(element, state);
      element.classList.add('ui-action-pending');
      element.setAttribute('aria-busy', 'true');
    }

    function captureTrigger(event) {
      const element = event.target.closest('button, a.button, [role="button"], input[type="submit"]');
      if (!element || element.matches(':disabled, [aria-disabled="true"]')) return;
      recentTriggers = [{ element, at: Date.now() }, ...recentTriggers].slice(0, 12);
      setLoading(element);
    }

    function captureSubmit(event) {
      const element = event.submitter || event.target.querySelector('button[type="submit"], input[type="submit"]');
      if (!element) return;
      recentTriggers = [{ element, at: Date.now() }, ...recentTriggers].slice(0, 12);
      setLoading(element);
    }

    function requestStarted(event) {
      recentTriggers = recentTriggers.filter((trigger) => Date.now() - trigger.at < 2500 && trigger.element.isConnected);
      const trigger = recentTriggers[0];
      if (!trigger) return;
      const requestId = event.detail?.id;
      if (!requestId) return;
      requestTriggers.set(requestId, trigger.element);
      setLoading(trigger.element, requestId);
    }

    function requestFinished(event) {
      const requestId = event.detail?.id;
      const element = requestTriggers.get(requestId);
      if (!element) return;
      requestTriggers.delete(requestId);
      const state = triggerState.get(element);
      state?.requests.delete(requestId);
      if (state && !state.requests.size) {
        window.clearTimeout(state.timer);
        window.setTimeout(() => {
          element.classList.remove('ui-action-pending');
          element.removeAttribute('aria-busy');
        }, 140);
      }
    }

    document.addEventListener('click', captureTrigger, true);
    document.addEventListener('submit', captureSubmit, true);
    window.addEventListener('powermail:request-start', requestStarted);
    window.addEventListener('powermail:request-end', requestFinished);

    return () => {
      document.removeEventListener('click', captureTrigger, true);
      document.removeEventListener('submit', captureSubmit, true);
      window.removeEventListener('powermail:request-start', requestStarted);
      window.removeEventListener('powermail:request-end', requestFinished);
    };
  }, []);

  useEffect(() => {
    function promote(element) {
      if (!(element instanceof HTMLElement) || !element.matches('.alert, .notice-panel')) return;
      const message = element.textContent.trim();
      if (!message || element.dataset.feedbackMessage === message) return;
      element.dataset.feedbackMessage = message;
      element.classList.add('feedback-source-hidden');
      toast(message, element.classList.contains('alert') ? 'error' : 'success');
    }

    function scan(node) {
      if (!(node instanceof HTMLElement)) return;
      promote(node);
      node.querySelectorAll?.('.alert, .notice-panel').forEach(promote);
    }

    document.querySelectorAll('.alert, .notice-panel').forEach(promote);
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        scan(mutation.target);
        mutation.addedNodes.forEach(scan);
      });
    });
    observer.observe(document.body, { childList: true, subtree: true, characterData: true });
    return () => observer.disconnect();
  }, [toast]);

  const value = useMemo(() => ({ confirmAction, toast }), [confirmAction, toast]);

  return (
    <UiFeedbackContext.Provider value={value}>
      {children}

      <div className="toast-region" aria-live="polite" aria-label="Notifications">
        {toasts.map((item) => (
          <div className={`app-toast toast-${item.tone}`} role={item.tone === 'error' ? 'alert' : 'status'} key={item.id}>
            <span className="toast-icon"><FeedbackIcon tone={item.tone} /></span>
            <p>{item.message}</p>
            <button type="button" aria-label="Dismiss notification" onClick={() => removeToast(item.id)}>Close</button>
          </div>
        ))}
      </div>

      {dialog && (
        <div className="confirm-dialog-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && closeDialog(false)}>
          <section className="confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message">
            <span className={`confirm-dialog-icon tone-${dialog.tone}`}><FeedbackIcon tone={dialog.tone === 'danger' ? 'error' : 'info'} /></span>
            <div>
              <h2 id="confirm-dialog-title">{dialog.title}</h2>
              <p id="confirm-dialog-message">{dialog.message}</p>
            </div>
            <div className="confirm-dialog-actions">
              <button type="button" className="secondary-button" onClick={() => closeDialog(false)}>{dialog.cancelLabel}</button>
              <button type="button" className={dialog.tone === 'danger' ? 'danger-button' : 'primary-button'} autoFocus onClick={() => closeDialog(true)}>{dialog.confirmLabel}</button>
            </div>
          </section>
        </div>
      )}
    </UiFeedbackContext.Provider>
  );
}

export function useUiFeedback() {
  const context = useContext(UiFeedbackContext);
  if (!context) throw new Error('useUiFeedback must be used inside UiFeedbackProvider');
  return context;
}
