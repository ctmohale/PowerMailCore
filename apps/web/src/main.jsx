import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import { ErrorBoundary } from './components/ErrorBoundary.jsx';
import { AuthProvider } from './context/AuthContext.jsx';
import { UiFeedbackProvider } from './context/UiFeedbackContext.jsx';
import './styles.css';

function showStartupError(error) {
  const root = document.getElementById('root');
  if (!root) return;

  root.innerHTML = `
    <main class="login-shell">
      <div class="login-panel">
        <p class="eyebrow">PowerMail Core</p>
        <h1>PowerMail failed to start</h1>
        <p class="login-copy">${String(error?.message || error || 'Unknown error')}</p>
      </div>
    </main>
  `;
}

window.addEventListener('error', (event) => showStartupError(event.error || event.message));
window.addEventListener('unhandledrejection', (event) => showStartupError(event.reason));

try {
  createRoot(document.getElementById('root')).render(
    <StrictMode>
      <ErrorBoundary>
        <UiFeedbackProvider>
          <AuthProvider>
            <App />
          </AuthProvider>
        </UiFeedbackProvider>
      </ErrorBoundary>
    </StrictMode>,
  );
} catch (error) {
  showStartupError(error);
}
