import { useMemo, useState } from 'react';

function cleanBaseUrl(value) {
  return String(value || '').replace(/\/$/, '');
}

export function ApiIntegrationGuide() {
  const [copied, setCopied] = useState('');
  const baseUrl = cleanBaseUrl(import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:4000/api');
  const examples = useMemo(() => [
    {
      id: 'send',
      method: 'POST',
      title: 'Send email',
      endpoint: '/send',
      code: `curl -X POST ${baseUrl}/send \\
  -H "Authorization: Bearer YOUR_API_KEY" \\
  -H "Content-Type: application/json" \\
  -d '{"from_email":"info@example.com","to":"client@example.com","template_key":"welcome","data":{"name":"Client"}}'`,
    },
    {
      id: 'inbox',
      method: 'GET',
      title: 'Read inbox',
      endpoint: '/inbox',
      code: `curl "${baseUrl}/inbox?status=unopened&mailbox=inbox" \\
  -H "Authorization: Bearer YOUR_API_KEY"`,
    },
    {
      id: 'templates',
      method: 'GET',
      title: 'Read templates',
      endpoint: '/templates',
      code: `curl "${baseUrl}/templates" \\
  -H "Authorization: Bearer YOUR_API_KEY"`,
    },
    {
      id: 'accounts',
      method: 'GET',
      title: 'Sending accounts',
      endpoint: '/sending-accounts',
      code: `curl "${baseUrl}/sending-accounts" \\
  -H "Authorization: Bearer YOUR_API_KEY"`,
    },
  ], [baseUrl]);

  async function copy(value, id) {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(id);
      window.setTimeout(() => setCopied((current) => current === id ? '' : current), 1600);
    } catch {
      setCopied('');
    }
  }

  return (
    <details className="api-integration-guide" open>
      <summary>
        <div>
          <span className="api-guide-kicker">Developer Guide</span>
          <strong>Integration Instructions</strong>
          <small>Server-side authentication and ready-to-use request examples</small>
        </div>
        <span className="api-guide-toggle" aria-hidden="true">Hide</span>
      </summary>

      <div className="api-guide-content">
        <div className="api-guide-overview">
          <div className="api-base-url">
            <span>Base URL</span>
            <code>{baseUrl}</code>
            <button type="button" onClick={() => copy(baseUrl, 'base')}>{copied === 'base' ? 'Copied' : 'Copy'}</button>
          </div>
          <div className="api-auth-note">
            <strong>Authentication</strong>
            <p>Keep the key on your server. Send <code>Authorization: Bearer YOUR_API_KEY</code>, or include <code>api_key</code> in JSON.</p>
          </div>
        </div>

        <div className="api-ability-row" aria-label="API key abilities">
          <span><code>send</code> Send mail</span>
          <span><code>templates</code> Read active templates</span>
          <span><code>inbox</code> Read received emails</span>
        </div>

        <div className="api-example-grid">
          {examples.map((example) => (
            <article className="api-example" key={example.id}>
              <header>
                <div>
                  <span className={`api-method api-method-${example.method.toLowerCase()}`}>{example.method}</span>
                  <strong>{example.title}</strong>
                  <code>{example.endpoint}</code>
                </div>
                <button type="button" onClick={() => copy(example.code, example.id)}>{copied === example.id ? 'Copied' : 'Copy'}</button>
              </header>
              <pre><code>{example.code}</code></pre>
            </article>
          ))}
        </div>
      </div>
    </details>
  );
}
