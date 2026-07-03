<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PowerMail Core')</title>
    @auth
        @php
            $activeSidebarSection = match (true) {
                request()->routeIs('dashboard') => 'overview',
                request()->routeIs('clients.*', 'users.*') => 'companies',
                request()->routeIs('send-email.*', 'inbox.*', 'email-logs.*') => 'mail',
                request()->routeIs('marketing.*') => 'marketing',
                request()->routeIs('api-keys.*') => 'developer',
                request()->routeIs('domains.*', 'email-accounts.*', 'email-templates.*') => 'settings',
                default => null,
            };
        @endphp
        <script>
            (() => {
                const root = document.documentElement;
                const activeSection = @json($activeSidebarSection);
                const defaultCollapsedSections = new Set(['overview', 'companies', 'mail']);
                const allSections = ['overview', 'companies', 'mail', 'marketing', 'developer', 'settings'];

                if (localStorage.getItem('powermail.sidebar.collapsed') === 'true') {
                    root.classList.add('sidebar-collapsed');
                }

                let sectionState = {};

                try {
                    sectionState = JSON.parse(localStorage.getItem('powermail.sidebar.sections') || '{}') || {};
                } catch (error) {
                    sectionState = {};
                }

                allSections.forEach((section) => {
                    const hasSavedState = Object.prototype.hasOwnProperty.call(sectionState, section);
                    const collapsed = section !== activeSection
                        && (hasSavedState ? sectionState[section] === true : defaultCollapsedSections.has(section));

                    if (collapsed) {
                        root.classList.add(`nav-section-collapsed-${section}`);
                    }
                });
            })();
        </script>
    @endauth
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
            --sidebar-collapsed-width: 86px;
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
            transition: padding 220ms ease, width 220ms ease;
            width: var(--sidebar-width);
            z-index: 30;
        }

        .sidebar-head {
            align-items: center;
            display: flex;
            gap: 10px;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .sidebar-scroll {
            display: flex;
            flex: 1;
            flex-direction: column;
            min-height: 0;
            overflow-y: auto;
            padding-right: 2px;
        }

        .sidebar-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: #D1D5DB;
            border-radius: 999px;
        }

        .brand {
            align-items: center;
            color: var(--text);
            display: inline-flex;
            gap: 12px;
            margin-bottom: 0;
            min-width: 0;
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
            min-width: 0;
        }

        .brand-text small {
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 700;
            margin-top: 3px;
        }

        .sidebar-nav {
            display: flex;
            flex: 1 0 auto;
            flex-direction: column;
            gap: 18px;
            padding-bottom: 18px;
        }

        .nav-section {
            display: grid;
            gap: 6px;
        }

        .settings-nav-section {
            margin-top: auto;
        }

        .nav-section-title {
            align-items: center;
            background: transparent;
            border: 0;
            border-radius: 10px;
            box-shadow: none;
            color: #9CA3AF;
            cursor: pointer;
            display: flex;
            font-size: 10px;
            font-weight: 900;
            justify-content: space-between;
            letter-spacing: 0.09em;
            min-height: 26px;
            padding: 0 12px 2px;
            text-align: left;
            text-transform: uppercase;
            width: 100%;
        }

        .nav-section-title:hover {
            background: #F8FAFC;
            box-shadow: none;
            color: var(--primary);
            transform: none;
        }

        .nav-section-chevron {
            height: 13px;
            opacity: 0.72;
            transition: transform 180ms ease;
            width: 13px;
        }

        .nav-section.collapsed .nav-section-chevron {
            transform: rotate(-90deg);
        }

        .nav-section-items {
            display: grid;
            gap: 4px;
            max-height: 520px;
            overflow: hidden;
            transition: max-height 200ms ease, opacity 180ms ease;
        }

        .nav-section.collapsed .nav-section-items {
            max-height: 0;
            opacity: 0;
        }

        html.nav-section-collapsed-overview [data-nav-section="overview"] .nav-section-chevron,
        html.nav-section-collapsed-companies [data-nav-section="companies"] .nav-section-chevron,
        html.nav-section-collapsed-mail [data-nav-section="mail"] .nav-section-chevron,
        html.nav-section-collapsed-marketing [data-nav-section="marketing"] .nav-section-chevron,
        html.nav-section-collapsed-developer [data-nav-section="developer"] .nav-section-chevron,
        html.nav-section-collapsed-settings [data-nav-section="settings"] .nav-section-chevron {
            transform: rotate(-90deg);
        }

        html.nav-section-collapsed-overview [data-nav-section="overview"] .nav-section-items,
        html.nav-section-collapsed-companies [data-nav-section="companies"] .nav-section-items,
        html.nav-section-collapsed-mail [data-nav-section="mail"] .nav-section-items,
        html.nav-section-collapsed-marketing [data-nav-section="marketing"] .nav-section-items,
        html.nav-section-collapsed-developer [data-nav-section="developer"] .nav-section-items,
        html.nav-section-collapsed-settings [data-nav-section="settings"] .nav-section-items {
            max-height: 0;
            opacity: 0;
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

        .sidebar-footer .role-badge {
            align-items: center;
            background: var(--soft-blue);
            border-radius: 999px;
            color: var(--primary);
            display: inline-flex;
            font-size: 11px;
            font-weight: 850;
            margin-top: 10px;
            min-height: 24px;
            padding: 4px 9px;
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

        .sidebar-toggle {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--shadow-soft);
            color: var(--text-secondary);
            cursor: pointer;
            display: inline-flex;
            flex: 0 0 auto;
            height: 36px;
            justify-content: center;
            padding: 0;
            width: 36px;
        }

        .sidebar-toggle:hover {
            color: var(--primary);
            transform: none;
        }

        .sidebar-toggle svg {
            height: 18px;
            transition: transform 220ms ease;
            width: 18px;
        }

        .app-main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 220ms ease;
        }

        @media (min-width: 961px) {
            :is(html.sidebar-collapsed, body.sidebar-collapsed) .sidebar {
                padding-left: 14px;
                padding-right: 14px;
                width: var(--sidebar-collapsed-width);
            }

            :is(html.sidebar-collapsed, body.sidebar-collapsed) .app-main {
                margin-left: var(--sidebar-collapsed-width);
            }

            :is(html.sidebar-collapsed, body.sidebar-collapsed) .sidebar-head {
                flex-direction: column;
                gap: 10px;
                justify-content: center;
                margin-bottom: 20px;
            }

            :is(html.sidebar-collapsed, body.sidebar-collapsed) .brand {
                padding: 0;
            }

            :is(html.sidebar-collapsed, body.sidebar-collapsed) .brand-text,
            :is(html.sidebar-collapsed, body.sidebar-collapsed) .nav-section-title,
            :is(html.sidebar-collapsed, body.sidebar-collapsed) .sidebar-nav a > span:not(.nav-icon),
            :is(html.sidebar-collapsed, body.sidebar-collapsed) .sidebar-footer strong,
            :is(html.sidebar-collapsed, body.sidebar-collapsed) .sidebar-footer > span:not(.role-badge),
            :is(html.sidebar-collapsed, body.sidebar-collapsed) .sidebar-footer .role-badge {
                display: none;
            }

            :is(html.sidebar-collapsed, body.sidebar-collapsed) .sidebar-toggle svg {
                transform: rotate(180deg);
            }

            :is(html.sidebar-collapsed, body.sidebar-collapsed) .sidebar-scroll {
                padding-right: 0;
            }

            :is(html.sidebar-collapsed, body.sidebar-collapsed) .sidebar-nav {
                align-items: center;
                gap: 14px;
            }

            :is(html.sidebar-collapsed, body.sidebar-collapsed) .nav-section,
            :is(html.sidebar-collapsed, body.sidebar-collapsed) .nav-section-items {
                width: 100%;
            }

            :is(html.sidebar-collapsed, body.sidebar-collapsed) .sidebar-nav a {
                gap: 0;
                justify-content: center;
                padding-left: 0;
                padding-right: 0;
            }

            :is(html.sidebar-collapsed, body.sidebar-collapsed) .sidebar-nav a:hover {
                transform: none;
            }

            :is(html.sidebar-collapsed, body.sidebar-collapsed) .nav-icon {
                height: 34px;
                width: 34px;
            }

            :is(html.sidebar-collapsed, body.sidebar-collapsed) .sidebar-footer {
                display: none;
            }
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
            position: relative;
            width: 42px;
        }

        .notification-badge {
            align-items: center;
            background: var(--error);
            border: 2px solid #fff;
            border-radius: 999px;
            color: #fff;
            display: inline-flex;
            font-size: 10px;
            font-weight: 900;
            height: 19px;
            justify-content: center;
            line-height: 1;
            min-width: 19px;
            padding: 0 5px;
            position: absolute;
            right: -5px;
            top: -5px;
        }

        .notification-badge[hidden] {
            display: none;
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
            margin: 0;
            max-width: none;
            padding: 24px 5% 32px;
            width: 100%;
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

        .mail-page-header {
            align-items: center;
            margin-bottom: 14px;
        }

        .mail-page-header h1 {
            font-size: 28px;
        }

        .mail-page-header .lede {
            font-size: 13px;
            margin-top: 4px;
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

        .table-filter-bar {
            align-items: end;
            border-top: 1px solid var(--border);
            display: grid;
            gap: 12px;
            grid-template-columns: minmax(260px, 1.4fr) repeat(3, minmax(150px, 0.75fr)) auto;
            margin: -4px 0 16px;
            padding-top: 16px;
        }

        .table-filter-bar .field {
            gap: 6px;
            min-width: 0;
        }

        .table-filter-bar input,
        .table-filter-bar select {
            min-height: 40px;
            padding: 9px 11px;
        }

        .table-filter-actions {
            align-items: center;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            min-height: 40px;
        }

        .table-filter-actions button,
        .table-filter-actions .button {
            min-height: 40px;
            padding: 8px 14px;
            white-space: nowrap;
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

        .campaign-progress-panel {
            align-items: center;
            display: grid;
            gap: 18px;
            grid-template-columns: auto minmax(0, 1fr) auto;
        }

        .campaign-progress-spinner {
            align-items: center;
            background: var(--soft-blue);
            border-radius: 999px;
            color: var(--primary);
            display: inline-flex;
            height: 58px;
            justify-content: center;
            position: relative;
            width: 58px;
        }

        .campaign-progress-spinner::before {
            animation: button-spin 900ms linear infinite;
            border: 3px solid rgba(79, 107, 255, 0.18);
            border-radius: inherit;
            border-top-color: var(--primary);
            content: "";
            inset: 6px;
            position: absolute;
        }

        .campaign-progress-spinner.complete::before {
            animation: none;
            border-color: var(--success);
        }

        .campaign-progress-spinner.failed::before {
            animation: none;
            border-color: var(--error);
        }

        .campaign-progress-spinner strong {
            font-size: 12px;
            font-weight: 900;
            position: relative;
            z-index: 1;
        }

        .campaign-progress-copy {
            display: grid;
            gap: 8px;
            min-width: 0;
        }

        .campaign-progress-copy h2 {
            margin: 0;
        }

        .campaign-progress-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .campaign-progress-stats span {
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-radius: 999px;
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 850;
            padding: 6px 10px;
        }

        .campaign-progress-toast {
            background: #111827;
            border-radius: 14px;
            bottom: 22px;
            box-shadow: 0 18px 38px rgba(17, 24, 39, 0.22);
            color: #fff;
            display: none;
            font-weight: 850;
            max-width: min(420px, calc(100vw - 32px));
            padding: 12px 14px;
            position: fixed;
            right: 22px;
            z-index: 80;
        }

        .campaign-progress-toast.active {
            display: block;
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

        .mail-layout {
            display: grid;
            gap: 16px;
            grid-template-columns: 238px minmax(0, 1fr);
            min-width: 0;
        }

        .mail-app {
            align-items: stretch;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow-soft);
            min-height: 680px;
            overflow: hidden;
        }

        .mail-rail {
            align-self: stretch;
            background: #F8FAFC;
            border-right: 1px solid var(--border);
            display: grid;
            gap: 14px;
            grid-auto-rows: max-content;
            height: 100%;
            max-height: max(420px, calc(100dvh - var(--topbar-height) - 110px));
            min-height: 680px;
            min-width: 0;
            overflow-x: hidden;
            overflow-y: auto;
            padding: 16px 12px;
            width: 100%;
        }

        .mail-rail::-webkit-scrollbar {
            width: 6px;
        }

        .mail-rail::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 999px;
        }

        .mail-compose-wrap {
            padding: 0 4px 2px;
        }

        .mail-compose-button {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 10px 24px rgba(17, 24, 39, 0.08);
            color: var(--text);
            justify-content: flex-start;
            min-height: 46px;
            padding: 10px 18px;
            width: 100%;
        }

        .mail-compose-button:hover {
            border-color: rgba(79, 107, 255, 0.24);
            box-shadow: 0 14px 30px rgba(79, 107, 255, 0.14);
            color: var(--primary);
        }

        .mail-section {
            border-top: 0;
            min-width: 0;
            padding-top: 0;
        }

        .mail-rail .panel-header {
            margin-bottom: 6px;
            padding: 0 8px;
        }

        .mail-rail .panel-header h2 {
            color: var(--text-secondary);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .mail-rail .panel-header p {
            display: none;
        }

        .mailbox-list {
            display: grid;
            gap: 2px;
            min-width: 0;
        }

        .mailbox-link {
            align-items: center;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 999px;
            color: var(--text);
            display: flex;
            font-size: 13px;
            font-weight: 800;
            gap: 10px;
            justify-content: space-between;
            max-width: 100%;
            min-height: 36px;
            min-width: 0;
            overflow: hidden;
            padding: 7px 10px;
            width: 100%;
        }

        .mailbox-link > span:first-child {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .mailbox-link:hover,
        .mailbox-link.active {
            background: #EEF2FF;
            border-color: transparent;
            color: var(--primary);
        }

        .mailbox-link.active {
            font-weight: 900;
        }

        .mailbox-count {
            color: var(--text-secondary);
            flex: 0 0 auto;
            font-size: 12px;
            font-weight: 850;
            margin-left: auto;
        }

        .mailbox-link button {
            flex: 0 0 auto;
        }

        .mailbox-subtext {
            color: var(--text-secondary);
            display: block;
            font-size: 11px;
            font-weight: 750;
            line-height: 1.25;
            margin-top: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .mail-rail .field {
            gap: 5px;
            min-width: 0;
        }

        .mail-rail label {
            font-size: 11px;
        }

        .mail-rail input,
        .mail-rail select {
            max-width: 100%;
            min-height: 38px;
            min-width: 0;
            padding: 8px 10px;
        }

        .mail-rail .inline-actions {
            flex-wrap: wrap;
            gap: 6px;
            min-width: 0;
            white-space: normal;
        }

        .mail-rail .stack {
            min-width: 0;
        }

        .mail-pane {
            border: 0;
            border-radius: 0;
            box-shadow: none;
            margin-bottom: 0;
            min-width: 0;
            padding: 0;
        }

        .mail-pane > .actions {
            border-top: 1px solid var(--border);
            margin-top: 0;
            padding: 14px 18px 18px;
        }

        .mail-pane > .actions .button,
        .mail-pane > .actions button {
            background: transparent;
            border: 1px solid var(--border);
            box-shadow: none;
            color: var(--text-secondary);
            min-height: 34px;
            padding: 7px 13px;
        }

        .mail-pane > .actions .button:hover,
        .mail-pane > .actions button:hover {
            background: #F1F5F9;
            border-color: #CBD5E1;
            box-shadow: none;
            color: var(--text);
            transform: none;
        }

        .mail-toolbar {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: space-between;
            min-height: 68px;
            padding: 14px 18px;
        }

        .mail-toolbar .button,
        .mail-toolbar button {
            background: transparent;
            border: 1px solid var(--border);
            box-shadow: none;
            color: var(--text-secondary);
            min-height: 32px;
            padding: 6px 12px;
        }

        .mail-toolbar .button:hover,
        .mail-toolbar button:hover {
            background: #F1F5F9;
            border-color: #CBD5E1;
            box-shadow: none;
            color: var(--text);
            transform: none;
        }

        .mail-toolbar h2 {
            font-size: 18px;
            margin: 0;
        }

        .mail-meta {
            align-items: center;
            color: var(--text-secondary);
            display: flex;
            flex-wrap: wrap;
            font-size: 12px;
            font-weight: 800;
            gap: 7px;
            margin-top: 3px;
        }

        .mail-meta-dot,
        .unread-dot {
            background: var(--primary);
            border-radius: 999px;
            display: inline-block;
        }

        .mail-meta-dot {
            height: 6px;
            width: 6px;
        }

        .sync-status {
            align-items: center;
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 999px;
            color: var(--text-secondary);
            display: inline-flex;
            font-size: 12px;
            font-weight: 850;
            min-height: 30px;
            padding: 5px 10px;
        }

        .compose-mail-card {
            border-radius: 18px;
            margin: 0 auto;
            max-width: 1280px;
            overflow: hidden;
            padding: 0;
        }

        .compose-mail-header {
            align-items: center;
            background: #F8FAFC;
            border-bottom: 1px solid var(--border);
            color: var(--text);
            display: flex;
            font-size: 13px;
            font-weight: 900;
            min-height: 44px;
            padding: 11px 16px;
        }

        .compose-mail-body {
            display: grid;
            gap: 0;
            padding: 0 18px 16px;
        }

        .send-compose-layout {
            display: grid;
            gap: 18px;
            grid-template-columns: minmax(360px, 0.82fr) minmax(520px, 1.18fr);
            min-width: 0;
            padding-top: 16px;
        }

        .send-compose-editor {
            min-width: 0;
        }

        .send-compose-preview {
            margin: 0;
            min-height: 520px;
        }

        .compose-mail-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            padding: 0 0 6px;
        }

        .compose-mail-card .field {
            gap: 6px;
        }

        .compose-mail-card label {
            color: var(--text-secondary);
            font-size: 11px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .compose-line {
            align-items: center;
            border-top: 1px solid var(--border);
            display: grid;
            gap: 12px;
            grid-template-columns: 72px minmax(0, 1fr);
            min-height: 48px;
        }

        .compose-line input {
            border: 0;
            border-radius: 0;
            box-shadow: none;
            min-height: 46px;
            padding-left: 0;
            padding-right: 0;
        }

        .compose-line input:focus {
            border-color: transparent;
            box-shadow: none;
            outline: 0;
        }

        .compose-mail-card input:focus,
        .compose-mail-card select:focus,
        .compose-mail-card textarea:focus {
            border-color: transparent;
            box-shadow: none;
            outline: 0;
        }

        .compose-data-field {
            border-top: 1px solid var(--border);
            display: grid;
            gap: 10px;
            padding-top: 14px;
        }

        .compose-data-label {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: space-between;
        }

        .compose-data-label span {
            color: var(--text-secondary);
            font-size: 12px;
        }

        .compose-data-field textarea {
            background: #F8FAFC;
            min-height: 160px;
        }

        .compose-default {
            align-items: center;
            color: var(--text-secondary);
            flex-direction: row;
            font-size: 13px;
            font-weight: 800;
            justify-content: flex-start;
            padding-top: 4px;
            text-transform: none;
        }

        .compose-mail-footer {
            align-items: center;
            background: #fff;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 10px;
            justify-content: flex-start;
            padding: 14px 18px;
        }

        .compose-mail-footer .button,
        .compose-mail-footer button {
            min-height: 36px;
            padding: 8px 15px;
        }

        .delivery-notice {
            margin-left: auto;
            margin-right: auto;
            max-width: 920px;
        }

        .delivery-hint {
            border-top: 1px solid rgba(154, 52, 18, 0.18);
            margin-top: 10px;
            padding-top: 10px;
        }

        .delivery-check-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            margin-top: 14px;
        }

        .delivery-check-grid div {
            background: rgba(255, 255, 255, 0.66);
            border: 1px solid rgba(5, 95, 70, 0.16);
            border-radius: 8px;
            padding: 10px 12px;
        }

        .delivery-check-grid span {
            color: var(--text-secondary);
            display: block;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.08em;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .delivery-check-grid strong {
            color: var(--text-primary);
            display: block;
            font-size: 13px;
            line-height: 1.4;
        }

        .delivery-steps {
            color: var(--text-secondary);
            display: grid;
            gap: 8px;
            margin: 16px 0 0;
            padding-left: 20px;
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

        button.is-loading,
        .button.is-loading {
            color: transparent !important;
            cursor: wait;
            opacity: 0.82;
            pointer-events: none;
            position: relative;
        }

        button.is-loading *,
        .button.is-loading * {
            opacity: 0 !important;
        }

        button.is-loading::after,
        .button.is-loading::after {
            animation: button-spin 760ms linear infinite;
            border: 2px solid rgba(255, 255, 255, 0.42);
            border-radius: 999px;
            border-top-color: #fff;
            content: "";
            height: 16px;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            position: absolute;
            top: 50%;
            width: 16px;
        }

        button.secondary.is-loading::after,
        .button.secondary.is-loading::after {
            border-color: rgba(79, 107, 255, 0.22);
            border-top-color: var(--primary);
        }

        button.danger.is-loading::after,
        .button.danger.is-loading::after {
            border-color: rgba(239, 68, 68, 0.22);
            border-top-color: var(--error);
        }

        @keyframes button-spin {
            to { transform: rotate(360deg); }
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

        .button.danger,
        button.danger {
            background: var(--soft-red);
            border: 1px solid #FECACA;
            box-shadow: none;
            color: var(--error);
        }

        .button.danger:hover,
        button.danger:hover {
            box-shadow: 0 12px 26px rgba(239, 68, 68, 0.14);
            color: var(--error);
        }

        .button.tiny,
        button.tiny {
            min-height: 30px;
            padding: 5px 10px;
        }

        .button.ghost {
            background: transparent;
            border: 1px solid var(--border);
            box-shadow: none;
            color: var(--text);
        }

        .inline-actions {
            align-items: center;
            display: flex;
            flex-wrap: nowrap;
            gap: 8px;
            white-space: nowrap;
        }

        .inline-actions form {
            margin: 0;
        }

        .stack {
            display: grid;
            gap: 14px;
        }

        .permissions-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            min-width: 0;
        }

        .confirm-dialog,
        .edit-dialog {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
            max-height: calc(100dvh - 32px);
            overflow: hidden;
            max-width: none;
            padding: 0;
            width: min(420px, calc(100vw - 32px));
        }

        .confirm-dialog[open],
        .edit-dialog[open] {
            animation: modal-in 220ms ease both;
        }

        .edit-dialog {
            width: min(820px, calc(100vw - 32px));
        }

        .template-dialog {
            width: min(1280px, calc(100vw - 32px));
        }

        .template-dialog .edit-dialog-body {
            padding: 28px;
        }

        .template-builder {
            display: grid;
            gap: 18px;
            grid-template-columns: minmax(0, 1.05fr) minmax(420px, 0.95fr);
            margin-top: 18px;
            min-height: 0;
        }

        .template-builder-fields {
            min-width: 0;
        }

        .template-dialog .template-builder .form-grid {
            margin-top: 0 !important;
        }

        .template-html-editor {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            line-height: 1.55;
            min-height: 430px;
        }

        .template-text-editor {
            min-height: 150px;
        }

        .template-preview-panel {
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            min-height: 640px;
            min-width: 0;
            overflow: hidden;
        }

        .template-preview-head {
            align-items: center;
            background: #fff;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 12px;
            justify-content: space-between;
            min-height: 52px;
            padding: 12px 14px;
        }

        .template-preview-head span {
            color: var(--primary);
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .template-preview-head strong {
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 800;
            overflow: hidden;
            text-align: right;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .template-preview-panel iframe {
            background: #fff;
            border: 0;
            flex: 1;
            min-height: 0;
            width: 100%;
        }

        .compose-dialog {
            border-radius: 10px 10px 0 0;
            bottom: 24px;
            box-shadow: 0 18px 48px rgba(15, 23, 42, 0.22), 0 2px 8px rgba(15, 23, 42, 0.12);
            height: min(680px, calc(100dvh - 48px));
            inset: auto 24px 24px auto;
            margin: 0;
            position: fixed;
            width: min(640px, calc(100vw - 48px));
        }

        .compose-dialog[open] {
            animation: compose-window-in 180ms ease both;
        }

        .edit-dialog.compose-dialog::backdrop {
            backdrop-filter: none;
            background: transparent;
            -webkit-backdrop-filter: none;
        }

        @keyframes compose-window-in {
            from {
                opacity: 0;
                transform: translateY(12px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .compose-dialog > .gmail-compose-form {
            background: #fff;
            display: flex;
            flex-direction: column;
            height: 100%;
            max-height: none;
            min-height: 0;
            overflow: hidden;
            width: 100%;
        }

        .gmail-compose-header {
            align-items: center;
            background: #f2f6fc;
            border-bottom: 1px solid #d7dee8;
            color: #1f2937;
            display: flex;
            flex: 0 0 auto;
            gap: 12px;
            justify-content: space-between;
            min-height: 44px;
            padding: 10px 14px;
        }

        .gmail-compose-header strong {
            font-size: 14px;
            font-weight: 700;
        }

        .gmail-compose-close,
        .gmail-compose-discard {
            align-items: center;
            background: transparent;
            border: 0;
            border-radius: 50%;
            color: #4b5563;
            display: inline-flex;
            height: 32px;
            justify-content: center;
            padding: 0;
            width: 32px;
        }

        .gmail-compose-close:hover,
        .gmail-compose-discard:hover {
            background: #e5e7eb;
        }

        .gmail-compose-close svg,
        .gmail-compose-discard svg {
            fill: none;
            height: 18px;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 2;
            width: 18px;
        }

        .gmail-compose-body {
            display: flex;
            flex: 1 1 auto;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
            padding: 0 16px;
        }

        .gmail-compose-body .notice {
            flex: 0 0 auto;
        }

        .gmail-compose-options {
            border-bottom: 1px solid #e5e7eb;
            display: grid;
            gap: 0;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .gmail-compose-row,
        .gmail-compose-line {
            align-items: center;
            border-bottom: 1px solid #e5e7eb;
            display: grid;
            gap: 10px;
            grid-template-columns: 74px minmax(0, 1fr);
            min-height: 42px;
        }

        .gmail-compose-options .gmail-compose-row {
            border-bottom: 0;
        }

        .gmail-compose-row + .gmail-compose-row {
            border-left: 1px solid #e5e7eb;
            padding-left: 14px;
        }

        .gmail-compose-row label,
        .gmail-compose-line label {
            color: #6b7280;
            font-size: 13px;
            font-weight: 500;
        }

        .gmail-compose-row select,
        .gmail-compose-line input {
            background: transparent;
            border: 0;
            box-shadow: none;
            font-size: 14px;
            height: 40px;
            outline: 0;
            padding: 0;
            width: 100%;
        }

        .gmail-compose-row select:focus,
        .gmail-compose-line input:focus,
        .gmail-template-field input:focus,
        .gmail-compose-body textarea:focus {
            border-color: transparent;
            box-shadow: none;
            outline: 0;
        }

        .gmail-compose-body textarea#compose_message_body,
        .gmail-compose-body textarea#campaign_body,
        .gmail-compose-body textarea[id^="contact_message_body_"] {
            border: 0;
            flex: 1 1 auto;
            font-size: 14px;
            line-height: 1.55;
            min-height: 260px;
            outline: 0;
            padding: 16px 0;
            resize: none;
            width: 100%;
        }

        .gmail-template-fields {
            border-top: 1px solid #e5e7eb;
            flex: 0 0 auto;
            padding: 12px 0 14px;
        }

        .gmail-template-title {
            color: #6b7280;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .gmail-template-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .gmail-template-field {
            display: grid;
            gap: 5px;
            min-width: 0;
        }

        .gmail-template-field label {
            color: #6b7280;
            font-size: 12px;
            font-weight: 600;
        }

        .gmail-template-field input {
            background: #fff;
            border: 1px solid #d7dee8;
            border-radius: 8px;
            min-height: 38px;
            padding: 8px 10px;
            width: 100%;
        }

        .gmail-compose-footer {
            align-items: center;
            border-top: 1px solid #e5e7eb;
            display: flex;
            flex: 0 0 auto;
            gap: 12px;
            justify-content: flex-start;
            min-height: 56px;
            padding: 10px 14px;
        }

        .gmail-compose-submit,
        .gmail-compose-footer button[type="submit"] {
            border-radius: 999px;
            min-height: 36px;
            padding: 0 22px;
        }

        .gmail-compose-submit:disabled,
        .gmail-compose-footer button[type="submit"]:disabled {
            cursor: not-allowed;
            opacity: 0.54;
        }

        .gmail-compose-default {
            align-items: center;
            color: #4b5563;
            display: inline-flex;
            font-size: 13px;
            gap: 8px;
            margin: 0;
        }

        .gmail-compose-discard {
            margin-left: auto;
        }

        .email-compose-dialog,
        .campaign-compose-dialog {
            width: min(680px, calc(100vw - 48px));
        }

        .email-compose-dialog {
            height: min(760px, calc(100dvh - 48px));
            width: min(1180px, calc(100vw - 48px));
        }

        .gmail-compose-workspace {
            display: grid;
            flex: 1 1 auto;
            gap: 16px;
            grid-template-columns: minmax(0, 1fr) minmax(360px, 0.9fr);
            min-height: 0;
            overflow: hidden;
        }

        .gmail-compose-editor {
            display: flex;
            flex-direction: column;
            min-height: 0;
            min-width: 0;
            overflow: hidden;
        }

        .gmail-compose-preview {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            margin: 14px 0;
            min-height: 0;
            min-width: 0;
            overflow: hidden;
        }

        .gmail-compose-preview-head {
            align-items: center;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            gap: 12px;
            justify-content: space-between;
            min-height: 46px;
            padding: 10px 12px;
        }

        .gmail-compose-preview-head span {
            color: #1a73e8;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .gmail-compose-preview-head strong {
            color: #5f6368;
            font-size: 12px;
            font-weight: 700;
            overflow: hidden;
            text-align: right;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .gmail-compose-preview iframe {
            background: #fff;
            border: 0;
            flex: 1;
            min-height: 0;
            width: 100%;
        }

        .email-compose-dialog .gmail-compose-header,
        .campaign-compose-dialog .gmail-compose-header {
            background: #f8fafc;
            min-height: 48px;
            padding: 11px 16px;
        }

        .email-compose-dialog .gmail-compose-close,
        .email-compose-dialog .gmail-compose-discard,
        .campaign-compose-dialog .gmail-compose-close,
        .campaign-compose-dialog .gmail-compose-discard {
            border-radius: 999px;
            flex: 0 0 34px;
            height: 34px;
            min-height: 34px;
            min-width: 34px;
            width: 34px;
        }

        .email-compose-dialog .gmail-compose-options,
        .campaign-compose-dialog .gmail-compose-options {
            border-bottom: 0;
            grid-template-columns: 1fr;
        }

        .email-compose-dialog .gmail-compose-row,
        .email-compose-dialog .gmail-compose-line,
        .campaign-compose-dialog .gmail-compose-row,
        .campaign-compose-dialog .gmail-compose-line {
            gap: 14px;
            grid-template-columns: 88px minmax(0, 1fr);
            min-height: 46px;
        }

        .email-compose-dialog .gmail-compose-row,
        .campaign-compose-dialog .gmail-compose-row {
            border-bottom: 1px solid #e5e7eb;
        }

        .email-compose-dialog .gmail-compose-row + .gmail-compose-row,
        .campaign-compose-dialog .gmail-compose-row + .gmail-compose-row {
            border-left: 0;
            padding-left: 0;
        }

        .email-compose-dialog .gmail-compose-row label,
        .email-compose-dialog .gmail-compose-line label,
        .campaign-compose-dialog .gmail-compose-row label,
        .campaign-compose-dialog .gmail-compose-line label {
            color: #5f6368;
            font-size: 13px;
            font-weight: 650;
        }

        .email-compose-dialog .gmail-compose-row select,
        .email-compose-dialog .gmail-compose-line input,
        .campaign-compose-dialog .gmail-compose-row select,
        .campaign-compose-dialog .gmail-compose-line input {
            color: #1f2937;
            min-width: 0;
        }

        .email-compose-dialog .gmail-compose-body textarea#compose_message_body,
        .email-compose-dialog .gmail-compose-body textarea[id^="contact_message_body_"],
        .campaign-compose-dialog .gmail-compose-body textarea#campaign_body {
            min-height: 220px;
            padding: 18px 0;
        }

        .email-compose-dialog .gmail-compose-footer,
        .campaign-compose-dialog .gmail-compose-footer {
            flex-wrap: wrap;
            min-height: 60px;
            padding: 11px 16px;
        }

        .email-compose-dialog .gmail-compose-submit,
        .campaign-compose-dialog .gmail-compose-submit {
            background: #1a73e8;
            box-shadow: none;
            min-width: 92px;
        }

        .email-compose-dialog .gmail-compose-submit:hover,
        .campaign-compose-dialog .gmail-compose-submit:hover {
            background: #1765cc;
            box-shadow: none;
            transform: none;
        }

        .email-compose-dialog .gmail-compose-default,
        .campaign-compose-dialog .gmail-compose-default {
            border: 1px solid transparent;
            border-radius: 999px;
            min-height: 34px;
            padding: 5px 10px 5px 8px;
        }

        .email-compose-dialog .gmail-compose-default:hover,
        .campaign-compose-dialog .gmail-compose-default:hover {
            background: #f1f5f9;
        }

        .email-compose-dialog .gmail-compose-default input,
        .campaign-compose-dialog .gmail-compose-default input {
            accent-color: #1a73e8;
            flex: 0 0 auto;
        }

        .edit-dialog > form {
            display: flex;
            flex-direction: column;
            max-height: calc(100dvh - 32px);
            min-height: 0;
            overflow: hidden;
            width: 100%;
        }

        .confirm-dialog::backdrop,
        .edit-dialog::backdrop {
            backdrop-filter: blur(10px);
            background: rgba(15, 23, 42, 0.38);
            -webkit-backdrop-filter: blur(10px);
        }

        @keyframes modal-in {
            from {
                opacity: 0;
                transform: translateY(8px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .confirm-dialog-body,
        .edit-dialog-body {
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
            padding: 24px;
            width: 100%;
        }

        .confirm-dialog-body p,
        .edit-dialog-body p {
            color: var(--text-secondary);
            margin: 8px 0 0;
        }

        .confirm-dialog-actions,
        .edit-dialog-actions {
            align-items: center;
            border-top: 1px solid var(--border);
            display: flex;
            flex: 0 0 auto;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
            padding: 16px 24px;
        }

        .edit-dialog .form-grid,
        .edit-dialog .form-grid.three {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .edit-dialog .field,
        .edit-dialog .field > span,
        .edit-dialog input,
        .edit-dialog select,
        .edit-dialog textarea {
            min-width: 0;
        }

        .edit-dialog .checkbox {
            align-items: flex-start;
            min-height: 42px;
            overflow-wrap: anywhere;
        }

        .table-wrap {
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow-x: auto;
        }

        .table-tabs {
            align-items: center;
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-radius: 10px;
            display: inline-flex;
            gap: 3px;
            margin-bottom: 18px;
            padding: 3px;
        }

        .table-tab {
            background: transparent;
            border: 0;
            border-radius: 7px;
            box-shadow: none;
            color: var(--text-secondary);
            min-height: 34px;
            padding: 7px 14px;
        }

        .table-tab:hover {
            background: #EEF2FF;
            box-shadow: none;
            color: var(--primary);
            transform: none;
        }

        .table-tab.active {
            background: #fff;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
            color: var(--text);
        }

        .table-tab span {
            color: var(--text-secondary);
            font-size: 11px;
            font-weight: 900;
            margin-left: 6px;
        }

        .table-tab-panel[hidden] {
            display: none;
        }

        .marketing-page-header {
            margin-bottom: 16px;
        }

        .marketing-shell {
            align-items: start;
            gap: 0;
            grid-template-columns: 238px minmax(0, 1fr);
        }

        .marketing-actions {
            gap: 8px;
        }

        .marketing-side-nav {
            display: grid;
            gap: 14px;
            margin-bottom: 0;
            padding: 16px 12px;
            position: static;
            top: auto;
        }

        .marketing-section-nav {
            display: grid;
            gap: 2px;
            min-width: 0;
        }

        .marketing-section-nav a {
            align-items: center;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 999px;
            color: var(--text);
            display: flex;
            font-size: 13px;
            font-weight: 800;
            gap: 10px;
            justify-content: space-between;
            min-height: 36px;
            min-width: 0;
            overflow: hidden;
            padding: 7px 10px;
            transition: background 220ms ease, color 220ms ease;
        }

        .marketing-section-nav a > span {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .marketing-section-nav a:hover,
        .marketing-section-nav a.active {
            background: #EEF2FF;
            border-color: transparent;
            box-shadow: none;
            color: var(--primary);
            transform: none;
        }

        .marketing-section-nav a.active {
            font-weight: 900;
        }

        .marketing-section-nav strong {
            color: var(--text-secondary);
            flex: 0 0 auto;
            font-size: 12px;
            font-weight: 850;
        }

        .marketing-side-summary {
            border-top: 1px solid var(--border);
            display: grid;
            gap: 2px;
            padding: 12px 0 0;
        }

        .marketing-side-summary div {
            align-items: center;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 999px;
            display: flex;
            justify-content: space-between;
            min-height: 34px;
            padding: 7px 10px;
        }

        .marketing-side-summary span {
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 800;
        }

        .marketing-side-summary strong {
            font-size: 14px;
            font-weight: 900;
        }

        .marketing-import-format {
            border-top: 1px solid var(--border);
            display: grid;
            gap: 8px;
            padding-top: 12px;
        }

        .marketing-import-format summary {
            align-items: center;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 999px;
            color: var(--text);
            cursor: pointer;
            display: flex;
            font-size: 13px;
            font-weight: 800;
            justify-content: space-between;
            list-style: none;
            min-height: 34px;
            padding: 7px 10px;
        }

        .marketing-import-format summary::-webkit-details-marker {
            display: none;
        }

        .marketing-import-format summary:hover {
            background: #EEF2FF;
            color: var(--primary);
        }

        .marketing-import-format summary strong {
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 850;
        }

        .marketing-import-format[open] summary strong {
            font-size: 0;
        }

        .marketing-import-format[open] summary strong::after {
            content: "Hide";
            font-size: 12px;
        }

        .marketing-import-format textarea {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-secondary);
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 11px;
            line-height: 1.45;
            min-height: 84px;
            overflow: auto;
            padding: 9px 10px;
            resize: none;
        }

        .marketing-import-format button {
            background: #fff;
            border: 1px solid var(--border);
            box-shadow: none;
            color: var(--text);
            justify-self: start;
        }

        .marketing-import-format button:hover {
            background: #F1F5F9;
            border-color: #CBD5E1;
            box-shadow: none;
            color: var(--primary);
            transform: none;
        }

        .marketing-workspace {
            overflow: hidden;
            padding: 0;
        }

        .marketing-workspace-head {
            align-items: center;
            background: #fff;
            display: flex;
            gap: 10px;
            justify-content: space-between;
            min-height: 68px;
        }

        .marketing-workspace-head h2 {
            font-size: 18px;
            margin: 0;
        }

        .marketing-workspace-head p,
        .marketing-table-head p {
            color: var(--text-secondary);
            margin: 5px 0 0;
        }

        .marketing-workspace .table-tabs {
            flex: 0 0 auto;
            margin-bottom: 0;
        }

        .marketing-tab-body {
            padding: 0;
        }

        .marketing-tab-body > .analytics-grid {
            padding: 18px;
        }

        .marketing-table-head {
            align-items: center;
            display: flex;
            gap: 14px;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .marketing-table-head h3 {
            font-size: 17px;
            margin: 0;
        }

        .marketing-table {
            min-width: 1120px;
            table-layout: fixed;
        }

        .sr-only {
            clip: rect(0, 0, 0, 0);
            border: 0;
            height: 1px;
            margin: -1px;
            overflow: hidden;
            padding: 0;
            position: absolute;
            white-space: nowrap;
            width: 1px;
        }

        .marketing-live-filter {
            align-items: center;
            background: #fff;
            border-bottom: 1px solid var(--border);
            display: grid;
            gap: 10px;
            grid-template-columns: minmax(260px, 1fr) auto auto;
            padding: 14px 18px;
        }

        .marketing-live-search {
            align-items: center;
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-radius: 999px;
            display: flex;
            gap: 9px;
            min-height: 42px;
            min-width: 0;
            padding: 0 8px 0 14px;
        }

        .marketing-live-search > svg {
            color: var(--text-secondary);
            flex: 0 0 auto;
            height: 18px;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 2;
            width: 18px;
        }

        .marketing-live-search input {
            background: transparent;
            border: 0;
            border-radius: 0;
            box-shadow: none;
            min-height: 40px;
            min-width: 0;
            padding: 0;
        }

        .marketing-live-search input:focus {
            box-shadow: none;
        }

        .marketing-live-clear {
            background: transparent;
            border: 0;
            border-radius: 999px;
            color: var(--text-secondary);
            height: 30px;
            min-height: 30px;
            padding: 0;
            width: 30px;
        }

        .marketing-live-clear svg {
            height: 16px;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-width: 2;
            width: 16px;
        }

        .marketing-live-controls {
            align-items: center;
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-radius: 999px;
            display: inline-flex;
            gap: 3px;
            padding: 3px;
        }

        .marketing-live-controls label {
            align-items: center;
            border-radius: 999px;
            color: var(--text-secondary);
            cursor: pointer;
            display: inline-flex;
            font-size: 12px;
            font-weight: 850;
            min-height: 32px;
            padding: 6px 10px;
            transition: background 180ms ease, color 180ms ease, box-shadow 180ms ease;
            white-space: nowrap;
        }

        .marketing-live-controls label.active {
            background: #fff;
            box-shadow: var(--shadow-soft);
            color: var(--primary);
        }

        .marketing-live-controls input {
            appearance: none;
            height: 0;
            min-height: 0;
            opacity: 0;
            padding: 0;
            position: absolute;
            width: 0;
        }

        [data-contact-results] {
            position: relative;
        }

        [data-contact-results]::after {
            background: rgba(255, 255, 255, 0.66);
            content: "";
            inset: 0;
            opacity: 0;
            pointer-events: none;
            position: absolute;
            transition: opacity 160ms ease;
        }

        [data-contact-results].marketing-results-loading::after {
            opacity: 1;
        }

        .marketing-metrics {
            border-bottom: 1px solid var(--border);
            gap: 0;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-bottom: 0;
        }

        .marketing-metrics .metric {
            border: 0;
            border-radius: 0;
            box-shadow: none;
            min-height: 104px;
            padding: 14px 18px;
        }

        .marketing-metrics .metric + .metric {
            border-left: 1px solid var(--border);
        }

        .marketing-metrics .metric-value {
            font-size: 24px;
            overflow-wrap: anywhere;
        }

        .marketing-workspace .table-wrap {
            border-left: 0;
            border-radius: 0;
            border-right: 0;
        }

        .marketing-workspace .marketing-table thead {
            background: #F8FAFC;
        }

        .marketing-workspace .marketing-table th {
            color: var(--primary);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .marketing-workspace .marketing-table th,
        .marketing-workspace .marketing-table td {
            height: 50px;
            max-height: 50px;
            overflow: hidden;
            padding-bottom: 6px;
            padding-top: 6px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .marketing-workspace .marketing-table td.wrap {
            max-width: none;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .marketing-contact-main,
        .marketing-contact-sub {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .marketing-contact-main {
            color: var(--text);
            font-weight: 850;
        }

        .marketing-contact-sub {
            color: var(--text-secondary);
            font-size: 11px;
            font-weight: 750;
            margin-top: 2px;
        }

        .marketing-contact-dialog {
            width: min(860px, calc(100vw - 32px));
        }

        .marketing-contact-dialog-head {
            align-items: center;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 14px;
            margin: -4px 0 20px;
            padding-bottom: 18px;
        }

        .marketing-contact-avatar {
            align-items: center;
            background: #EEF2FF;
            border-radius: 50%;
            color: var(--primary);
            display: inline-flex;
            flex: 0 0 44px;
            font-size: 17px;
            font-weight: 900;
            height: 44px;
            justify-content: center;
            width: 44px;
        }

        .marketing-contact-dialog-head > div:nth-child(2) {
            min-width: 0;
        }

        .marketing-contact-dialog-head .badge {
            flex: 0 0 auto;
            margin-left: auto;
        }

        .marketing-contact-dialog-head h2 {
            font-size: 20px;
            margin: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .marketing-contact-dialog-head p {
            color: var(--text-secondary);
            font-weight: 750;
            margin: 4px 0 0;
        }

        .marketing-contact-dialog-section {
            display: grid;
            gap: 10px;
            margin-top: 18px;
        }

        .marketing-contact-dialog-section h3 {
            color: var(--text);
            font-size: 14px;
            margin: 0;
        }

        .marketing-detail-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin: 0;
        }

        .marketing-detail-grid.compact {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .marketing-detail-grid div {
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-radius: 8px;
            min-width: 0;
            padding: 10px 12px;
        }

        .marketing-detail-grid div.highlight {
            background: #EEF2FF;
            border-color: rgba(79, 107, 255, 0.24);
        }

        .marketing-detail-grid div.wide {
            grid-column: 1 / -1;
        }

        .marketing-detail-grid dt {
            color: var(--primary);
            font-size: 11px;
            font-weight: 900;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .marketing-detail-grid dd {
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
            margin: 0;
            overflow-wrap: anywhere;
            white-space: normal;
        }

        .marketing-contact-raw {
            border-top: 1px solid var(--border);
            padding-top: 16px;
        }

        .marketing-contact-raw summary {
            align-items: center;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            list-style: none;
        }

        .marketing-contact-raw summary::-webkit-details-marker {
            display: none;
        }

        .marketing-contact-raw summary span {
            color: var(--text);
            font-size: 14px;
            font-weight: 850;
        }

        .marketing-contact-raw summary strong {
            color: var(--primary);
            font-size: 12px;
            font-weight: 900;
        }

        .marketing-contact-raw[open] summary strong {
            font-size: 0;
        }

        .marketing-contact-raw[open] summary strong::after {
            content: "Hide";
            font-size: 12px;
        }

        .marketing-detail-grid.raw {
            margin-top: 10px;
        }

        .marketing-pagination {
            border-top: 1px solid var(--border);
            padding: 12px 18px 16px;
        }

        .marketing-pagination nav {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: space-between;
        }

        .marketing-pagination svg {
            height: 16px;
            width: 16px;
        }

        .marketing-pagination a,
        .marketing-pagination span {
            align-items: center;
            border-radius: 999px;
            display: inline-flex;
            font-size: 12px;
            font-weight: 800;
            min-height: 30px;
        }

        .marketing-pagination a {
            color: var(--text-secondary);
        }

        .marketing-pagination a:hover {
            color: var(--primary);
        }

        .marketing-workspace .marketing-table tbody tr:hover td {
            background: #F8FAFC;
        }

        .marketing-workspace .marketing-table .inline-actions {
            gap: 4px;
            justify-content: flex-end;
            max-width: 100%;
            overflow: hidden;
        }

        .marketing-workspace .marketing-table .inline-actions form {
            flex: 0 0 auto;
        }

        .marketing-workspace .button,
        .marketing-workspace button:not(.mail-icon-action) {
            background: transparent;
            border: 1px solid var(--border);
            box-shadow: none;
            color: var(--text-secondary);
            min-height: 32px;
            padding: 6px 12px;
        }

        .marketing-workspace .button:hover,
        .marketing-workspace button:not(.mail-icon-action):hover {
            background: #F1F5F9;
            border-color: #CBD5E1;
            box-shadow: none;
            color: var(--text);
            transform: none;
        }

        .marketing-workspace .button.danger,
        .marketing-workspace button.danger:not(.mail-icon-action) {
            background: transparent;
            border-color: #FECACA;
            color: var(--error);
        }

        .marketing-workspace .button.danger:hover,
        .marketing-workspace button.danger:not(.mail-icon-action):hover {
            background: #FEF2F2;
            box-shadow: none;
            color: var(--error);
        }

        .marketing-empty {
            color: var(--text-secondary);
            font-weight: 750;
            padding: 24px 16px;
            text-align: center;
            white-space: normal;
        }

        .analytics-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: none;
        }

        .analytics-card-head span,
        .analytics-campaign-row span {
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 800;
        }

        .analytics-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .analytics-card {
            padding: 20px;
        }

        .analytics-card-wide {
            grid-column: span 2;
        }

        .analytics-card-head {
            align-items: center;
            display: flex;
            gap: 12px;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .analytics-card-head h3,
        .analytics-card-head h4 {
            font-size: 17px;
            margin: 0;
        }

        .analytics-bars {
            align-items: end;
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(7, minmax(34px, 1fr));
            min-height: 190px;
        }

        .analytics-day {
            align-items: center;
            display: grid;
            gap: 6px;
            justify-items: center;
            min-width: 0;
        }

        .analytics-bar-track {
            align-items: end;
            background: #F1F5F9;
            border-radius: 8px;
            display: flex;
            height: 130px;
            overflow: hidden;
            width: 100%;
        }

        .analytics-bar-track span {
            background: linear-gradient(180deg, #4F6BFF, #20C997);
            border-radius: 8px 8px 0 0;
            display: block;
            min-height: 4px;
            width: 100%;
        }

        .analytics-day strong {
            font-size: 13px;
        }

        .analytics-day small {
            color: var(--text-secondary);
            font-size: 11px;
            font-weight: 850;
        }

        .analytics-progress-list {
            display: grid;
            gap: 13px;
        }

        .analytics-progress-row {
            display: grid;
            gap: 7px;
        }

        .analytics-progress-row > div:first-child,
        .analytics-campaign-row {
            align-items: center;
            display: flex;
            gap: 12px;
            justify-content: space-between;
        }

        .analytics-progress-row strong,
        .analytics-campaign-row strong {
            font-size: 13px;
        }

        .analytics-progress {
            background: #F1F5F9;
            border-radius: 999px;
            height: 8px;
            overflow: hidden;
        }

        .analytics-progress span {
            background: #4F6BFF;
            border-radius: inherit;
            display: block;
            height: 100%;
        }

        .analytics-progress-row[data-tone="green"] .analytics-progress span { background: #16A34A; }
        .analytics-progress-row[data-tone="amber"] .analytics-progress span { background: #D97706; }
        .analytics-progress-row[data-tone="red"] .analytics-progress span { background: #DC2626; }

        .analytics-campaign-list {
            display: grid;
            gap: 10px;
        }

        .analytics-campaign-row {
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 11px 12px;
        }

        .analytics-campaign-row div {
            display: grid;
            gap: 3px;
        }

        .analytics-campaign-row div:last-child {
            justify-items: end;
            text-align: right;
        }

        .analytics-empty {
            color: var(--text-secondary);
            font-weight: 750;
            margin: 0;
            padding: 12px 0;
        }

        .mail-pane .table-wrap {
            border-left: 0;
            border-radius: 0;
            border-right: 0;
            overflow-x: auto;
        }

        table {
            border-collapse: collapse;
            font-size: 13px;
            width: 100%;
        }

        th,
        td {
            border-bottom: 1px solid var(--border);
            padding: 9px 11px;
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        .inbox-table {
            min-width: 760px;
            table-layout: fixed;
        }

        .mail-pane .inbox-table thead {
            display: none;
        }

        .inbox-table th,
        .inbox-table td {
            height: 50px;
            max-height: 50px;
            overflow: hidden;
            padding-bottom: 6px;
            padding-top: 6px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .inbox-table .date-column {
            width: 76px;
        }

        .inbox-table .client-column {
            width: 120px;
        }

        .inbox-table .from-cell {
            width: 230px;
        }

        .inbox-table .status-cell {
            width: 94px;
        }

        .inbox-table td.wrap {
            max-width: none;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .inbox-table .message-subject {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .inbox-table .actions-cell {
            overflow: hidden;
            width: 176px;
        }

        .mail-icon-action {
            align-items: center;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 999px;
            box-shadow: none;
            color: var(--text-secondary);
            display: inline-flex;
            flex: 0 0 28px;
            height: 28px;
            justify-content: center;
            min-height: 28px;
            min-width: 28px;
            padding: 0;
            transform: none;
            width: 28px;
        }

        .mail-icon-action svg {
            fill: none;
            height: 16px;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 2;
            width: 16px;
        }

        .mail-icon-action:hover {
            background: #F1F5F9;
            border-color: transparent;
            box-shadow: none;
            color: var(--text);
            transform: none;
        }

        .mail-icon-action.danger {
            color: var(--text-secondary);
        }

        .mail-icon-action.danger:hover {
            background: #FEF2F2;
            color: var(--error);
        }

        .mail-pane .inbox-table td {
            background: #fff;
        }

        .mail-pane .inbox-table tbody tr {
            cursor: pointer;
        }

        .mail-pane .inbox-table tbody tr:hover td {
            background: #F8FAFC;
        }

        .mail-pane .inbox-table tbody tr.unopened-row td {
            background: #F8FBFF;
            font-weight: 850;
        }

        .mail-pane .inbox-table tbody tr.unopened-row:hover td {
            background: #EEF4FF;
        }

        .mail-pane .inbox-table .inline-actions {
            gap: 4px;
            justify-content: flex-end;
            max-width: 100%;
            overflow: hidden;
        }

        .mail-pane .inbox-table .inline-actions form {
            flex: 0 0 28px;
        }

        tbody tr:last-child td { border-bottom: 0; }
        tbody tr { transition: background 220ms ease; }
        tbody tr:hover { background: #F9FAFB; }
        tbody tr.unopened-row {
            background: #F8FBFF;
            box-shadow: inset 3px 0 0 var(--primary);
        }
        tbody tr.unopened-row:hover { background: #F3F7FF; }

        .date-cell {
            align-items: center;
            display: inline-flex;
            gap: 8px;
        }

        .date-cell.compact {
            gap: 7px;
            line-height: 1.05;
        }

        .date-cell.compact > span:last-child {
            display: grid;
            gap: 2px;
        }

        .date-cell.compact strong {
            color: var(--text);
            font-size: 12px;
            font-weight: 900;
        }

        .date-cell.compact small {
            color: var(--text-secondary);
            font-size: 11px;
            font-weight: 750;
        }

        .unread-dot {
            box-shadow: 0 0 0 4px rgba(79, 107, 255, 0.12);
            height: 8px;
            width: 8px;
        }

        .message-subject.unopened {
            color: var(--text);
            font-weight: 900;
        }

        .message-meta {
            align-items: center;
            color: var(--text-secondary);
            display: flex;
            flex-wrap: wrap;
            gap: 8px 14px;
            margin-top: 6px;
        }

        .message-meta span {
            align-items: center;
            display: inline-flex;
            gap: 6px;
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .message-meta strong {
            color: var(--text);
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .email-reader {
            padding: 20px 24px 24px;
        }

        .email-reader-header {
            align-items: flex-start;
            border-bottom: 1px solid var(--border);
            display: grid;
            gap: 12px;
            grid-template-columns: 42px minmax(0, 1fr) auto;
            margin-bottom: 18px;
            padding-bottom: 16px;
        }

        .sender-avatar {
            align-items: center;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 999px;
            color: #fff;
            display: inline-flex;
            font-size: 15px;
            font-weight: 900;
            height: 42px;
            justify-content: center;
            width: 42px;
        }

        .sender-summary {
            min-width: 0;
        }

        .sender-line {
            align-items: baseline;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            line-height: 1.25;
        }

        .sender-line strong {
            color: var(--text);
            font-size: 14px;
            font-weight: 900;
        }

        .sender-line span,
        .recipient-line,
        .email-received {
            color: var(--text-secondary);
            font-size: 12px;
        }

        .sender-line span {
            overflow-wrap: anywhere;
        }

        .recipient-line {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 3px;
        }

        .recipient-line span + span::before {
            content: "•";
            margin-right: 6px;
        }

        .email-received {
            line-height: 1.25;
            padding-top: 1px;
            white-space: nowrap;
        }

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

        td.actions-cell {
            width: 1%;
        }

        .badge {
            align-items: center;
            border-radius: 999px;
            display: inline-flex;
            font-size: 12px;
            font-weight: 850;
            line-height: 1;
            min-height: 22px;
            padding: 5px 8px;
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
        .badge.info,
        .badge.partial {
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
            background: transparent;
            border: 0;
            border-radius: 0;
            min-height: 180px;
            overflow-x: auto;
            padding: 0;
            white-space: pre-wrap;
        }

        .message-frame {
            background: #fff;
            border: 0;
            border-radius: 0;
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
            .marketing-metrics .metric + .metric {
                border-left: 0;
                border-top: 0;
            }
            .marketing-metrics .metric:nth-child(even) {
                border-left: 1px solid var(--border);
            }
            .marketing-metrics .metric:nth-child(n+3) {
                border-top: 1px solid var(--border);
            }
            .permissions-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .split-grid { grid-template-columns: 1fr; }
            .marketing-shell { grid-template-columns: 1fr; }
            .marketing-side-nav {
                position: static;
            }
            .marketing-section-nav {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            .mail-layout { grid-template-columns: 1fr; }
            .mail-app { min-height: 0; }
            .mail-rail {
                border-bottom: 1px solid var(--border);
                border-right: 0;
                max-height: none;
                min-height: 0;
                overflow: visible;
            }
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

            .sidebar-toggle {
                display: none;
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
                padding: 18px 5% 24px;
            }

            .mail-page-header h1 {
                font-size: 24px;
            }

            .mail-toolbar {
                align-items: flex-start;
                flex-direction: column;
                padding: 14px;
            }

            .mail-toolbar .inline-actions {
                width: 100%;
            }

            .mail-rail {
                padding: 14px 10px;
            }

            .compose-mail-grid {
                grid-template-columns: 1fr;
            }

            .send-compose-layout {
                grid-template-columns: 1fr;
            }

            .send-compose-preview {
                min-height: 420px;
            }

            .template-builder {
                grid-template-columns: 1fr;
            }

            .template-preview-panel {
                min-height: 520px;
            }

            .compose-line {
                grid-template-columns: 1fr;
                gap: 2px;
                padding: 10px 0;
            }

            .compose-line input {
                min-height: 38px;
            }

            .grid,
            .form-grid,
            .form-grid.three,
            .kpi-grid,
            .analytics-grid,
            .permissions-grid {
                grid-template-columns: 1fr;
            }

            .table-filter-bar {
                grid-template-columns: 1fr;
            }

            .table-filter-actions {
                justify-content: flex-start;
            }

            .marketing-metrics .metric:nth-child(even) {
                border-left: 0;
            }

            .marketing-metrics .metric + .metric {
                border-top: 1px solid var(--border);
            }

            .analytics-card-wide {
                grid-column: auto;
            }

            .analytics-bars {
                gap: 8px;
                grid-template-columns: repeat(7, minmax(28px, 1fr));
            }

            .marketing-workspace-head,
            .marketing-table-head {
                align-items: stretch;
                flex-direction: column;
            }

            .marketing-live-filter {
                grid-template-columns: 1fr;
                padding: 12px 14px;
            }

            .marketing-live-controls {
                justify-content: flex-start;
                max-width: 100%;
                overflow-x: auto;
            }

            .marketing-workspace .table-tabs {
                display: grid;
                grid-template-columns: 1fr;
                width: 100%;
            }

            .marketing-section-nav {
                grid-template-columns: 1fr;
            }

            .marketing-contact-dialog-head {
                align-items: flex-start;
            }

            .marketing-detail-grid,
            .marketing-detail-grid.compact {
                grid-template-columns: 1fr;
            }

            .email-reader {
                padding: 18px;
            }

            .email-reader-header {
                grid-template-columns: 38px minmax(0, 1fr);
            }

            .sender-avatar {
                height: 38px;
                width: 38px;
            }

            .email-received {
                grid-column: 2;
            }

            .compose-dialog {
                border-radius: 16px 16px 0 0;
                bottom: 0;
                height: min(92dvh, 720px);
                inset: auto 0 0 0;
                width: 100vw;
            }

            .gmail-compose-workspace {
                grid-template-columns: 1fr;
                overflow-y: auto;
            }

            .gmail-compose-editor {
                overflow: visible;
            }

            .gmail-compose-preview {
                min-height: 420px;
            }

            .gmail-compose-options {
                grid-template-columns: 1fr;
            }

            .gmail-compose-row + .gmail-compose-row {
                border-left: 0;
                border-top: 1px solid #e5e7eb;
                padding-left: 0;
            }

            .gmail-compose-row,
            .gmail-compose-line {
                grid-template-columns: 68px minmax(0, 1fr);
            }

            .gmail-template-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    @auth
        @php
            $currentUser = auth()->user();
            $unopenedNotificationCount = $unopenedNotificationCount ?? 0;
            $unopenedNotificationLabel = $unopenedNotificationCount > 99 ? '99+' : (string) $unopenedNotificationCount;
        @endphp
        <div class="app-shell">
            <aside class="sidebar">
                <div class="sidebar-head">
                    <a class="brand" href="{{ route('dashboard') }}">
                        <span class="brand-mark">P</span>
                        <span class="brand-text">
                            PowerMail
                            <small>Core</small>
                        </span>
                    </a>
                    <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-label="Collapse sidebar" aria-pressed="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                    </button>
                </div>

                <div class="sidebar-scroll">
                    <nav class="sidebar-nav" aria-label="Primary">
                        <div class="nav-section" data-nav-section="overview">
                            <button class="nav-section-title" type="button" data-nav-section-toggle aria-expanded="true" aria-controls="nav-section-overview">
                                <span>Overview</span>
                                <svg class="nav-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="nav-section-items" id="nav-section-overview">
                                <a href="{{ route('dashboard') }}" title="Dashboard" aria-label="Dashboard" @class(['active' => request()->routeIs('dashboard')])>
                                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 13h8V3H3v10Z"/><path d="M13 21h8V11h-8v10Z"/><path d="M13 3v6h8V3h-8Z"/><path d="M3 21h8v-6H3v6Z"/></svg></span>
                                    <span>Dashboard</span>
                                </a>
                            </div>
                        </div>

                        @if ($currentUser->isAdmin())
                            <div class="nav-section" data-nav-section="companies">
                                <button class="nav-section-title" type="button" data-nav-section-toggle aria-expanded="true" aria-controls="nav-section-companies">
                                    <span>Companies</span>
                                    <svg class="nav-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div class="nav-section-items" id="nav-section-companies">
                                    <a href="{{ route('clients.index') }}" title="Clients" aria-label="Clients" @class(['active' => request()->routeIs('clients.*')])>
                                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                                        <span>Clients</span>
                                    </a>
                                    <a href="{{ route('users.index') }}" title="Users" aria-label="Users" @class(['active' => request()->routeIs('users.*')])>
                                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M18 8h4"/><path d="M20 6v4"/></svg></span>
                                        <span>Users</span>
                                    </a>
                                </div>
                            </div>
                        @endif

                        @if ($currentUser->canAccess(\App\Models\User::PERMISSION_SEND_EMAILS) || $currentUser->canAccess(\App\Models\User::PERMISSION_VIEW_INBOX) || $currentUser->canAccess(\App\Models\User::PERMISSION_VIEW_LOGS))
                            <div class="nav-section" data-nav-section="mail">
                                <button class="nav-section-title" type="button" data-nav-section-toggle aria-expanded="true" aria-controls="nav-section-mail">
                                    <span>Mail Operations</span>
                                    <svg class="nav-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div class="nav-section-items" id="nav-section-mail">
                                    @if ($currentUser->canAccess(\App\Models\User::PERMISSION_SEND_EMAILS))
                                        <a href="{{ route('send-email.index') }}" title="Send Email" aria-label="Send Email" @class(['active' => request()->routeIs('send-email.*')])>
                                            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg></span>
                                            <span>Send Email</span>
                                        </a>
                                    @endif
                                    @if ($currentUser->canAccess(\App\Models\User::PERMISSION_VIEW_INBOX))
                                        <a href="{{ route('inbox.index') }}" title="Inbox" aria-label="Inbox" @class(['active' => request()->routeIs('inbox.*')])>
                                            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="m5.45 5.11-3.43 6.86A2 2 0 0 0 2 13v5a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5a2 2 0 0 0-.02-1.03l-3.43-6.86A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg></span>
                                            <span>Inbox</span>
                                        </a>
                                    @endif
                                    @if ($currentUser->canAccess(\App\Models\User::PERMISSION_VIEW_LOGS))
                                        <a href="{{ route('email-logs.index') }}" title="Logs" aria-label="Logs" @class(['active' => request()->routeIs('email-logs.*')])>
                                            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg></span>
                                            <span>Logs</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($currentUser->canAccess(\App\Models\User::PERMISSION_MANAGE_MARKETING))
                            <div class="nav-section" data-nav-section="marketing">
                                <button class="nav-section-title" type="button" data-nav-section-toggle aria-expanded="true" aria-controls="nav-section-marketing">
                                    <span>Marketing</span>
                                    <svg class="nav-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div class="nav-section-items" id="nav-section-marketing">
                                    <a href="{{ route('marketing.index') }}" title="Marketing" aria-label="Marketing" @class(['active' => request()->routeIs('marketing.*')])>
                                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11h4l10-5v12L7 13H3z"/><path d="M7 13v5a2 2 0 0 0 2 2h1"/><path d="M21 9v6"/></svg></span>
                                        <span>Marketing</span>
                                    </a>
                                </div>
                            </div>
                        @endif

                        @if ($currentUser->isAdmin())
                            <div class="nav-section" data-nav-section="developer">
                                <button class="nav-section-title" type="button" data-nav-section-toggle aria-expanded="true" aria-controls="nav-section-developer">
                                    <span>Developer</span>
                                    <svg class="nav-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div class="nav-section-items" id="nav-section-developer">
                                    <a href="{{ route('api-keys.index') }}" title="API Keys" aria-label="API Keys" @class(['active' => request()->routeIs('api-keys.*')])>
                                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15 2 6 6"/><path d="m18 5 3 3"/></svg></span>
                                        <span>API Keys</span>
                                    </a>
                                </div>
                            </div>
                        @endif

                        @if ($currentUser->isAdmin() || $currentUser->canAccess(\App\Models\User::PERMISSION_MANAGE_ACCOUNTS) || $currentUser->canAccess(\App\Models\User::PERMISSION_MANAGE_TEMPLATES))
                            <div class="nav-section settings-nav-section" data-nav-section="settings">
                                <button class="nav-section-title" type="button" data-nav-section-toggle aria-expanded="true" aria-controls="nav-section-settings">
                                    <span>Settings</span>
                                    <svg class="nav-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div class="nav-section-items" id="nav-section-settings">
                                    @if ($currentUser->isAdmin())
                                        <a href="{{ route('domains.index') }}" title="Domains" aria-label="Domains" @class(['active' => request()->routeIs('domains.*')])>
                                            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20"/><path d="M12 2a15.3 15.3 0 0 0 0 20"/></svg></span>
                                            <span>Domains</span>
                                        </a>
                                    @endif
                                    @if ($currentUser->canAccess(\App\Models\User::PERMISSION_MANAGE_ACCOUNTS))
                                        <a href="{{ route('email-accounts.index') }}" title="Email Accounts" aria-label="Email Accounts" @class(['active' => request()->routeIs('email-accounts.*')])>
                                            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="m22 6-10 7L2 6"/></svg></span>
                                            <span>Email Accounts</span>
                                        </a>
                                    @endif
                                    @if ($currentUser->canAccess(\App\Models\User::PERMISSION_MANAGE_TEMPLATES))
                                        <a href="{{ route('email-templates.index') }}" title="Email Templates" aria-label="Email Templates" @class(['active' => request()->routeIs('email-templates.*')])>
                                            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg></span>
                                            <span>Email Templates</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </nav>

                    <div class="sidebar-footer">
                        <strong>{{ str($currentUser->name)->limit(24) }}</strong>
                        <span>{{ $currentUser->email }}</span>
                        <span class="role-badge">{{ $currentUser->isAdmin() ? 'Administrator' : ($currentUser->client?->name ?: 'Client user') }}</span>
                    </div>
                </div>
            </aside>

            <div class="app-main">
                <header class="topbar">
                    <label class="search" aria-label="Search">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="search" placeholder="Search clients, logs, accounts...">
                    </label>

                    <div class="top-actions">
                        @if ($currentUser->canAccess(\App\Models\User::PERMISSION_VIEW_INBOX))
                            <a class="icon-button" href="{{ route('inbox.index', ['opened' => 'unopened']) }}" aria-label="{{ $unopenedNotificationCount }} unopened email{{ $unopenedNotificationCount === 1 ? '' : 's' }}">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                                <span class="notification-badge" data-unopened-notification-count @hidden($unopenedNotificationCount === 0)>{{ $unopenedNotificationLabel }}</span>
                            </a>
                        @else
                        <button class="icon-button" type="button" aria-label="Notifications" disabled>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        </button>
                        @endif
                        <select class="language-select" aria-label="Language">
                            <option>EN</option>
                            <option>ZA</option>
                        </select>
                        <details class="profile-menu">
                            <summary class="profile-trigger">
                                <span class="avatar">{{ strtoupper((string) str($currentUser->name)->substr(0, 1)) }}</span>
                                <span>{{ str($currentUser->name)->limit(18) }}</span>
                            </summary>
                            <div class="profile-dropdown">
                                <strong>{{ $currentUser->name }}</strong>
                                <p>{{ $currentUser->email }}</p>
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
            @yield('content')
        </main>
    @endauth

    <dialog class="confirm-dialog" id="confirm-dialog">
        <div class="confirm-dialog-body">
            <h2>Confirm action</h2>
            <p id="confirm-message">This action cannot be undone.</p>
        </div>
        <div class="confirm-dialog-actions">
            <button class="secondary" type="button" id="confirm-cancel">Cancel</button>
            <button class="danger" type="button" id="confirm-submit">Delete</button>
        </div>
    </dialog>

    @auth
        <script>
            (() => {
                const storageKey = 'powermail.sidebar.collapsed';
                const sectionsStorageKey = 'powermail.sidebar.sections';
                const defaultCollapsedSections = new Set(['overview', 'companies', 'mail']);
                const toggle = document.querySelector('[data-sidebar-toggle]');
                const sections = document.querySelectorAll('[data-nav-section]');

                if (!toggle) {
                    return;
                }

                const setCollapsed = (collapsed) => {
                    document.documentElement.classList.toggle('sidebar-collapsed', collapsed);
                    document.body.classList.toggle('sidebar-collapsed', collapsed);
                    toggle.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
                    toggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
                };

                setCollapsed(localStorage.getItem(storageKey) === 'true');

                toggle.addEventListener('click', () => {
                    const collapsed = !document.body.classList.contains('sidebar-collapsed');
                    localStorage.setItem(storageKey, collapsed ? 'true' : 'false');
                    setCollapsed(collapsed);
                });

                let sectionState = {};

                try {
                    sectionState = JSON.parse(localStorage.getItem(sectionsStorageKey) || '{}') || {};
                } catch (error) {
                    sectionState = {};
                }

                const persistSections = () => {
                    localStorage.setItem(sectionsStorageKey, JSON.stringify(sectionState));
                };

                const setSectionCollapsed = (section, collapsed) => {
                    const key = section.dataset.navSection;
                    const button = section.querySelector('[data-nav-section-toggle]');
                    const hasActiveItem = Boolean(section.querySelector('.nav-section-items a.active'));

                    if (hasActiveItem) {
                        collapsed = false;
                    }

                    document.documentElement.classList.toggle(`nav-section-collapsed-${key}`, collapsed);
                    section.classList.toggle('collapsed', collapsed);
                    button?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                };

                sections.forEach((section) => {
                    const key = section.dataset.navSection;
                    const hasActiveItem = Boolean(section.querySelector('.nav-section-items a.active'));
                    const hasSavedState = Object.prototype.hasOwnProperty.call(sectionState, key);
                    const collapsed = hasActiveItem
                        ? false
                        : (hasSavedState ? sectionState[key] === true : defaultCollapsedSections.has(key));

                    setSectionCollapsed(section, collapsed);

                    section.querySelector('[data-nav-section-toggle]')?.addEventListener('click', () => {
                        const nextCollapsed = !section.classList.contains('collapsed');
                        sectionState[key] = nextCollapsed;
                        setSectionCollapsed(section, nextCollapsed);
                        persistSections();
                    });
                });
            })();
        </script>
    @endauth

    <script>
        window.powerMailTemplatePreview = (() => {
            const escapeHtml = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
            const nl2br = (value) => escapeHtml(value).replace(/\r\n|\r|\n/g, '<br>');
            const placeholderPattern = new RegExp('\\{\\{\\s*([A-Za-z0-9_.-]+)\\s*\\}\\}', 'g');
            const previewCss = `
                <style>
                    html, body {
                        box-sizing: border-box !important;
                        margin: 0 !important;
                        max-width: 100% !important;
                        overflow-x: hidden !important;
                    }
                    *, *::before, *::after {
                        box-sizing: border-box !important;
                    }
                    body {
                        overflow-wrap: anywhere !important;
                        word-break: normal !important;
                    }
                    table {
                        max-width: 100% !important;
                        table-layout: fixed !important;
                    }
                    img, video, canvas, iframe {
                        height: auto !important;
                        max-width: 100% !important;
                    }
                    pre, code {
                        white-space: pre-wrap !important;
                    }
                </style>
            `;

            const render = (template, values, html = false) => String(template || '').replace(placeholderPattern, (match, key) => {
                if (!Object.hasOwn(values, key)) {
                    return '';
                }

                if (html && ['body', 'message'].includes(key)) {
                    return nl2br(values[key]);
                }

                if (html && ['body_html', 'message_html'].includes(key)) {
                    return String(values[key] ?? '');
                }

                return html ? escapeHtml(values[key]) : String(values[key] ?? '');
            });

            const fitPreviewHtml = (content) => {
                const html = String(content || '');

                if (/<\/head>/i.test(html)) {
                    return html.replace(/<\/head>/i, `${previewCss}</head>`);
                }

                if (/<body\b[^>]*>/i.test(html)) {
                    return html.replace(/<body\b([^>]*)>/i, `<body$1>${previewCss}`);
                }

                return `<!doctype html><html><head>${previewCss}</head><body>${html}</body></html>`;
            };

            const collectValues = (root, baseValues = {}) => {
                const values = { ...baseValues };
                root.querySelectorAll('[name^="template_data["]').forEach((input) => {
                    const match = input.name.match(/^template_data\[(.+)]$/);
                    if (match) {
                        values[match[1]] = input.value;
                    }
                });

                const message = root.querySelector('[data-compose-message]')?.value ?? '';
                values.body = message;
                values.message = message;

                return values;
            };

            const refresh = (root, templates, baseValues = {}) => {
                const templateId = root.querySelector('[data-compose-template]')?.value || '';
                const template = templates[templateId];
                const frame = root.querySelector('[data-compose-preview-frame]');
                const subjectTarget = root.querySelector('[data-compose-preview-subject]');
                const subjectInput = root.querySelector('[data-compose-subject]');
                const values = collectValues(root, baseValues);

                if (!template) {
                    const plainSubject = subjectInput?.value || 'Subject preview';
                    const plainMessage = root.querySelector('[data-compose-message]')?.value || '';

                    if (subjectTarget) {
                        subjectTarget.textContent = plainSubject;
                    }

                    if (frame) {
                        frame.srcdoc = fitPreviewHtml(`<div style="font-family:Arial,sans-serif;line-height:1.6;padding:24px;color:#111827;">${nl2br(plainMessage) || '<span style="color:#6b7280;">Write a message to preview it here.</span>'}</div>`);
                    }

                    return;
                }

                const subjectTemplate = subjectInput?.value?.trim() || template.subject || 'Subject preview';

                if (subjectTarget) {
                    subjectTarget.textContent = render(subjectTemplate, values);
                }

                if (frame) {
                    frame.srcdoc = fitPreviewHtml(render(template.body_html, values, true));
                }
            };

            return { refresh };
        })();

        (() => {
            const dialog = document.getElementById('confirm-dialog');
            const message = document.getElementById('confirm-message');
            const cancel = document.getElementById('confirm-cancel');
            const submit = document.getElementById('confirm-submit');
            let pendingForm = null;
            let pendingSubmitter = null;

            const loadingTargets = new WeakSet();

            const setButtonLoading = (button, persist = false) => {
                if (!button || button.disabled || button.getAttribute('aria-disabled') === 'true') {
                    return;
                }

                button.classList.add('is-loading');
                button.setAttribute('aria-busy', 'true');
                loadingTargets.add(button);

                if (button.matches('button[type="submit"], button:not([type]), input[type="submit"]')) {
                    button.disabled = true;
                } else if (button.matches('a')) {
                    button.setAttribute('aria-disabled', 'true');
                }

                if (!persist) {
                    window.setTimeout(() => clearButtonLoading(button), 720);
                }
            };

            const clearButtonLoading = (button) => {
                if (!button || !loadingTargets.has(button)) {
                    return;
                }

                button.classList.remove('is-loading');
                button.removeAttribute('aria-busy');
                loadingTargets.delete(button);

                if (button.matches('button')) {
                    button.disabled = false;
                } else if (button.matches('a')) {
                    button.removeAttribute('aria-disabled');
                }
            };

            const shouldPersistLinkLoading = (link) => {
                const href = link.getAttribute('href') || '';

                return href !== ''
                    && href !== '#'
                    && !href.startsWith('#')
                    && link.target !== '_blank'
                    && !link.hasAttribute('download');
            };

            document.addEventListener('submit', (event) => {
                const form = event.target;
                const confirmText = form?.dataset?.confirm;
                const submitter = event.submitter instanceof HTMLElement ? event.submitter : form?.querySelector('button[type="submit"], button:not([type]), input[type="submit"]');

                if (!confirmText || form.dataset.confirmed === 'true') {
                    setButtonLoading(submitter, true);
                    return;
                }

                event.preventDefault();

                if (!dialog?.showModal) {
                    if (window.confirm(confirmText)) {
                        form.dataset.confirmed = 'true';
                        submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement
                            ? form.requestSubmit(submitter)
                            : form.requestSubmit();
                    }
                    return;
                }

                pendingForm = form;
                pendingSubmitter = submitter;
                message.textContent = confirmText;
                dialog.showModal();
            });

            cancel?.addEventListener('click', () => {
                pendingForm = null;
                pendingSubmitter = null;
                dialog.close();
            });

            submit?.addEventListener('click', () => {
                if (!pendingForm) {
                    dialog.close();
                    return;
                }

                pendingForm.dataset.confirmed = 'true';
                dialog.close();
                setButtonLoading(submit, true);
                pendingSubmitter instanceof HTMLButtonElement || pendingSubmitter instanceof HTMLInputElement
                    ? pendingForm.requestSubmit(pendingSubmitter)
                    : pendingForm.requestSubmit();
            });

            document.addEventListener('click', (event) => {
                const opener = event.target.closest('[data-open-dialog]');
                const closer = event.target.closest('[data-close-dialog]');
                const composeTrigger = event.target.closest('[data-compose-to]');
                const action = event.target.closest('button, a.button, .icon-button');

                if (composeTrigger) {
                    const composeTo = document.getElementById('compose_to');
                    const composeSubject = document.getElementById('compose_subject');
                    const composeData = document.getElementById('compose_data_json');
                    let parsedComposeData = {};

                    if (composeTo) {
                        composeTo.value = composeTrigger.dataset.composeTo || '';
                    }

                    if (composeSubject) {
                        composeSubject.value = composeTrigger.dataset.composeSubject || '';
                    }

                    if (composeData && composeTrigger.dataset.composeData) {
                        composeData.value = composeTrigger.dataset.composeData;
                    }

                    if (composeTrigger.dataset.composeData) {
                        try {
                            parsedComposeData = JSON.parse(composeTrigger.dataset.composeData);
                        } catch (error) {
                            parsedComposeData = {};
                        }
                    }

                    window.powerMailComposeDialog?.setData(parsedComposeData);
                }

                if (opener) {
                    const target = document.getElementById(opener.dataset.openDialog);
                    if (target?.showModal) {
                        target.showModal();
                    }
                }

                if (closer) {
                    closer.closest('dialog')?.close();
                }

                if (!action || action.classList.contains('is-loading') || action.matches('[data-nav-section-toggle]')) {
                    return;
                }

                if (action.matches('button[type="submit"], button:not([type]), input[type="submit"]')) {
                    return;
                }

                if (action.matches('a')) {
                    setButtonLoading(action, shouldPersistLinkLoading(action));
                    return;
                }

                setButtonLoading(action);
            });

            const autoOpenDialog = document.querySelector('dialog[data-auto-open="true"]');
            if (autoOpenDialog?.showModal) {
                autoOpenDialog.showModal();
            }
        })();
    </script>
</body>
</html>
