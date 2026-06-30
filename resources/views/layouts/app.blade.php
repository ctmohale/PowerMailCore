<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PowerMail Core')</title>
    <style>
        :root {
            --bg: #f6f7f9;
            --surface: #ffffff;
            --surface-soft: #f9fafb;
            --ink: #111827;
            --muted: #667085;
            --line: #e4e7ec;
            --accent: #0f766e;
            --accent-dark: #115e59;
            --accent-soft: #e6f5f2;
            --blue: #2563eb;
            --blue-soft: #eaf1ff;
            --danger: #b42318;
            --danger-soft: #fee4e2;
            --warning: #a15c07;
            --warning-soft: #fef0c7;
            --success: #067647;
            --success-soft: #dcfae6;
            --shadow: 0 18px 42px rgba(16, 24, 40, 0.07);
            --radius: 8px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-width: 320px;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 14px;
            line-height: 1.45;
        }

        a {
            color: var(--accent-dark);
            text-decoration: none;
        }

        a:hover {
            color: var(--blue);
        }

        .topbar {
            background: rgba(255, 255, 255, 0.96);
            border-bottom: 1px solid var(--line);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .topbar-inner {
            align-items: center;
            display: flex;
            gap: 18px;
            margin: 0 auto;
            max-width: 1280px;
            min-height: 68px;
            padding: 0 24px;
        }

        .brand {
            align-items: center;
            color: var(--ink);
            display: inline-flex;
            flex: 0 0 auto;
            font-weight: 800;
            gap: 10px;
            letter-spacing: 0;
        }

        .brand-mark {
            align-items: center;
            background: var(--accent);
            border-radius: 8px;
            color: #fff;
            display: inline-flex;
            font-size: 14px;
            height: 34px;
            justify-content: center;
            width: 34px;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }

        .brand-text small {
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            margin-top: 2px;
        }

        .nav {
            display: flex;
            flex: 1;
            flex-wrap: wrap;
            gap: 4px;
        }

        .nav a {
            border-radius: 7px;
            color: #475467;
            font-size: 13px;
            font-weight: 700;
            padding: 8px 10px;
            white-space: nowrap;
        }

        .nav a.active,
        .nav a:hover {
            background: var(--accent-soft);
            color: var(--accent-dark);
        }

        .user-actions {
            align-items: center;
            display: flex;
            gap: 10px;
            white-space: nowrap;
        }

        .container {
            margin: 0 auto;
            max-width: 1280px;
            padding: 28px 24px 52px;
        }

        .auth-container {
            align-items: center;
            display: flex;
            min-height: calc(100vh - 68px);
            justify-content: center;
            padding: 28px 20px;
        }

        .page-header {
            align-items: flex-end;
            display: flex;
            gap: 18px;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .page-title {
            min-width: 0;
        }

        h1 {
            font-size: clamp(25px, 3vw, 34px);
            letter-spacing: 0;
            line-height: 1.12;
            margin: 0;
        }

        h2 {
            font-size: 16px;
            line-height: 1.2;
            margin: 0;
        }

        .eyebrow {
            color: var(--accent-dark);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0;
            margin: 0 0 7px;
            text-transform: uppercase;
        }

        .lede {
            color: var(--muted);
            margin: 8px 0 0;
            max-width: 680px;
        }

        .muted {
            color: var(--muted);
        }

        .panel,
        .metric,
        .auth-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .panel {
            margin-bottom: 22px;
            padding: 20px;
        }

        .panel.compact,
        .auth-card {
            max-width: 430px;
            width: 100%;
        }

        .panel-header {
            align-items: flex-start;
            display: flex;
            gap: 14px;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .panel-header p {
            color: var(--muted);
            margin: 6px 0 0;
        }

        .grid,
        .kpi-grid {
            display: grid;
            gap: 14px;
        }

        .grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .kpi-grid {
            grid-template-columns: repeat(6, minmax(0, 1fr));
            margin-bottom: 22px;
        }

        .split-grid {
            display: grid;
            gap: 22px;
            grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.65fr);
        }

        .form-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .form-grid.three {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .metric {
            min-height: 116px;
            padding: 16px;
        }

        .metric-top {
            align-items: center;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .metric-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .metric-value {
            display: block;
            font-size: 28px;
            font-weight: 850;
            letter-spacing: 0;
            line-height: 1;
            margin-top: 12px;
        }

        .metric-hint {
            color: var(--muted);
            display: block;
            font-size: 12px;
            margin-top: 10px;
        }

        .metric[data-tone="green"] .metric-dot { background: var(--success); }
        .metric[data-tone="blue"] .metric-dot { background: var(--blue); }
        .metric[data-tone="amber"] .metric-dot { background: #d97706; }
        .metric[data-tone="red"] .metric-dot { background: var(--danger); }

        .metric-dot {
            border-radius: 999px;
            height: 9px;
            width: 9px;
        }

        .stack {
            display: grid;
            gap: 12px;
        }

        .summary-list {
            display: grid;
            gap: 10px;
            margin: 0;
            padding: 0;
        }

        .summary-item {
            align-items: center;
            background: var(--surface-soft);
            border: 1px solid var(--line);
            border-radius: 7px;
            display: flex;
            justify-content: space-between;
            padding: 12px;
        }

        .summary-item strong {
            color: var(--ink);
        }

        .subform {
            border-top: 1px solid var(--line);
            margin-top: 16px;
            padding-top: 16px;
        }

        .subform:first-of-type {
            border-top: 0;
            margin-top: 0;
            padding-top: 0;
        }

        .bar {
            background: #edf0f3;
            border-radius: 999px;
            height: 8px;
            overflow: hidden;
            width: 100%;
        }

        .bar span {
            background: var(--accent);
            border-radius: inherit;
            display: block;
            height: 100%;
            min-width: 3px;
        }

        .quick-actions {
            display: grid;
            gap: 10px;
        }

        .quick-link {
            align-items: center;
            background: var(--surface-soft);
            border: 1px solid var(--line);
            border-radius: 7px;
            color: var(--ink);
            display: flex;
            font-weight: 750;
            justify-content: space-between;
            min-height: 46px;
            padding: 12px;
        }

        .quick-link:hover {
            background: var(--blue-soft);
            color: var(--blue);
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            color: #344054;
            font-size: 12px;
            font-weight: 800;
        }

        input,
        select,
        textarea {
            background: #fff;
            border: 1px solid #cfd6df;
            border-radius: 7px;
            color: var(--ink);
            font: inherit;
            min-height: 42px;
            padding: 9px 11px;
            width: 100%;
        }

        textarea {
            min-height: 124px;
            resize: vertical;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--accent);
            outline: 3px solid rgba(15, 118, 110, 0.13);
        }

        .checkbox {
            align-items: center;
            flex-direction: row;
            gap: 9px;
            min-height: 42px;
        }

        .checkbox input {
            min-height: auto;
            width: auto;
        }

        .actions {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        button,
        .button {
            align-items: center;
            background: var(--accent);
            border: 0;
            border-radius: 7px;
            color: #fff;
            cursor: pointer;
            display: inline-flex;
            font: inherit;
            font-weight: 800;
            justify-content: center;
            min-height: 40px;
            padding: 9px 14px;
        }

        button:hover,
        .button:hover {
            background: var(--accent-dark);
            color: #fff;
        }

        .button.secondary,
        button.secondary {
            background: #eef2f6;
            color: #344054;
        }

        .button.secondary:hover,
        button.secondary:hover {
            background: #e4e7ec;
            color: var(--ink);
        }

        .button.ghost {
            background: transparent;
            border: 1px solid var(--line);
            color: var(--ink);
        }

        .table-wrap {
            border: 1px solid var(--line);
            border-radius: var(--radius);
            overflow-x: auto;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border-bottom: 1px solid var(--line);
            padding: 12px;
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        tbody tr:hover {
            background: #fafbfc;
        }

        th {
            background: var(--surface-soft);
            color: var(--muted);
            font-size: 11px;
            font-weight: 850;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        td.wrap {
            max-width: 420px;
            white-space: normal;
        }

        .badge {
            align-items: center;
            border-radius: 999px;
            display: inline-flex;
            font-size: 12px;
            font-weight: 850;
            line-height: 1;
            min-height: 24px;
            padding: 6px 9px;
            text-transform: capitalize;
        }

        .badge.sent,
        .badge.active {
            background: var(--success-soft);
            color: var(--success);
        }

        .badge.failed {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .badge.pending {
            background: var(--warning-soft);
            color: var(--warning);
        }

        .badge.opened,
        .badge.clicked,
        .badge.info {
            background: var(--blue-soft);
            color: var(--blue);
        }

        .alert,
        .notice {
            border-radius: 8px;
            margin-bottom: 16px;
            padding: 12px 14px;
        }

        .alert.success {
            background: var(--success-soft);
            color: var(--success);
        }

        .alert.error {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .alert ul {
            margin: 8px 0 0;
            padding-left: 18px;
        }

        .notice {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
        }

        .key-box,
        pre {
            background: #101828;
            border-radius: 8px;
            color: #f8fafc;
            overflow-x: auto;
            padding: 12px;
        }

        .key-box {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            margin-bottom: 16px;
        }

        .detail-table th {
            width: 190px;
        }

        .message-body {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            min-height: 180px;
            overflow-x: auto;
            padding: 16px;
            white-space: normal;
        }

        .message-frame {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            min-height: 420px;
            width: 100%;
        }

        @media (max-width: 1080px) {
            .kpi-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .split-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 860px) {
            .topbar-inner,
            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .topbar-inner {
                padding-bottom: 14px;
                padding-top: 14px;
            }

            .nav {
                width: 100%;
            }

            .user-actions {
                justify-content: space-between;
                width: 100%;
            }

            .grid,
            .form-grid,
            .form-grid.three,
            .kpi-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding-left: 16px;
                padding-right: 16px;
            }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('dashboard') }}">
                <span class="brand-mark">P</span>
                <span class="brand-text">
                    PowerMail
                    <small>Core</small>
                </span>
            </a>
            @auth
                <nav class="nav" aria-label="Primary">
                    <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Dashboard</a>
                    <a href="{{ route('clients.index') }}" @class(['active' => request()->routeIs('clients.*')])>Clients</a>
                    <a href="{{ route('domains.index') }}" @class(['active' => request()->routeIs('domains.*')])>Domains</a>
                    <a href="{{ route('email-accounts.index') }}" @class(['active' => request()->routeIs('email-accounts.*')])>Accounts</a>
                    <a href="{{ route('email-templates.index') }}" @class(['active' => request()->routeIs('email-templates.*')])>Templates</a>
                    <a href="{{ route('api-keys.index') }}" @class(['active' => request()->routeIs('api-keys.*')])>API Keys</a>
                    <a href="{{ route('inbox.index') }}" @class(['active' => request()->routeIs('inbox.*')])>Inbox</a>
                    <a href="{{ route('email-logs.index') }}" @class(['active' => request()->routeIs('email-logs.*')])>Logs</a>
                </nav>
                <div class="user-actions">
                    <span class="muted">{{ auth()->user()->email }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="secondary" type="submit">Log out</button>
                    </form>
                </div>
            @endauth
        </div>
    </header>

    @auth
        <main class="container">
            @include('layouts.partials.flash')
            @yield('content')
        </main>
    @else
        <main class="auth-container">
            @include('layouts.partials.flash')
            @yield('content')
        </main>
    @endauth
</body>
</html>
