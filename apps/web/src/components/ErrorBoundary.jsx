import { Component } from 'react';

export class ErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { error: null };
  }

  static getDerivedStateFromError(error) {
    return { error };
  }

  componentDidCatch(error, info) {
    console.error(error, info);
  }

  render() {
    if (this.state.error) {
      return (
        <main className="login-shell">
          <div className="login-panel">
            <p className="eyebrow">PowerMail Core</p>
            <h1>Something failed to load</h1>
            <p className="login-copy">{this.state.error.message || 'Refresh PowerMail and try again.'}</p>
            <button className="primary-button" type="button" onClick={() => window.location.reload()}>
              Reload
            </button>
          </div>
        </main>
      );
    }

    return this.props.children;
  }
}
