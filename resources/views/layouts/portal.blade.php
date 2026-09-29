<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Clearance Portal') — ClearanceMS</title>
    @include('partials.favicon')
    @include('partials.app-shell')
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Mulish:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/admin_dashboard.css') }}" rel="stylesheet">
    <link href="{{ asset('css/theme_department.css') }}" rel="stylesheet">
    <link href="{{ asset('css/white_theme.css') }}" rel="stylesheet">
    <style>
        html, body { min-height: 100%; height: 100%; }
        body { margin: 0; padding: 0; background-color: #f4f6f9; color: #1f2937; overflow: hidden; }
        body.student-portal-theme,
        body.student-portal-theme .main {
            background:
                linear-gradient(90deg, rgba(224, 243, 255, .94) 0%, rgba(219, 240, 255, .86) 45%, rgba(207, 235, 255, .64) 100%),
                url("{{ asset('images/mcc-campus.jpg') }}") center / cover fixed no-repeat !important;
        }
        body.student-portal-theme .main .card,
        body.student-portal-theme .main .metric-card,
        body.student-portal-theme .main .messenger,
        body.student-portal-theme .main .filter-bar,
        body.student-portal-theme .main .submission-card {
            border: 1px solid rgba(255, 255, 255, .76) !important;
            background: linear-gradient(135deg, rgba(255, 255, 255, .72), rgba(235, 248, 255, .52)) !important;
            box-shadow:
                0 18px 45px rgba(32, 94, 145, .16),
                0 5px 14px rgba(44, 118, 174, .09),
                inset 0 1px 0 rgba(255, 255, 255, .92) !important;
            backdrop-filter: blur(18px) saturate(145%);
            -webkit-backdrop-filter: blur(18px) saturate(145%);
        }
        body.student-portal-theme .main .card-header,
        body.student-portal-theme .main .card-footer {
            border-color: rgba(177, 211, 233, .48) !important;
            background: rgba(255, 255, 255, .34) !important;
        }
        body.student-portal-theme .main .table {
            --bs-table-bg: transparent;
            --bs-table-accent-bg: transparent;
        }
        body.student-portal-theme .main .card:hover,
        body.student-portal-theme .main .metric-card:hover {
            border-color: rgba(255, 255, 255, .94) !important;
            box-shadow:
                0 22px 52px rgba(32, 94, 145, .2),
                0 7px 17px rgba(44, 118, 174, .11),
                inset 0 1px 0 #fff !important;
        }
        body.campus-glass-theme,
        body.campus-glass-theme .main {
            background:
                linear-gradient(90deg, rgba(224,243,255,.94) 0%, rgba(219,240,255,.86) 45%, rgba(207,235,255,.64) 100%),
                url("{{ asset('images/mcc-campus.jpg') }}") center / cover fixed no-repeat !important;
        }
        body.campus-glass-theme .main .card,
        body.campus-glass-theme .main .metric-card,
        body.campus-glass-theme .main .filter-bar,
        body.campus-glass-theme .main .table-responsive,
        body.campus-glass-theme .main .table-scroll,
        body.campus-glass-theme .main .messenger,
        body.campus-glass-theme .main .info-card,
        body.campus-glass-theme .main .stat-card {
            border: 1px solid rgba(255,255,255,.78) !important;
            background: linear-gradient(135deg, rgba(255,255,255,.75), rgba(235,248,255,.54)) !important;
            box-shadow: 0 18px 45px rgba(32,94,145,.16), 0 5px 14px rgba(44,118,174,.09), inset 0 1px 0 rgba(255,255,255,.94) !important;
            backdrop-filter: blur(18px) saturate(145%);
            -webkit-backdrop-filter: blur(18px) saturate(145%);
        }
        body.campus-glass-theme .main .card-header,
        body.campus-glass-theme .main .card-footer {
            border-color: rgba(177,211,233,.48) !important;
            background: rgba(255,255,255,.34) !important;
        }
        body.campus-glass-theme .main .table,
        body.campus-glass-theme .main .cms-table,
        body.campus-glass-theme .main .cms-table th,
        body.campus-glass-theme .main .cms-table td {
            --bs-table-bg: transparent;
            background: transparent !important;
        }
        body.campus-glass-theme .main .card:hover,
        body.campus-glass-theme .main .metric-card:hover {
            border-color: rgba(255,255,255,.96) !important;
            box-shadow: 0 22px 52px rgba(32,94,145,.2), 0 7px 17px rgba(44,118,174,.11), inset 0 1px 0 #fff !important;
        }
        .student-brand-lockup { display: inline-flex; align-items: center; gap: .65rem; }
        .student-brand-logo {
            width: 42px;
            height: 42px;
            padding: 3px;
            object-fit: contain;
            border: 2px solid rgba(255, 255, 255, .88);
            border-radius: 50%;
            background: rgba(255, 255, 255, .9);
            box-shadow: 0 5px 14px rgba(26, 94, 155, .2);
        }
        .topbar { background: #fff; border-bottom: 1px solid rgba(0,0,0,.08); padding: 0.9rem 1rem; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 50; color: #1f2937; }
        .topbar .brand { font-weight: 700; letter-spacing: .02em; color: #111827; }
        .topbar .brand span { color: #0d6efd; }
        .user-pill { display: inline-flex; align-items: center; gap: .85rem; padding: .5rem .75rem; background: #f8f9fa; border-radius: 999px; color: #1f2937; }
        .user-avatar { width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; background: #0d6efd; color: #fff; font-weight: 700; }
        .sidebar { min-height: 100vh; background-color: #1e2a3a; width: 260px; position: fixed; top: 0; left: 0; will-change: transform, opacity; transition: transform .34s cubic-bezier(.22, 1, .36, 1), opacity .24s ease; transform: translateX(-100%); }
        .sidebar.closed { transform: translateX(-100%); }
        .sidebar.open { transform: translateX(0); }
        .sidebar .nav-link { color: #f8fafc; font-weight: 500; border-radius: .5rem; margin-bottom: .25rem; display: block; padding: .65rem .9rem; transition: background-color .2s ease, color .2s ease; }
        .sidebar .nav-link.active, .sidebar .nav-link:hover { background-color: rgba(13, 110, 253, .16); color: #fff; }
        .sidebar .nav-link.active { box-shadow: inset 0 0 0 1px rgba(13, 110, 253, .20); }
        .sidebar .nav-section { color: #94a3b8; font-size: .8rem; text-transform: uppercase; letter-spacing: .08em; margin-bottom: .75rem; margin-top: 1rem; }
        .sidebar .brand { color: #fff; }
        .sidebar .sidebar-action { width: 100%; text-align: left; color: #111827; background: none; border: none; padding: .65rem .9rem; border-radius: .5rem; cursor: pointer; transition: background-color .2s ease, color .2s ease; }
        .sidebar .sidebar-action:hover { background-color: rgba(13, 110, 253, .12); color: #0d6efd; }
        .sidebar .sidebar-action:focus { outline: none; box-shadow: inset 0 0 0 1px rgba(13, 110, 253, .18); }
        .sidebar .nav-section { color: #94a3b8; font-size: .8rem; text-transform: uppercase; letter-spacing: .08em; margin-bottom: .75rem; margin-top: 1rem; }
        .main { margin-left: 0; height: calc(100vh - 72px); min-height: calc(100vh - 72px); max-height: calc(100vh - 72px); padding: 0; overflow-y: auto; overflow-x: hidden; position: relative; background: #f4f6f9; color: #111827; transition: margin-left .25s ease-in-out; }
        .main.sidebar-open { margin-left: 260px; }
        .container-fluid { height: 100%; overflow-x: hidden; display: flex; flex-direction: column; min-height: 0; padding: .6rem .75rem .75rem; }
        .portal-page-container { padding: 32px clamp(20px, 2.2vw, 34px) 40px; }
        .page-header { margin-bottom: .5rem; flex: 0 0 auto; }
        .page-content { flex: 1 1 auto; overflow: visible; padding-right: .1rem; min-height: 0; }
        .page-content .row { min-height: 100%; min-height: 0; }
        .page-content .card.h-100 { display: flex; flex-direction: column; min-height: 0; }
        .page-content .card.h-100 .card-body { flex: 1 1 auto; overflow: auto; min-height: 0; }
        .page-content .card.h-100 .card-body .d-flex.align-items-end { align-items: flex-end; }
        .card-stat { border: none; border-radius: .75rem; box-shadow: 0 2px 10px rgba(0,0,0,.05); color: #111827; }
        .page-header { margin-bottom: 1.5rem; display: flex; justify-content: space-between; gap: 1rem; align-items: flex-end; color: #111827; }
        .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .metric-card { background: #fff; border-radius: 1rem; padding: 1.5rem; min-height: 130px; box-shadow: 0 18px 48px rgba(24,31,62,.06); }
        .metric-label { color: #6c757d; display: block; margin-bottom: .75rem; }
        .dashboard-shell { display: grid; gap: 1rem; }
        .dashboard-grid { display: grid; gap: 1rem; }
        .modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.35); justify-content: center; align-items: center; padding: 1.25rem; }
        .modal-bg.open { display: flex; }
        .modal { background: #fff; border-radius: 1rem; width: min(700px,100%); max-width: 100%; box-shadow: 0 24px 60px rgba(15,23,42,.2); overflow: hidden; }
        .notification-panel { position: fixed; top: 95px; left: 50%; width: min(420px, calc(100% - 2rem)); max-height: 460px; border-radius: 1rem; background: #fff; box-shadow: 0 28px 72px rgba(15,23,42,.18); opacity: 0; visibility: hidden; transform: translate(-50%, -10px); transition: all .2s ease-in-out; z-index: 10000; display: flex; flex-direction: column; overflow: hidden; }
        .notification-panel.open { opacity: 1; visibility: visible; transform: translate(-50%, 0); }
        .notification-panel .header { padding: 1rem 1.2rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; gap: .75rem; }
        .notification-panel .header .title { font-weight: 700; }
        .notification-panel .header .subtitle { color: #64748b; font-size: .85rem; }
        .notification-panel .header .button-link { border: none; background: transparent; color: #0d6efd; cursor: pointer; font-size: .9rem; padding: 0; }
        .notification-panel .notifications-list { overflow-y: auto; max-height: 320px; }
        .notification-panel .notifications-list .item { padding: 1rem 1.2rem; border-bottom: 1px solid #f1f5f9; display: flex; flex-direction: column; gap: .5rem; }
        .notification-panel .notifications-list .item.unread { background: #eff6ff; }
        .notification-panel .notifications-list .item:last-child { border-bottom: none; }
        .notification-panel .item-message { color: #111827; }
        .notification-panel .item-meta { display: flex; justify-content: space-between; gap: .5rem; color: #64748b; font-size: .82rem; }
        .notification-panel .item-link { color: #0d6efd; text-decoration: none; font-size: .88rem; }
        .notification-panel .item-actions { display:flex; align-items:center; gap:.65rem; }
        .notification-panel .item-delete { border:0; background:transparent; color:#dc3545; padding:0; cursor:pointer; font-size:.88rem; }
        .notification-panel .item-delete:hover { color:#a61e2b; }
        .notification-panel .empty-state { padding: 1.5rem 1.2rem; color: #64748b; text-align: center; }
        .notification-panel { z-index: 10000; }
        .account-panel { position: fixed; top: 58%; left: 50%; width: min(480px, calc(100% - 2rem)); max-height: calc(100vh - 4rem); border-radius: 1rem; background: #fff; box-shadow: 0 28px 72px rgba(15,23,42,.18); opacity: 0; visibility: hidden; transform: translate(-50%, -58%); transition: all .2s ease-in-out; z-index: 10001; display: flex; flex-direction: column; overflow: hidden; pointer-events: auto; }
        .account-panel.open { opacity: 1; visibility: visible; transform: translate(-50%, -50%); }
        .account-panel .panel-header { padding: 1rem 1.2rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; gap: .75rem; }
        .account-panel .panel-title { font-weight: 700; }
        .account-panel .panel-body { padding: 1rem 1.2rem; overflow-y: auto; max-height: calc(100vh - 220px); }
        .account-panel .form-label { display: block; font-size: .9rem; margin-bottom: .35rem; color: #334155; }
        .account-panel .form-control { width: 100%; padding: .75rem .9rem; border: 1px solid #cbd5e1; border-radius: .65rem; }
        .account-panel .form-row { display: grid; gap: 1rem; grid-template-columns: repeat(2, minmax(0, 1fr)); margin-bottom: 1rem; }
        .account-panel .panel-footer { padding: 1rem 1.2rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: .75rem; flex-wrap: wrap; }
        .account-panel .btn-secondary { background: #f1f5f9; border: 1px solid #cbd5e1; color: #111827; }
        .account-panel .btn-primary { background: #0d6efd; border: none; color: #fff; }
        @media (max-width: 768px) {
            .portal-page-container { padding: 20px 12px 26px; }
            .account-panel { left: 50%; top: 50%; transform: translate(-50%, -50%); width: calc(100% - 2rem); max-height: calc(100vh - 3rem); border-radius: .9rem; }
            .account-panel.open { transform: translate(-50%, -50%); }
            .account-panel .panel-body { max-height: calc(100vh - 240px); }
            .account-panel .form-row { grid-template-columns: 1fr; }
            .account-panel .panel-footer { justify-content: stretch; }
            .account-panel .panel-footer .btn { width: 100%; }
        }
        .modal-header, .modal-footer { padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between; }
        .modal-body { padding: 1rem 1.25rem; }
        .modal-title { font-weight: 700; }
        .modal-close { border: none; background: transparent; font-size: 1.1rem; cursor: pointer; }
        .btn-filter { background: #0d6efd; color: #fff; border: none; padding: .75rem 1rem; border-radius: .75rem; width: 100%; }
        .btn-save, .btn-back, .btn-add { border: none; border-radius: .85rem; width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; }
        .btn-save { background: #198754; }
        .btn-back { background: #dc3545; }
        .btn-add { background: #0d6efd; }
        .dashboard-actions { display: flex; gap: .75rem; flex-wrap: wrap; }
        .overlay { position: fixed; inset: 0; background: rgba(15,23,42,.42); opacity: 0; visibility: hidden; transition: opacity .28s ease, visibility .28s ease; z-index: 90; pointer-events: none; }
        .overlay.show { opacity: 1; visibility: visible; pointer-events: auto; }
        @media (max-width: 992px) {
            .sidebar { position: fixed; z-index: 40; }
            .main, .main.sidebar-open { margin-left: 0; }
        }
    </style>
    <link href="{{ asset('css/portal_overlays.css') }}" rel="stylesheet">
    <link href="{{ asset('css/student_portal_chrome.css') }}" rel="stylesheet">
    @stack('styles')
    <link href="{{ asset('css/portal_page_indicator.css') }}" rel="stylesheet">
</head>
<body class="student-portal-theme portal-chrome-theme @yield('theme-body-class') {{ request()->routeIs('registrar.*', 'office.*', 'treasurer.*') ? 'campus-glass-theme' : '' }} {{ str_contains((string) request()->userAgent(), 'MCCStudentAndroid/') ? 'student-android-app' : '' }}">
@include('partials.app-shell-body')
<div class="overlay" id="overlay" onclick="closeOverlay()"></div>
@include('partials.action-feedback-modal')

<div class="topbar">
    <div class="topbar-left d-flex align-items-center gap-3">
        <div class="brand student-brand-lockup">
            <button class="menu-btn btn btn-light btn-sm" type="button" onclick="toggleSidebar()" aria-label="Open navigation"><i class="bi bi-list"></i></button>
            <img class="student-brand-logo" src="{{ asset('images/mcc-logo.png') }}" alt="MCC logo">
            <span class="portal-brand-copy">
                <span class="portal-brand-name"><span>Clearance</span>MS</span>
                <small>
                    @if(request()->routeIs('student.*'))
                        Student Portal
                    @elseif(request()->routeIs('office.*'))
                        Office Portal
                    @elseif(request()->routeIs('treasurer.*'))
                        Treasurer Portal
                    @elseif(request()->routeIs('registrar.*'))
                        Registrar Portal
                    @else
                        Clearance Portal
                    @endif
                </small>
            </span>
        </div>
    </div>
    <div class="topbar-right d-flex align-items-center gap-3">
        @php
            $currentRoute = Route::currentRouteName() ?? '';
            $routePrefix = null;
            if (str_contains($currentRoute, '.')) {
                $routePrefix = explode('.', $currentRoute)[0];
            }
            $isStudentAndroidApp = str_contains((string) request()->userAgent(), 'MCCStudentAndroid/');

            $guardName = match ($routePrefix) {
                'office', 'treasurer', 'registrar', 'student', 'instructor' => $routePrefix,
                'mainAdmin' => 'admin',
                default => null,
            };

            $user = null;
            if ($guardName) {
                $user = auth($guardName)->user();
            }
            $user = $user ?: auth('office')->user() ?? auth('treasurer')->user() ?? auth('registrar')->user() ?? auth('student')->user() ?? auth('instructor')->user() ?? auth()->user();

            $nameFromUser = $user ? trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? '')) : '';
            $portalUserName = trim($__env->yieldContent('user-label')) ?: ($nameFromUser ?: ($user->full_name ?? $user->name ?? 'User'));
            $studentHeaderName = $routePrefix === 'student'
                ? ($nameFromUser ?: ($user->full_name ?? 'Student'))
                : $portalUserName;
            $studentClassMeta = $routePrefix === 'student' && $user
                ? trim(($user->program ?? '') . ' ' . ($user->year_level ?? '') . '-' . ($user->section ?? ''), ' -')
                : '';
            $studentHeaderMeta = $routePrefix === 'student' && $user
                ? implode(' · ', array_filter([$user->student_id ?? null, $studentClassMeta]))
                : '';
            $accountEditRoute = $routePrefix ? route($routePrefix . '.account.edit') : null;
            $accountUpdateRoute = $routePrefix ? route($routePrefix . '.account.update') : null;
        @endphp
        <div class="user-pill" @if($accountEditRoute) onclick="toggleAccountPanel()" style="cursor:pointer;" title="Edit account" @endif>
            <div class="user-avatar">{{ strtoupper(substr($studentHeaderName, 0, 1)) }}</div>
            <div>
                <div class="user-name">{{ $studentHeaderName }}</div>
                <div class="user-role text-muted small">{{ $studentHeaderMeta ?: trim($__env->yieldContent('user-role')) }}</div>
            </div>
            <i class="bi bi-chevron-down student-account-chevron"></i>
        </div>
        <button id="notifBtn" class="user-pill" style="position:relative;" type="button" onclick="toggleNotifications()" aria-controls="notifPanel" aria-expanded="false">
            <i class="bi bi-bell-fill" style="font-size:18px"></i>
            <span id="notifBadge" style="display:none;position:absolute;top:-6px;right:-6px;background:#f43f5e;color:#fff;border-radius:999px;font-size:10px;padding:1px 5px;"></span>
        </button>
    </div>
</div>

<nav class="sidebar closed" id="sidebar">
    <div class="p-3 sidebar-inner">
        <div class="brand mb-4 sidebar-portal-card">
            <span class="sidebar-portal-icon">
                <i class="bi {{
                    match ($routePrefix) {
                        'student' => 'bi-mortarboard',
                        'office' => 'bi-building',
                        'treasurer' => 'bi-wallet2',
                        'registrar' => 'bi-clipboard2-check',
                        default => 'bi-grid-1x2',
                    }
                }}"></i>
            </span>
            <div class="sidebar-portal-copy">
                <div class="fs-5 fw-semibold">@yield('portal-name', 'Clearance Portal')</div>
                <div class="small text-secondary">@yield('portal-subtitle', '')</div>
            </div>
            <button class="sidebar-close-button" type="button" onclick="closeOverlay()" aria-label="Close navigation"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="nav-section">Menu</div>
        <div class="sidebar-nav-links">
            @yield('nav')
            @if($routePrefix === 'office' && \App\Support\LibraryEvaluation::canManage(Auth::guard('office')->user()))
                <a class="nav-link {{ request()->routeIs('office.library-evaluations.*') ? 'active' : '' }}" href="{{ route('office.library-evaluations.index') }}"><i class="bi bi-ui-checks me-2"></i> Library Evaluation</a>
            @endif
            @if($routePrefix === 'office' && \App\Support\LibraryEvaluation::canManage(Auth::guard('office')->user(), 'guidance'))
                <a class="nav-link {{ request()->routeIs('office.guidance-evaluations.*') ? 'active' : '' }}" href="{{ route('office.guidance-evaluations.index') }}"><i class="bi bi-ui-checks me-2"></i> Guidance Evaluation</a>
            @endif
            @if($routePrefix === 'student')
                <a class="nav-link {{ request()->routeIs('student.library-evaluation.*') ? 'active' : '' }}" href="{{ route('student.library-evaluation.index') }}"><i class="bi bi-ui-checks me-2"></i> Library Evaluation</a>
                <a class="nav-link {{ request()->routeIs('student.guidance-evaluation.*') ? 'active' : '' }}" href="{{ route('student.guidance-evaluation.index') }}"><i class="bi bi-ui-checks me-2"></i> Guidance Evaluation</a>
            @endif
            @if($routePrefix === 'student' && ! $isStudentAndroidApp)
                <a class="nav-link" href="{{ route('student.application.download') }}">
                    <i class="bi bi-android2"></i> Download Application
                </a>
            @endif
            <div class="sidebar-account-group">
                <div class="nav-section">Account</div>
                @yield('logout-form')
            </div>
        </div>
    </div>
</nav>

<div class="notification-panel" id="notifPanel" role="dialog" aria-modal="true" aria-label="Notifications">
    <div class="header">
        <div class="panel-heading">
            <span class="panel-icon"><i class="bi bi-bell-fill"></i></span>
            <div class="panel-heading-copy">
                <div class="title">Notifications</div>
                <div class="subtitle">Latest alerts and updates</div>
            </div>
        </div>
        <div class="panel-actions">
            <button class="button-link" id="markAllReadBtn" type="button"><i class="bi bi-check2-all me-1"></i>Mark all read</button>
            <button class="panel-close" type="button" onclick="closeNotifications()" aria-label="Close notifications"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
    <div class="notifications-list" id="notifList">
        <div class="empty-state">Loading notifications…</div>
    </div>
</div>

<div class="account-panel" id="accountPanel" role="dialog" aria-modal="true" aria-labelledby="accountPanelTitle">
    <div class="panel-header">
        <div class="panel-heading">
            <span class="panel-icon account"><i class="bi bi-person-gear"></i></span>
            <div class="panel-heading-copy">
                <div class="panel-title" id="accountPanelTitle">Edit Account</div>
                <div class="subtitle">Update your profile and password</div>
            </div>
        </div>
        <button class="panel-close" type="button" onclick="closeAccountPanel()" aria-label="Close account editor"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="panel-body">
        <form method="POST" action="{{ $accountUpdateRoute ?? '#' }}">
            @csrf
            @method('PUT')

            <div class="form-row">
                <div>
                    <label class="form-label">First Name</label>
                    <input type="text" name="firstname" class="form-control" value="{{ old('firstname', $user->firstname ?? '') }}" required pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}">
                </div>
                <div>
                    <label class="form-label">Last Name</label>
                    <input type="text" name="lastname" class="form-control" value="{{ old('lastname', $user->lastname ?? '') }}" required pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}">
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middlename" class="form-control" value="{{ old('middlename', $user->middlename ?? '') }}" pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}">
                </div>
                <div>
                    <label class="form-label">Suffix</label>
                    <input type="text" name="suffix" class="form-control" value="{{ old('suffix', $user->suffix ?? '') }}">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $user->email ?? '') }}" required>
            </div>

            <div class="form-row">
                <div>
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control">
                </div>
                <div>
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-control">
                </div>
            </div>

            <div class="panel-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAccountPanel()"><i class="bi bi-x-lg me-1"></i>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="main" id="mainContent">
    <div class="container-fluid portal-page-container">
        @if (! $__env->hasSection('hide-page-header'))
            @php
                $portalRoleLabel = match ($routePrefix) {
                    'student' => 'Student Portal',
                    'office' => 'Office Portal',
                    'treasurer' => 'Treasurer Portal',
                    'registrar' => 'Registrar Portal',
                    default => 'Clearance Portal',
                };
                $portalPageMeta = match (true) {
                    request()->routeIs('student.dashboard') => ['eyebrow' => 'Clearance Overview', 'icon' => 'bi bi-clipboard2-check-fill', 'description' => 'Track your clearance progress, outstanding requirements, and completed approvals.'],
                    request()->routeIs('student.clearance-updates') => ['eyebrow' => 'Clearance Activity', 'icon' => 'bi bi-arrow-repeat', 'description' => 'Follow every clearance step and see which offices or instructors still require action.'],
                    request()->routeIs('student.library-evaluation.*') => ['eyebrow' => 'Library Services', 'icon' => 'bi bi-ui-checks', 'description' => 'Rate your library experience to complete the evaluation required for library clearance.'],
                    request()->routeIs('student.submission-remark') => ['eyebrow' => 'Requirements Center', 'icon' => 'bi bi-file-earmark-arrow-up-fill', 'description' => 'Upload required documents and review feedback from your clearance reviewers.'],
                    request()->routeIs('student.chat-support') => ['eyebrow' => 'Student Support', 'icon' => 'bi bi-chat-square-text-fill', 'description' => 'Message your instructors, offices, treasurers, and the registrar about clearance concerns.'],
                    request()->routeIs('office.chat'), request()->routeIs('treasurer.chat'), request()->routeIs('registrar.chat') => ['eyebrow' => 'Student Messages', 'icon' => 'bi bi-chat-square-text-fill', 'description' => 'Answer student questions and follow up on outstanding clearance requirements.'],
                    request()->routeIs('office.dashboard') => ['eyebrow' => 'Office Overview', 'icon' => 'bi bi-building-check', 'description' => 'Review student requests, check submissions, and keep office clearance decisions moving.'],
                    request()->routeIs('office.submissions') => ['eyebrow' => 'Document Review', 'icon' => 'bi bi-folder2-open', 'description' => 'Inspect uploaded requirements, record remarks, and complete document reviews.'],
                    request()->routeIs('office.clearance.requests') => ['eyebrow' => 'Clearance Review', 'icon' => 'bi bi-clipboard2-check-fill', 'description' => 'Evaluate student clearance requests and record the appropriate office decision.'],
                    request()->routeIs('office.library-evaluations.*') => ['eyebrow' => 'Library Services', 'icon' => 'bi bi-ui-checks', 'description' => 'Manage the library evaluation and review student responses for clearance.'],
                    request()->routeIs('treasurer.dashboard') => ['eyebrow' => 'Financial Overview', 'icon' => 'bi bi-bank2', 'description' => 'Monitor financial clearance cases, supporting documents, and outstanding balances.'],
                    request()->routeIs('treasurer.clearance-updates') => ['eyebrow' => 'Financial Review', 'icon' => 'bi bi-cash-coin', 'description' => 'Review student financial clearance records and update their current status.'],
                    request()->routeIs('treasurer.submission-remark') => ['eyebrow' => 'Requirements Review', 'icon' => 'bi bi-file-earmark-check-fill', 'description' => 'Inspect submitted financial documents and record review remarks.'],
                    request()->routeIs('registrar.dashboard') => ['eyebrow' => 'Registrar Overview', 'icon' => 'bi bi-clipboard-data-fill', 'description' => 'Monitor student records, finalize clearances, and verify completed forms.'],
                    request()->routeIs('registrar.student-clearance') => ['eyebrow' => 'Clearance Management', 'icon' => 'bi bi-people-fill', 'description' => 'Review student clearance records and issue final registrar decisions.'],
                    request()->routeIs('registrar.qr-scanner') => ['eyebrow' => 'Verification Tools', 'icon' => 'bi bi-qr-code-scan', 'description' => 'Scan a clearance QR code to confirm the authenticity of a completed record.'],
                    request()->routeIs('registrar.clearance-verification') => ['eyebrow' => 'Record Verification', 'icon' => 'bi bi-patch-check-fill', 'description' => 'Confirm clearance details and validate the selected student record.'],
                    default => ['eyebrow' => $portalRoleLabel, 'icon' => 'bi bi-compass-fill', 'description' => 'Manage the clearance information and actions available on this page.'],
                };
                $portalScope = trim($__env->yieldContent('portal-subtitle'));
                $portalIndicatorBadge = match ($routePrefix) {
                    'student' => $portalScope ? 'Student · '.$portalScope : 'Student',
                    'office' => $portalScope ?: 'Office Personnel',
                    'treasurer' => $portalScope ?: 'Treasurer',
                    'registrar' => 'Registrar Office',
                    default => $portalRoleLabel,
                };
                $portalIndicatorBadgeIcon = match ($routePrefix) {
                    'student' => 'bi bi-mortarboard',
                    'office' => 'bi bi-building',
                    'treasurer' => 'bi bi-wallet2',
                    'registrar' => 'bi bi-building-check',
                    default => 'bi bi-person-badge',
                };
            @endphp
            <x-portal.page-indicator
                :title="trim($__env->yieldContent('page-title')) ?: 'Dashboard'"
                :description="$portalPageMeta['description']"
                :eyebrow="$portalPageMeta['eyebrow']"
                :icon="$portalPageMeta['icon']"
                :badge="$portalIndicatorBadge"
                :badge-icon="$portalIndicatorBadgeIcon"
                :variant="$routePrefix ?: 'portal'"
            >
                @hasSection('header-actions')
                    <x-slot:actions>@yield('header-actions')</x-slot:actions>
                @endif
            </x-portal.page-indicator>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @yield('content')
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@php
    $notificationPortalGuard = match (true) {
        request()->routeIs('student.*') => 'student',
        request()->routeIs('instructor.*') => 'instructor',
        request()->routeIs('registrar.*') => 'registrar',
        request()->routeIs('treasurer.*') => 'treasurer',
        request()->routeIs('office.*') => 'office',
        default => 'web',
    };
@endphp
<script>
const notificationPortalGuard = @json($notificationPortalGuard);
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const main = document.getElementById('mainContent');
    const isOpening = sidebar.classList.contains('closed');

    sidebar.classList.toggle('closed');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');

    if (window.innerWidth > 992) {
        main.classList.toggle('sidebar-open', isOpening);
    } else {
        main.classList.remove('sidebar-open');
    }
}
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const main = document.getElementById('mainContent');
    sidebar.classList.add('closed');
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
    main.classList.remove('sidebar-open');
}
function closeNotifications() {
    const panel = document.getElementById('notifPanel');
    const overlay = document.getElementById('overlay');
    panel.classList.remove('open');
    document.getElementById('notifBtn')?.setAttribute('aria-expanded', 'false');
    overlay.classList.remove('show');
}
function closeAccountPanel() {
    const panel = document.getElementById('accountPanel');
    const overlay = document.getElementById('overlay');
    panel.classList.remove('open');
    overlay.classList.remove('show');
}
function closeOverlay() {
    closeSidebar();
    closeNotifications();
    closeAccountPanel();
}
function toggleAccountPanel() {
    const panel = document.getElementById('accountPanel');
    const overlay = document.getElementById('overlay');
    const sidebar = document.getElementById('sidebar');

    if (panel.classList.contains('open')) {
        closeAccountPanel();
        return;
    }

    sidebar.classList.add('closed');
    sidebar.classList.remove('open');
    closeNotifications();
    panel.classList.add('open');
    document.getElementById('notifBtn')?.setAttribute('aria-expanded', 'true');
    overlay.classList.add('show');
}
async function toggleNotifications() {
    const panel = document.getElementById('notifPanel');
    const overlay = document.getElementById('overlay');
    const sidebar = document.getElementById('sidebar');

    if (panel.classList.contains('open')) {
        closeNotifications();
        return;
    }

    sidebar.classList.add('closed');
    sidebar.classList.remove('open');
    panel.classList.add('open');
    overlay.classList.add('show');
    await loadNotifications(true);
}
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}
async function loadNotifications(force = false) {
    const list = document.getElementById('notifList');
    const badge = document.getElementById('notifBadge');

    try {
        const res = await fetch(`${@json(route('notifications.api'))}?guard=${notificationPortalGuard}`, { headers: { Accept: 'application/json' } });
        if (!res.ok) {
            list.innerHTML = '<div class="empty-state">Unable to load notifications.</div>';
            return;
        }

        const data = await res.json();
        if (data.unread > 0) {
            badge.style.display = 'inline-block';
            badge.textContent = data.unread;
        } else {
            badge.style.display = 'none';
        }

        if (!Array.isArray(data.notifications) || data.notifications.length === 0) {
            list.innerHTML = '<div class="empty-state">No notifications yet.</div>';
            return;
        }

        list.innerHTML = data.notifications.map(notification => {
            const unreadClass = notification.is_read === 0 ? 'unread' : '';
            const link = notification.link_url ? `<a href="${notification.link_url}" class="item-link">View</a>` : '';
            const deleteButton = `<button type="button" class="item-delete" onclick="deleteNotification(${notification.id})" title="Delete notification"><i class="bi bi-trash"></i> Delete</button>`;

            return `
                <div class="item ${unreadClass}">
                    <div class="item-message">${notification.message}</div>
                    <div class="item-meta">
                        <span>${notification.created_at}</span>
                        <span class="item-actions">${link}${deleteButton}</span>
                    </div>
                </div>
            `;
        }).join('');
    } catch (error) {
        list.innerHTML = '<div class="empty-state">Unable to load notifications.</div>';
    }
}
async function deleteNotification(notificationId) {
    if (!confirm('Delete this notification?')) return;

    const res = await fetch(`${@json(url('/notifications/api'))}/${notificationId}?guard=${notificationPortalGuard}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
    });

    if (res.ok) await loadNotifications(true);
}
async function markAllNotificationsRead() {
    try {
        const res = await fetch(`${@json(route('notifications.api.readAll'))}?guard=${notificationPortalGuard}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
        });

        if (res.ok) {
            await loadNotifications(true);
        }
    } catch (error) {
        // ignore
    }
}

document.addEventListener('DOMContentLoaded', function () {
    loadNotifications();
    const markAllButton = document.getElementById('markAllReadBtn');
    if (markAllButton) {
        markAllButton.addEventListener('click', markAllNotificationsRead);
    }
});
setInterval(loadNotifications, 30000);
</script>
<script src="{{ asset('js/protected-page-history.js') }}"></script>
<script src="{{ asset('js/auth-feedback-modals.js') }}"></script>
@stack('scripts')
</body>
</html>
