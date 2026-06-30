<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PowerMail Core')</title>
    <style>
        :root {
            --primary: #4F6BFF;
            --secondary: #7C4DFF;
            --success: #22C55E;
            --warning: #F59E0B;
            --error: #EF4444;
            --bg: #F8FAFC;
            --card: #FFFFFF;
            --border: #E5E7EB;
            --text: #111827;
            --text-secondary: #6B7280;
            --soft-blue: #EEF2FF;
            --soft-purple: #F3EFFF;
            --soft-green: #ECFDF3;
            --soft-orange: #FFF7ED;
            --soft-red: #FEF2F2;
            --soft-gray: #F3F4F6;
            --shadow: 0 18px 48px rgba(17, 24, 39, 0.07);
            --shadow-soft: 0 8px 22px rgba(17, 24, 39, 0.05);
            --radius: 18px;
            --sidebar-width: 260px;
            --topbar-height: 74px;
        }

        * { box-sizing: border-box; }

        html { min-width: 320px; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }

        a { color: var(--primary); text-decoration: none; transition: color 240ms ease; }
        a:hover { color: var(--secondary); }

        svg { display: block; }

        .app-shell {
            min-height: 100vh;
        }

        .sidebar {
            background: rgba(255, 255, 255, 0.88);
            border-right: 1px solid var(--border);
            bottom: 0;
            display: flex;
            flex-direction: column;
            left: 0;
            padding: 22px 18px;
            position: fixed;
            top: 0;
            width: var(--sidebar-width);
            z-index: 30;
        }

        .brand {
            align-items: center;
            color: var(--text);
            display: inline-flex;
            gap: 12px;
            margin-bottom: 28px;
            padding: 0 6px;
        }

        .brand-mark {
            align-items: center;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 16px;
            box-shadow: 0 14px 26px rgba(79, 107, 255, 0.25);
            color: #fff;
            display: inline-flex;
            font-size: 15px;
            font-weight: 900;
            height: 42px;
            justify-content: center;
            width: 42px;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            font-size: 15px;
            font-weight: 850;
            line-height: 1.1;
        }

        .brand-text small {
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 700;
            margin-top: 3px;
        }

        .sidebar-nav {
            display: grid;
            gap: 6px;
        }

        .sidebar-nav a {
            align-items: center;
            border-radius: 14px;
            color: #4B5563;
            display: flex;
            gap: 12px;
            font-weight: 750;
            min-height: 44px;
            padding: 10px 12px;
            transition: background 240ms ease, color 240ms ease, transform 240ms ease;
        }

        .sidebar-nav a:hover {
            background: var(--soft-blue);
            color: var(--primary);
            transform: translateX(2px);
        }

        .sidebar-nav a.active {
            background: linear-gradient(135deg, rgba(79, 107, 255, 0.12), rgba(124, 77, 255, 0.12));
            color: var(--primary);
        }

        .nav-icon {
            align-items: center;
            border-radius: 12px;
            display: inline-flex;
            flex: 0 0 auto;
            height: 32px;
            justify-content: center;
            width: 32px;
        }

        .sidebar-nav a.active .nav-icon {
            background: #fff;
            box-shadow: var(--shadow-soft);
        }

        .nav-icon svg {
            height: 18px;
            width: 18px;
        }

        .sidebar-footer {
            background: linear-gradient(180deg, #fff, #F9FAFB);
            border: 1px solid var(--border);
            border-radius: 18px;
            margin-top: auto;
            padding: 16px;
        }

        .sidebar-footer strong {
            display: block;
            font-size: 13px;
        }

        .sidebar-footer span {
            color: var(--text-secondary);
            display: block;
            font-size: 12px;
            margin-top: 4px;
        }

        .app-main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .topbar {
            align-items: center;
            background: rgba(248, 250, 252, 0.9);
            border-bottom: 1px solid rgba(229, 231, 235, 0.8);
            display: flex;
            gap: 18px;
            height: var(--topbar-height);
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .search {
            align-items: center;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 999px;
            box-shadow: var(--shadow-soft);
            color: var(--text-secondary);
            display: flex;
            gap: 10px;
            max-width: 440px;
            min-height: 44px;
            padding: 0 15px;
            width: min(42vw, 440px);
        }

        .search input {
            border: 0;
            box-shadow: none;
            min-height: auto;
            padding: 0;
        }

        .search input:focus {
            outline: 0;
        }

        .top-actions {
            align-items: center;
            display: flex;
            gap: 10px;
        }

        .icon-button,
        .language-select,
        .profile-trigger {
            align-items: center;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 999px;
            box-shadow: var(--shadow-soft);
            color: var(--text);
            display: inline-flex;
            font: inherit;
            font-weight: 750;
            gap: 8px;
            min-height: 42px;
            padding: 0 12px;
            transition: transform 220ms ease, box-shadow 220ms ease, border-color 220ms ease;
        }

        .icon-button {
            justify-content: center;
            padding: 0;
            width: 42px;
        }

        .icon-button:hover,
        .language-select:hover,
        .profile-trigger:hover {
            border-color: rgba(79, 107, 255, 0.28);
            box-shadow: 0 12px 28px rgba(79, 107, 255, 0.12);
            transform: translateY(-1px);
        }

        .language-select {
            appearance: none;
            cursor: pointer;
            width: auto;
        }

        .profile-menu {
            position: relative;
        }

        .profile-menu summary {
            list-style: none;
        }

        .profile-menu summary::-webkit-details-marker {
            display: none;
        }

        .avatar {
            align-items: center;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 999px;
            color: #fff;
            display: inline-flex;
            font-size: 12px;
            font-weight: 900;
            height: 30px;
            justify-content: center;
            width: 30px;
        }

        .profile-dropdown {
            animation: slideDown 220ms ease both;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            min-width: 230px;
            padding: 12px;
            position: absolute;
            right: 0;
            top: calc(100% + 10px);
        }

        .profile-dropdown p {
            color: var(--text-secondary);
            font-size: 12px;
            margin: 4px 0 12px;
        }

        .container {
            margin: 0 auto;
            max-width: 1320px;
            padding: 32px;
        }

        .auth-container {
            align-items: center;
            background:
                radial-gradient(circle at 18% 14%, rgba(79, 107, 255, 0.13), transparent 30%),
                radial-gradient(circle at 80% 16%, rgba(124, 77, 255, 0.13), transparent 30%),
                var(--bg);
            display: flex;
            min-height: 100vh;
            justify-content: center;
            padding: 28px 20px;
        }

        .page-header {
            align-items: flex-end;
            display: flex;
            gap: 18px;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .page-title { min-width: 0; }

        h1 {
            color: var(--text);
            font-size: clamp(28px, 3vw, 40px);
            letter-spacing: -0.02em;
            line-height: 1.06;
            margin: 0;
        }

        h2 {
            color: var(--text);
            font-size: 17px;
            letter-spacing: -0.01em;
            line-height: 1.2;
            margin: 0;
        }

        .eyebrow {
            color: var(--primary);
            font-size: 12px;
            font-weight: 850;
            letter-spacing: 0.04em;
            margin: 0 0 8px;
            text-transform: uppercase;
        }

        .lede,
        .muted {
            color: var(--text-secondary);
        }

        .lede {
            margin: 10px 0 0;
            max-width: 660px;
        }

        .panel,
        .metric,
        .auth-card {
            animation: fadeIn 260ms ease both;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-soft);
        }

        .panel {
            margin-bottom: 24px;
            padding: 24px;
        }

        .panel.compact,
        .auth-card {
            max-width: 440px;
            width: 100%;
        }

        .panel-header {
            align-items: flex-start;
            display: flex;
            gap: 14px;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .panel-header p {
            color: var(--text-secondary);
            margin: 6px 0 0;
        }

        .grid,
        .kpi-grid {
            display: grid;
            gap: 18px;
        }

        .grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .kpi-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-bottom: 24px;
        }

        .split-grid {
            display: grid;
            gap: 24px;
            grid-template-columns: minmax(0, 1.35fr) minmax(340px, 0.65fr);
        }

        .form-grid {
            display: grid;
            gap: 20px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .form-grid.three {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .metric {
            min-height: 158px;
            padding: 24px;
            transition: transform 240ms ease, box-shadow 240ms ease, border-color 240ms ease;
        }

        .metric:hover {
            border-color: rgba(79, 107, 255, 0.28);
            box-shadow: 0 18px 42px rgba(79, 107, 255, 0.11);
            transform: translateY(-3px) scale(1.01);
        }

        .metric-top {
            align-items: center;
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .metric-label {
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 850;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .metric-icon {
            align-items: center;
            border-radius: 999px;
            display: inline-flex;
            height: 44px;
            justify-content: center;
            width: 44px;
        }

        .metric-icon svg {
            height: 21px;
            width: 21px;
        }

        .metric[data-tone="blue"] .metric-icon { background: var(--soft-blue); color: var(--primary); }
        .metric[data-tone="purple"] .metric-icon { background: var(--soft-purple); color: var(--secondary); }
        .metric[data-tone="green"] .metric-icon { background: var(--soft-green); color: var(--success); }
        .metric[data-tone="amber"] .metric-icon { background: var(--soft-orange); color: var(--warning); }
        .metric[data-tone="red"] .metric-icon { background: var(--soft-red); color: var(--error); }

        .metric-value {
            display: block;
            font-size: 34px;
            font-weight: 900;
            letter-spacing: -0.03em;
            line-height: 1;
            margin-top: 18px;
        }

        .metric-footer {
            align-items: center;
            display: flex;
            gap: 8px;
            margin-top: 14px;
        }

        .trend {
            align-items: center;
            border-radius: 999px;
            display: inline-flex;
            font-size: 12px;
            font-weight: 850;
            gap: 3px;
            min-height: 24px;
            padding: 4px 8px;
        }

        .trend.up {
            background: var(--soft-green);
            color: var(--success);
        }

        .trend.down {
            background: var(--soft-red);
            color: var(--error);
        }

        .metric-hint {
            color: var(--text-secondary);
            font-size: 12px;
        }

        .summary-list {
            display: grid;
            gap: 12px;
            margin: 0;
            padding: 0;
        }

        .summary-item {
            align-items: center;
            background: #FAFBFC;
            border: 1px solid var(--border);
            border-radius: 16px;
            display: flex;
            justify-content: space-between;
            padding: 14px;
        }

        .summary-item strong { color: var(--text); }

        .subform {
            border-top: 1px solid var(--border);
            margin-top: 18px;
            padding-top: 18px;
        }

        .subform:first-of-type {
            border-top: 0;
            margin-top: 0;
            padding-top: 0;
        }

        .bar {
            background: #EEF2F7;
            border-radius: 999px;
            height: 9px;
            overflow: hidden;
            width: 100%;
        }

        .bar span {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: inherit;
            display: block;
            height: 100%;
            min-width: 3px;
        }

        .quick-actions {
            display: grid;
            gap: 12px;
        }

        .quick-link {
            align-items: center;
            background: #FAFBFC;
            border: 1px solid var(--border);
            border-radius: 16px;
            color: var(--text);
            display: flex;
            font-weight: 800;
            justify-content: space-between;
            min-height: 54px;
            padding: 14px 16px;
            transition: background 240ms ease, border-color 240ms ease, transform 240ms ease;
        }

        .quick-link:hover {
            background: var(--soft-blue);
            border-color: rgba(79, 107, 255, 0.22);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .chart-panel {
            min-height: 360px;
        }

        .chart-wrap {
            height: 250px;
            position: relative;
        }

        .chart-svg {
            height: 100%;
            overflow: visible;
            width: 100%;
        }

        .chart-grid {
            stroke: #E5E7EB;
            stroke-dasharray: 4 8;
        }

        .chart-area {
            opacity: 0.75;
        }

        .chart-line {
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 4;
        }

        .chart-dot {
            cursor: pointer;
            transition: r 200ms ease, transform 200ms ease;
        }

        .chart-dot:hover {
            r: 7;
        }

        .chart-legend {
            align-items: center;
            color: var(--text-secondary);
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            font-size: 12px;
            margin-top: 12px;
        }

        .legend-item {
            align-items: center;
            display: inline-flex;
            gap: 7px;
        }

        .legend-dot {
            border-radius: 999px;
            height: 9px;
            width: 9px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .field.full { grid-column: 1 / -1; }

        label {
            color: #374151;
            font-size: 12px;
            font-weight: 850;
        }

        input,
        select,
        textarea {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text);
            font: inherit;
            min-height: 46px;
            padding: 11px 13px;
            transition: border-color 220ms ease, box-shadow 220ms ease;
            width: 100%;
        }

        textarea {
            min-height: 132px;
            resize: vertical;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: rgba(79, 107, 255, 0.8);
            box-shadow: 0 0 0 4px rgba(79, 107, 255, 0.12);
            outline: 0;
        }

        .checkbox {
            align-items: center;
            flex-direction: row;
            gap: 10px;
            min-height: 46px;
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
            margin-top: 20px;
        }

        button,
        .button {
            align-items: center;
            background: linear-gradient(135deg, var(--primary), #6A5CFF);
            border: 0;
            border-radius: 999px;
            box-shadow: 0 12px 24px rgba(79, 107, 255, 0.22);
            color: #fff;
            cursor: pointer;
            display: inline-flex;
            font: inherit;
            font-weight: 850;
            justify-content: center;
            min-height: 44px;
            padding: 10px 18px;
            transition: transform 220ms ease, box-shadow 220ms ease, opacity 220ms ease;
        }

        button:hover,
        .button:hover {
            box-shadow: 0 16px 32px rgba(79, 107, 255, 0.28);
            color: #fff;
            transform: translateY(-1px);
        }

        .button.secondary,
        button.secondary {
            background: #fff;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-soft);
            color: var(--text);
        }

        .button.secondary:hover,
        button.secondary:hover {
            border-color: rgba(79, 107, 255, 0.28);
            color: var(--primary);
        }

        .button.ghost {
            background: transparent;
            border: 1px solid var(--border);
            box-shadow: none;
            color: var(--text);
        }

        .table-wrap {
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow-x: auto;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border-bottom: 1px solid var(--border);
            padding: 14px 16px;
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        tbody tr:last-child td { border-bottom: 0; }
        tbody tr { transition: background 220ms ease; }
        tbody tr:hover { background: #F9FAFB; }

        th {
            background: #FAFBFC;
            color: var(--text-secondary);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        td.wrap {
            max-width: 430px;
            white-space: normal;
        }

        .badge {
            align-items: center;
            border-radius: 999px;
            display: inline-flex;
            font-size: 12px;
            font-weight: 850;
            line-height: 1;
            min-height: 26px;
            padding: 7px 10px;
            text-transform: capitalize;
        }

        .badge.sent,
        .badge.active {
            background: var(--soft-green);
            color: var(--success);
        }

        .badge.failed {
            background: var(--soft-red);
            color: var(--error);
        }

        .badge.pending {
            background: var(--soft-orange);
            color: var(--warning);
        }

        .badge.opened,
        .badge.clicked,
        .badge.info {
            background: var(--soft-blue);
            color: var(--primary);
        }

        .badge.draft {
            background: var(--soft-gray);
            color: var(--text-secondary);
        }

        .alert,
        .notice {
            border-radius: 16px;
            margin-bottom: 18px;
            padding: 14px 16px;
        }

        .alert.success {
            background: var(--soft-green);
            color: #15803D;
        }

        .alert.error {
            background: var(--soft-red);
            color: var(--error);
        }

        .alert ul {
            margin: 8px 0 0;
            padding-left: 18px;
        }

        .notice {
            background: var(--soft-orange);
            border: 1px solid #FED7AA;
            color: #9A3412;
        }

        .key-box,
        pre {
            background: #111827;
            border-radius: 16px;
            color: #F8FAFC;
            overflow-x: auto;
            padding: 16px;
        }

        .key-box {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            margin-bottom: 16px;
        }

        .detail-table th { width: 190px; }

        .message-body {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 18px;
            min-height: 180px;
            overflow-x: auto;
            padding: 18px;
            white-space: normal;
        }

        .message-frame {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 18px;
            min-height: 420px;
            width: 100%;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 1180px) {
            .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .split-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 960px) {
            .sidebar {
                border-bottom: 1px solid var(--border);
                border-right: 0;
                height: auto;
                padding: 16px;
                position: sticky;
                width: 100%;
            }

            .sidebar-nav {
                display: flex;
                overflow-x: auto;
                padding-bottom: 2px;
            }

            .sidebar-footer {
                display: none;
            }

            .app-main {
                margin-left: 0;
            }

            .topbar {
                padding: 0 16px;
            }

            .search {
                width: min(100%, 360px);
            }
        }

        @media (max-width: 760px) {
            .topbar,
            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .topbar {
                height: auto;
                padding-bottom: 16px;
                padding-top: 16px;
            }

            .search,
            .top-actions {
                width: 100%;
            }

            .top-actions {
                flex-wrap: wrap;
            }

            .container {
                padding: 22px 16px;
            }

            .grid,
            .form-grid,
            .form-grid.three,
            .kpi-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    @auth
        <div class="app-shell">
            <aside class="sidebar">
                <a class="brand" href="{{ route('dashboard') }}">
                    <span class="brand-mark">P</span>
                    <span class="brand-text">
                        PowerMail
                        <small>Core</small>
                    </span>
                </a>

                <nav class="sidebar-nav" aria-label="Primary">
                    <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 13h8V3H3v10Z"/><path d="M13 21h8V11h-8v10Z"/><path d="M13 3v6h8V3h-8Z"/><path d="M3 21h8v-6H3v6Z"/></svg></span>
                        <span>Dashboard</span>
                    </a>
                    <a href="{{ route('clients.index') }}" @class(['active' => request()->routeIs('clients.*')])>
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                        <span>Clients</span>
                    </a>
                    <a href="{{ route('domains.index') }}" @class(['active' => request()->routeIs('domains.*')])>
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20"/><path d="M12 2a15.3 15.3 0 0 0 0 20"/></svg></span>
                        <span>Domains</span>
                    </a>
                    <a href="{{ route('email-accounts.index') }}" @class(['active' => request()->routeIs('email-accounts.*')])>
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="m22 6-10 7L2 6"/></svg></span>
                        <span>Accounts</span>
                    </a>
                    <a href="{{ route('email-templates.index') }}" @class(['active' => request()->routeIs('email-templates.*')])>
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg></span>
                        <span>Templates</span>
                    </a>
                    <a href="{{ route('api-keys.index') }}" @class(['active' => request()->routeIs('api-keys.*')])>
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15 2 6 6"/><path d="m18 5 3 3"/></svg></span>
                        <span>API Keys</span>
                    </a>
                    <a href="{{ route('inbox.index') }}" @class(['active' => request()->routeIs('inbox.*')])>
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="m5.45 5.11-3.43 6.86A2 2 0 0 0 2 13v5a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5a2 2 0 0 0-.02-1.03l-3.43-6.86A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg></span>
                        <span>Inbox</span>
                    </a>
                    <a href="{{ route('email-logs.index') }}" @class(['active' => request()->routeIs('email-logs.*')])>
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg></span>
                        <span>Logs</span>
                    </a>
                </nav>

                <div class="sidebar-footer">
                    <strong>{{ auth()->user()->name }}</strong>
                    <span>{{ auth()->user()->email }}</span>
                </div>
            </aside>

            <div class="app-main">
                <header class="topbar">
                    <label class="search" aria-label="Search">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="search" placeholder="Search clients, logs, accounts...">
                    </label>

                    <div class="top-actions">
                        <button class="icon-button" type="button" aria-label="Notifications">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        </button>
                        <select class="language-select" aria-label="Language">
                            <option>EN</option>
                            <option>ZA</option>
                        </select>
                        <details class="profile-menu">
                            <summary class="profile-trigger">
                                <span class="avatar">{{ strtoupper((string) str(auth()->user()->name)->substr(0, 1)) }}</span>
                                <span>{{ str(auth()->user()->name)->limit(18) }}</span>
                            </summary>
                            <div class="profile-dropdown">
                                <strong>{{ auth()->user()->name }}</strong>
                                <p>{{ auth()->user()->email }}</p>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="secondary" type="submit">Log out</button>
                                </form>
                            </div>
                        </details>
                    </div>
                </header>

                <main class="container">
                    @include('layouts.partials.flash')
                    @yield('content')
                </main>
            </div>
        </div>
    @else
        <main class="auth-container">
            @include('layouts.partials.flash')
            @yield('content')
        </main>
    @endauth
</body>
</html>
