<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PowerMail Core')</title>
    <style>
        :root {
            --bg: #f5f7fb;
            --panel: #ffffff;
            --ink: #172033;
            --muted: #647084;
            --line: #dce3ed;
            --accent: #136f63;
            --accent-dark: #0e574d;
            --danger: #b42318;
            --warning: #a15c07;
            --success: #067647;
            --shadow: 0 10px 24px rgba(23, 32, 51, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 15px;
            line-height: 1.45;
        }

        a {
            color: var(--accent);
            text-decoration: none;
        }

        a:hover {
            color: var(--accent-dark);
        }

        .topbar {
            background: var(--panel);
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
            max-width: 1180px;
            min-height: 64px;
            padding: 0 20px;
        }

        .brand {
            color: var(--ink);
            font-size: 18px;
            font-weight: 800;
            white-space: nowrap;
        }

        .nav {
            display: flex;
            flex: 1;
            flex-wrap: wrap;
            gap: 4px;
        }

        .nav a {
            border-radius: 6px;
            color: var(--muted);
            font-size: 14px;
            padding: 8px 10px;
        }

        .nav a.active,
        .nav a:hover {
            background: #eaf4f2;
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
            max-width: 1180px;
            padding: 28px 20px 48px;
        }

        .auth-container {
            align-items: center;
            display: flex;
            min-height: calc(100vh - 64px);
            justify-content: center;
            padding: 28px 20px;
        }

        h1 {
            font-size: 28px;
            line-height: 1.2;
            margin: 0 0 18px;
        }

        h2 {
            font-size: 18px;
            margin: 0 0 14px;
        }

        .muted {
            color: var(--muted);
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 22px;
            padding: 20px;
        }

        .panel.compact {
            max-width: 420px;
            width: 100%;
        }

        .grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .metric {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 16px;
        }

        .metric strong {
            display: block;
            font-size: 26px;
            line-height: 1;
            margin-top: 6px;
        }

        .form-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .form-grid.three {
            grid-template-columns: repeat(3, minmax(0, 1fr));
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
            color: #2d3748;
            font-size: 13px;
            font-weight: 700;
        }

        input,
        select,
        textarea {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            color: var(--ink);
            font: inherit;
            min-height: 40px;
            padding: 9px 10px;
            width: 100%;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--accent);
            outline: 2px solid rgba(19, 111, 99, 0.14);
        }

        .checkbox {
            align-items: center;
            flex-direction: row;
            gap: 8px;
            padding-top: 24px;
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
            background: var(--accent);
            border: 0;
            border-radius: 6px;
            color: #fff;
            cursor: pointer;
            display: inline-flex;
            font: inherit;
            font-weight: 700;
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
            background: #e9eef5;
            color: var(--ink);
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border-bottom: 1px solid var(--line);
            padding: 11px 10px;
            text-align: left;
            vertical-align: top;
            white-space: nowrap;
        }

        th {
            color: var(--muted);
            font-size: 12px;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        td.wrap {
            max-width: 360px;
            white-space: normal;
        }

        .badge {
            border-radius: 999px;
            display: inline-flex;
            font-size: 12px;
            font-weight: 800;
            padding: 4px 8px;
            text-transform: capitalize;
        }

        .badge.sent,
        .badge.active {
            background: #dcfae6;
            color: var(--success);
        }

        .badge.failed {
            background: #fee4e2;
            color: var(--danger);
        }

        .badge.pending {
            background: #fef0c7;
            color: var(--warning);
        }

        .badge.opened,
        .badge.clicked {
            background: #e0f2fe;
            color: #026aa2;
        }

        .alert {
            border-radius: 8px;
            margin-bottom: 16px;
            padding: 12px 14px;
        }

        .alert.success {
            background: #dcfae6;
            color: var(--success);
        }

        .alert.error {
            background: #fee4e2;
            color: var(--danger);
        }

        .notice {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 8px;
            color: #9a3412;
            margin-bottom: 16px;
            padding: 12px 14px;
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

        .pagination {
            margin-top: 16px;
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

        @media (max-width: 860px) {
            .topbar-inner {
                align-items: flex-start;
                flex-direction: column;
                padding-bottom: 12px;
                padding-top: 12px;
            }

            .grid,
            .form-grid,
            .form-grid.three {
                grid-template-columns: 1fr;
            }

            .user-actions {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('dashboard') }}">PowerMail Core</a>
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
