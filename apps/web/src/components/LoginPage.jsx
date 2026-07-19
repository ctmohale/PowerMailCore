import { useState } from 'react';
import { useAuth } from '../context/AuthContext.jsx';

export function LoginPage() {
  const { error, login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(event) {
    event.preventDefault();
    setSubmitting(true);

    try {
      await login(email, password);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main className="login-shell">
      <form className="login-panel" onSubmit={handleSubmit}>
        <div>
          <p className="eyebrow">PowerMail Core</p>
          <h1>Sign in</h1>
          <p className="login-copy">Use your existing PowerMail user account.</p>
        </div>

        {error && <div className="alert">{error}</div>}

        <label>
          <span>Email</span>
          <input autoComplete="email" type="email" value={email} onChange={(event) => setEmail(event.target.value)} required />
        </label>

        <label>
          <span>Password</span>
          <input autoComplete="current-password" type="password" value={password} onChange={(event) => setPassword(event.target.value)} required />
        </label>

        <button className="primary-button" type="submit" disabled={submitting}>
          {submitting ? 'Signing in...' : 'Sign in'}
        </button>
      </form>
    </main>
  );
}
