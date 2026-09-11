<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Instructor') — ClearanceMS</title>
    @include('partials.favicon')
    @include('partials.app-shell')
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Mulish:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="{{ asset('css/admin_dashboard.css') }}" rel="stylesheet">
    <link href="{{ asset('css/theme_department.css') }}" rel="stylesheet">
    <link href="{{ asset('css/white_theme.css') }}" rel="stylesheet">
    <style>
        html, body { height: 100%; margin: 0; }
        body { padding: 0; overflow: hidden; }
        .topbar { position: sticky; top: 0; height: 79px; padding: 10px 20px; }
        .main { height: calc(100vh - 79px); min-height: calc(100vh - 79px); max-height: calc(100vh - 79px); overflow-x: hidden; overflow-y: auto; }
        .main > .page-content-fit { min-height: 100%; display: flex; flex-direction: column; gap: .6rem; overflow: visible; }
        .dashboard-compact { font-size: .9rem; }
        .dashboard-compact .card { border: none; border-radius: .85rem; box-shadow: 0 8px 26px rgba(15,23,42,.06); }
        .dashboard-compact .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: .75rem; margin-bottom: .6rem; }
        .dashboard-compact .metric-card { background: #fff; border-radius: .85rem; padding: .8rem .9rem; min-height: 90px; }
        .dashboard-compact .metric-label { color: #6b7280; display: block; margin-bottom: .25rem; font-size: .78rem; }
        .dashboard-compact .metric-card strong { font-size: 1.2rem; }
        .dashboard-compact .chart-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; flex: 1; min-height: 0; }
        .dashboard-compact .chart-card { padding: .8rem; display: flex; flex-direction: column; min-height: 0; }
        .dashboard-compact .mini-bars { height: 150px; display: flex; align-items: flex-end; gap: .4rem; }
        .dashboard-compact .mini-bars > div { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; }
        .dashboard-compact .mini-bars .bar { width: 100%; max-width: 48px; border-radius: 999px 999px 0 0; background: linear-gradient(180deg, #4f8cff, #2563eb); }
        .dashboard-compact .mini-bars .bar.alt { background: linear-gradient(180deg, #34d399, #059669); }
        .dashboard-compact .mini-bars .bar-label { font-size: .7rem; color: #6b7280; margin-top: .25rem; }
        body.instructor-portal-theme,
        body.instructor-portal-theme .main {
            background:
                linear-gradient(90deg, rgba(224, 243, 255, .94) 0%, rgba(219, 240, 255, .86) 45%, rgba(207, 235, 255, .64) 100%),
                url("{{ asset('images/mcc-campus.jpg') }}") center / cover fixed no-repeat !important;
        }
        body.instructor-portal-theme .main .card,
        body.instructor-portal-theme .main .metric-card,
        body.instructor-portal-theme .main .filter-bar,
        body.instructor-portal-theme .main .table-scroll,
        body.instructor-portal-theme .main .messenger {
            border: 1px solid rgba(255,255,255,.78) !important;
            background: linear-gradient(135deg, rgba(255,255,255,.75), rgba(235,248,255,.54)) !important;
            box-shadow: 0 18px 45px rgba(32,94,145,.16), 0 5px 14px rgba(44,118,174,.09), inset 0 1px 0 rgba(255,255,255,.94) !important;
            backdrop-filter: blur(18px) saturate(145%);
            -webkit-backdrop-filter: blur(18px) saturate(145%);
        }
        body.instructor-portal-theme .main .card:hover,
        body.instructor-portal-theme .main .metric-card:hover {
            border-color: rgba(255,255,255,.96) !important;
            box-shadow: 0 22px 52px rgba(32,94,145,.2), 0 7px 17px rgba(44,118,174,.11), inset 0 1px 0 #fff !important;
        }
        body.instructor-portal-theme .main .cms-table,
        body.instructor-portal-theme .main .cms-table th,
        body.instructor-portal-theme .main .cms-table td { background: transparent !important; }
        body.instructor-portal-theme .main .filter-bar { padding: 1rem; border-radius: 1rem; }
        @media (max-width: 992px) {
            .dashboard-compact .chart-row { grid-template-columns: 1fr; }
        }
        .overlay { position: fixed; inset: 0; background: rgba(0,0,0,.35); opacity: 0; visibility: hidden; transition: all .2s ease-in-out; z-index: 9998; pointer-events: none; }
        .overlay.show { opacity: 1; visibility: visible; pointer-events: auto; }
        .modal-bg { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, .6); justify-content: center; align-items: center; padding: 1.25rem; z-index: 10005; }
        .modal-bg.open { display: flex; }
        .modal-bg .modal { width: min(520px, 100%); background: #fff; border-radius: 1rem; box-shadow: 0 24px 60px rgba(15, 23, 42, .25); overflow: hidden; position: relative; margin: auto; }
        .modal-bg .modal-header, .modal-bg .modal-footer { padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; }
        .modal-bg .modal-body { padding: 1rem 1.25rem; }
        .modal-bg .modal-title { font-weight: 700; font-size: 1.05rem; }
        .modal-bg .modal-close { border: none; background: #f1f5f9; color: #334155; border-radius: 999px; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; cursor: pointer; }
        .modal-bg .modal-close:hover { background: #e2e8f0; }
        .modal-bg textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: .75rem; padding: .75rem .9rem; resize: vertical; min-height: 120px; }
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
            .topbar { width: 100%; height: 67px; min-height: 67px; padding: 8px 12px; }
            .main { height: calc(100vh - 67px); min-height: calc(100vh - 67px); max-height: calc(100vh - 67px); }
            .account-panel { left: 50%; top: 50%; transform: translate(-50%, -50%); width: calc(100% - 2rem); max-height: calc(100vh - 3rem); border-radius: .9rem; }
            .account-panel.open { transform: translate(-50%, -50%); }
            .account-panel .panel-body { max-height: calc(100vh - 240px); }
            .account-panel .form-row { grid-template-columns: 1fr; }
            .account-panel .panel-footer { justify-content: stretch; }
            .account-panel .panel-footer .btn { width: 100%; }
        }
    </style>
    <link href="{{ asset('css/portal_overlays.css') }}" rel="stylesheet">
    <link href="{{ asset('css/student_portal_chrome.css') }}" rel="stylesheet">
    @stack('styles')
    <link href="{{ asset('css/portal_page_indicator.css') }}" rel="stylesheet">
</head>
<body class="student-portal-theme portal-chrome-theme instructor-portal-theme dept-{{ \App\Support\InstructorDepartment::slug(auth('instructor')->user()->department ?? '') }}">
@include('partials.app-shell-body')
@php
    $accountUpdateRoute = route('instructor.account.update');
@endphp
<div class="overlay" id="overlay" onclick="closeOverlay()"></div>
@include('partials.action-feedback-modal')

{{-- TOPBAR --}}
<div class="topbar">
    <div class="topbar-left">
        <div class="brand student-brand-lockup">
            <button class="menu-btn" type="button" onclick="toggleSidebar()" aria-label="Open navigation"><i class="bi bi-list"></i></button>
            <img class="student-brand-logo" src="{{ asset('images/mcc-logo.png') }}" alt="MCC logo">
            <span class="portal-brand-copy">
                <span class="portal-brand-name"><span>Clearance</span>MS</span>
                <small>Instructor Portal</small>
            </span>
        </div>
    </div>
    <div class="topbar-right">
        <div class="user-pill" onclick="toggleAccountPanel()" style="cursor:pointer;" title="Edit account">
            <div class="user-avatar">{{ strtoupper(substr(auth('instructor')->user()->firstname ?? 'I',0,1)) }}</div>
            <div>
                <div class="user-name">{{ auth('instructor')->user()->full_name ?? 'Instructor' }}</div>
                <div class="user-role">{{ auth('instructor')->user()->department_label ?? 'Instructor' }}</div>
            </div>
            <i class="bi bi-chevron-down student-account-chevron"></i>
        </div>

        <button id="notifBtn" class="user-pill" style="position:relative;" type="button" onclick="toggleNotifications()" aria-controls="notifPanel" aria-expanded="false">
            <i class="bi bi-bell-fill" style="font-size:18px"></i>
            <span id="notifBadge" style="display:none;position:absolute;top:-6px;right:-6px;
                background:#f43f5e;color:#fff;border-radius:999px;font-size:10px;padding:1px 5px;"></span>
        </button>
    </div>
</div>

{{-- SIDEBAR --}}
<div class="sidebar closed" id="sidebar">
    <div class="p-3 sidebar-inner">
        <div class="brand mb-4 sidebar-portal-card">
            <span class="sidebar-portal-icon"><i class="bi bi-person-video3"></i></span>
            <div class="sidebar-portal-copy">
                <div class="fs-5 fw-semibold">Instructor Portal</div>
                <div class="small text-secondary">{{ auth('instructor')->user()->department_label ?? 'Faculty account' }}</div>
            </div>
            <button class="sidebar-close-button" type="button" onclick="closeOverlay()" aria-label="Close navigation"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="sidebar-nav-links">
            <div class="nav-section">Main</div>
            <a href="{{ route('instructor.dashboard') }}" class="nav-link {{ request()->routeIs('instructor.dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
            <div class="nav-section">Management</div>
            <a href="{{ route('instructor.submissions.index') }}" class="nav-link {{ request()->routeIs('instructor.submissions*') ? 'active' : '' }}"><i class="bi bi-file-earmark-arrow-up"></i> Submissions</a>
            <a href="{{ route('instructor.clearance') }}" class="nav-link {{ request()->routeIs('instructor.clearance') ? 'active' : '' }}"><i class="bi bi-clipboard2-check"></i> Clearance</a>
            <a href="{{ route('instructor.chat') }}" class="nav-link {{ request()->routeIs('instructor.chat') ? 'active' : '' }}"><i class="bi bi-chat-square-text"></i> Messages</a>
            <div class="sidebar-account-group">
                <div class="nav-section">Account</div>
                <form method="POST" action="{{ route('instructor.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right"></i> Log Out</button></form>
            </div>
        </div>
    </div>
</div>

{{-- PAGE CONTENT --}}
<div class="main sidebar-collapsed" id="mainContent">
    <div class="page-content-fit">
        @php
            $instructorPageMeta = match (true) {
                request()->routeIs('instructor.dashboard') => ['title' => 'Instructor Dashboard', 'eyebrow' => 'Teaching Overview', 'icon' => 'bi bi-person-workspace', 'description' => 'Review submissions, manage clearances, and support your assigned students.'],
                request()->routeIs('instructor.submissions.index') => ['title' => 'Submission & Remark', 'eyebrow' => 'Document Review', 'icon' => 'bi bi-file-earmark-arrow-up-fill', 'description' => 'Review submitted files, record feedback, and update student clearance status.'],
                request()->routeIs('instructor.clearance') => ['title' => 'Student Clearance', 'eyebrow' => 'Clearance Review', 'icon' => 'bi bi-clipboard2-check-fill', 'description' => 'Review assigned students and record clearance decisions for your subjects.'],
                request()->routeIs('instructor.chat') => ['title' => 'Messages', 'eyebrow' => 'Student Support', 'icon' => 'bi bi-chat-square-text-fill', 'description' => 'Communicate with students assigned to your classes and address their concerns.'],
                default => ['title' => trim($__env->yieldContent('title')) ?: 'Instructor Portal', 'eyebrow' => 'Instructor Portal', 'icon' => 'bi bi-person-video3', 'description' => 'Manage your teaching and clearance responsibilities.'],
            };
            $instructorDepartment = auth('instructor')->user()->department_label ?? 'Instructor';
        @endphp
        <x-portal.page-indicator
            :title="$instructorPageMeta['title']"
            :description="$instructorPageMeta['description']"
            :eyebrow="$instructorPageMeta['eyebrow']"
            :icon="$instructorPageMeta['icon']"
            :badge="$instructorDepartment"
            badge-icon="bi bi-mortarboard"
            variant="instructor"
        />
        @yield('content')
    </div>
</div>

@include('instructor.components.esignature-widget')

@include('mainAdmin.partials.csv-import-modal')

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

<div class="account-panel" id="accountPanel" role="dialog" aria-modal="true" aria-labelledby="instructorAccountPanelTitle">
    <div class="panel-header">
        <div class="panel-heading">
            <span class="panel-icon account"><i class="bi bi-person-gear"></i></span>
            <div class="panel-heading-copy">
                <div class="panel-title" id="instructorAccountPanelTitle">Edit Account</div>
                <div class="subtitle">Update your profile and password</div>
            </div>
        </div>
        <button class="panel-close" type="button" onclick="closeAccountPanel()" aria-label="Close account editor"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="panel-body">
        <form method="POST" action="{{ $accountUpdateRoute }}" id="instructorAccountForm">
            @csrf
            @method('PUT')

            <div class="form-row">
                <div>
                    <label class="form-label">First Name</label>
                    <input type="text" name="firstname" class="form-control" value="{{ old('firstname', auth('instructor')->user()->firstname ?? '') }}" required pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}">
                </div>
                <div>
                    <label class="form-label">Last Name</label>
                    <input type="text" name="lastname" class="form-control" value="{{ old('lastname', auth('instructor')->user()->lastname ?? '') }}" required pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}">
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middlename" class="form-control" value="{{ old('middlename', auth('instructor')->user()->middlename ?? '') }}" pattern="{{ \App\Support\PersonName::PATTERN }}" title="{{ \App\Support\PersonName::REQUIREMENT_MESSAGE }}">
                </div>
                <div>
                    <label class="form-label">Suffix</label>
                    <input type="text" name="suffix" class="form-control" value="{{ old('suffix', auth('instructor')->user()->suffix ?? '') }}">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', auth('instructor')->user()->email ?? '') }}" required>
            </div>

            <div class="form-row{{ \App\Models\Instructor::tracksEmploymentStatus() ? '' : ' single' }}">
                <div>
                    <label class="form-label">Department</label>
                    <select name="department" class="form-control" required>
                        @foreach(\App\Support\InstructorDepartment::OPTIONS as $departmentOption)
                        <option value="{{ $departmentOption }}" {{ old('department', \App\Support\InstructorDepartment::canonical(auth('instructor')->user()->department ?? '')) === $departmentOption ? 'selected' : '' }}>{{ $departmentOption }}</option>
                        @endforeach
                    </select>
                </div>
                @if(\App\Models\Instructor::tracksEmploymentStatus())
                <div>
                    {{-- Read-only: Main Admin owns an instructor's employment status. --}}
                    <label class="form-label">Position</label>
                    <input type="text" class="form-control" value="{{ auth('instructor')->user()->employment_status ?: \App\Models\Instructor::EMPLOYMENT_REGULAR }}" disabled>
                </div>
                @endif
            </div>

            <div class="form-row">
                <div>
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control">
                </div>
                <div>
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control">
            </div>

            <div class="panel-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAccountPanel()"><i class="bi bi-x-lg me-1"></i>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
window.CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

async function apiFetch(url, options = {}) {
    const headers = new Headers(options.headers || {});
    if (!headers.has('Accept')) headers.set('Accept', 'application/json');
    if (!headers.has('X-CSRF-TOKEN')) headers.set('X-CSRF-TOKEN', window.CSRF_TOKEN);
    if (!(options.body instanceof FormData) && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
    }
    const response = await fetch(url, { ...options, headers });
    if (!response.ok) {
        const text = await response.text();
        throw new Error(text || 'Request failed');
    }
    return response;
}

document.getElementById('instructorAccountForm')?.addEventListener('submit', async function (event) {
    event.preventDefault();
    const form = event.currentTarget;
    const submitButton = form.querySelector('[type="submit"]');
    const originalLabel = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Saving...';

    try {
        const response = await fetch(form.action, {
            method: form.method,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.CSRF_TOKEN,
            },
            body: new FormData(form),
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            const validationMessages = data.errors ? Object.values(data.errors).flat().join('\n') : null;
            await window.showFeedbackModal({
                title: response.status === 422 ? 'Check Your Entries' : 'Update Failed',
                message: validationMessages || data.message || 'The account could not be updated.',
                tone: 'danger',
            });
            return;
        }

        document.querySelector('.topbar .user-name').textContent = data.new_name;
        document.querySelector('.topbar .user-avatar').textContent = data.new_init;
        form.elements.current_password.value = '';
        form.elements.new_password.value = '';
        form.elements.confirm_password.value = '';
        closeAccountPanel();
        await window.showSuccessModal(data.message || 'Account updated successfully.');
    } catch (error) {
        await window.showFeedbackModal({
            title: 'Update Failed',
            message: 'Unable to update the account right now. Please try again.',
            tone: 'danger',
        });
    } finally {
        submitButton.disabled = false;
        submitButton.innerHTML = originalLabel;
    }
});

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const main = document.getElementById('mainContent');
    const isOpening = sidebar.classList.contains('closed');

    sidebar.classList.toggle('closed');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');

    if (window.innerWidth > 992) {
        main?.classList.toggle('sidebar-open', isOpening);
    } else {
        main?.classList.remove('sidebar-open');
    }
}
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const main = document.getElementById('mainContent');

    sidebar.classList.add('closed');
    sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('show');
    main?.classList.remove('sidebar-open');
}
function closeNotifications() {
    const panel = document.getElementById('notifPanel');
    const overlay = document.getElementById('overlay');
    if (panel) panel.classList.remove('open');
    document.getElementById('notifBtn')?.setAttribute('aria-expanded', 'false');
    if (overlay) overlay.classList.remove('show');
}
function closeAccountPanel() {
    const panel = document.getElementById('accountPanel');
    const overlay = document.getElementById('overlay');
    if (panel) panel.classList.remove('open');
    if (overlay) overlay.classList.remove('show');
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

    if (sidebar) {
        sidebar.classList.add('closed');
        sidebar.classList.remove('open');
    }
    closeNotifications();
    panel.classList.add('open');
    document.getElementById('notifBtn')?.setAttribute('aria-expanded', 'true');
    if (overlay) overlay.classList.add('show');
}
function toggleNotifications() {
    const panel = document.getElementById('notifPanel');
    const overlay = document.getElementById('overlay');
    const sidebar = document.getElementById('sidebar');

    if (panel.classList.contains('open')) {
        closeNotifications();
        return;
    }

    if (sidebar) {
        sidebar.classList.add('closed');
        sidebar.classList.remove('open');
    }
    closeAccountPanel();
    panel.classList.add('open');
    if (overlay) overlay.classList.add('show');
    loadNotifications(true);
}
// ── Notification bell (was ajax_get_notifications / ajax_mark_notif_read) ──
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}
async function loadNotifications() {
    const list = document.getElementById('notifList');
    const badge = document.getElementById('notifBadge');
    try {
        const res = await fetch(`${@json(route('notifications.api'))}?guard=instructor`, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('Unable to load notifications');
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
            const deleteButton = `<button type="button" class="item-delete" onclick="deleteNotification(${notification.id})"><i class="bi bi-trash"></i> Delete</button>`;
            return `<div class="item ${unreadClass}"><div class="item-message">${notification.message}</div><div class="item-meta"><span>${notification.created_at}</span><span class="item-actions">${link}${deleteButton}</span></div></div>`;
        }).join('');
    } catch (e) {
        list.innerHTML = '<div class="empty-state">Unable to load notifications.</div>';
    }
}
async function deleteNotification(notificationId) {
    if (!confirm('Delete this notification?')) return;
    const res = await fetch(`${@json(url('/notifications/api'))}/${notificationId}?guard=instructor`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
    });
    if (res.ok) await loadNotifications();
}
async function markAllNotificationsRead() {
    const res = await fetch(`${@json(route('notifications.api.readAll'))}?guard=instructor`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
    });
    if (res.ok) await loadNotifications();
}
document.addEventListener('DOMContentLoaded', () => {
    loadNotifications();
    document.getElementById('markAllReadBtn')?.addEventListener('click', markAllNotificationsRead);
});
setInterval(loadNotifications, 30000);
</script>
<script src="{{ asset('js/protected-page-history.js') }}"></script>
<script src="{{ asset('js/auth-feedback-modals.js') }}"></script>
@stack('scripts')
</body>
</html>
