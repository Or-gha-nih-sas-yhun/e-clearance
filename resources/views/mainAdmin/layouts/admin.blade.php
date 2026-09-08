<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ClearanceMS')</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Mulish:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="{{ asset('css/admin_dashboard.css') }}" rel="stylesheet">
    <style>
        body.admin-glass-theme,
        body.admin-glass-theme .main {
            background:
                linear-gradient(90deg, rgba(224,243,255,.94) 0%, rgba(219,240,255,.86) 45%, rgba(207,235,255,.64) 100%),
                url("{{ asset('images/mcc-campus.jpg') }}") center / cover fixed no-repeat !important;
        }
        body.admin-glass-theme .main .card,
        body.admin-glass-theme .main .metric-card,
        body.admin-glass-theme .main .chart-card,
        body.admin-glass-theme .main .filter-bar,
        body.admin-glass-theme .main .table-scroll,
        body.admin-glass-theme .main .data-table-wrap,
        body.admin-glass-theme .main .info-card,
        body.admin-glass-theme .main .stat-card,
        body.admin-glass-theme .main .dtable {
            border: 1px solid rgba(255,255,255,.78) !important;
            background: linear-gradient(135deg, rgba(255,255,255,.76), rgba(235,248,255,.55)) !important;
            box-shadow: 0 18px 45px rgba(32,94,145,.16), 0 5px 14px rgba(44,118,174,.09), inset 0 1px 0 rgba(255,255,255,.94) !important;
            backdrop-filter: blur(18px) saturate(145%);
            -webkit-backdrop-filter: blur(18px) saturate(145%);
        }
        body.admin-glass-theme .main .cms-table,
        body.admin-glass-theme .main .cms-table th,
        body.admin-glass-theme .main .cms-table td,
        body.admin-glass-theme .main .table {
            --bs-table-bg: transparent;
            background: transparent !important;
        }
        body.admin-glass-theme .main .card:hover,
        body.admin-glass-theme .main .metric-card:hover,
        body.admin-glass-theme .main .chart-card:hover {
            border-color: rgba(255,255,255,.96) !important;
            box-shadow: 0 22px 52px rgba(32,94,145,.2), 0 7px 17px rgba(44,118,174,.11), inset 0 1px 0 #fff !important;
        }
        .notification-panel { position:fixed; top:72px; right:18px; width:min(400px,calc(100% - 2rem)); max-height:460px; background:#fff; border-radius:12px; box-shadow:0 20px 55px rgba(15,23,42,.22); z-index:10000; overflow:hidden; display:none; }
        .notification-panel.open { display:block; }
        .notification-header { display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid #e5e7eb; }
        .notification-list { max-height:360px; overflow-y:auto; }
        .notification-item { padding:12px 16px; border-bottom:1px solid #eef2f7; }
        .notification-item.unread { background:#eff6ff; }
        .notification-meta { display:flex; justify-content:space-between; gap:10px; color:#64748b; font-size:.78rem; margin-top:6px; }
        .notification-actions { display:flex; align-items:center; gap:10px; }
        .notification-delete { border:0; background:transparent; color:#dc3545; padding:0; cursor:pointer; }
        .notification-empty { padding:24px; text-align:center; color:#64748b; }
        .action-dialog-overlay { position:fixed; inset:0; z-index:100000; display:none; align-items:center; justify-content:center; padding:20px; background:rgba(26,48,76,.46); backdrop-filter:blur(7px); -webkit-backdrop-filter:blur(7px); }
        .action-dialog-overlay.show { display:flex; animation:dialogFade .18s ease-out; }
        .action-dialog { position:relative; isolation:isolate; width:min(430px,100%); overflow:hidden; color:#172033; border:1px solid rgba(255,255,255,.9); border-radius:22px; background:linear-gradient(135deg,rgba(255,255,255,.93),rgba(228,243,255,.82)); box-shadow:0 35px 90px rgba(15,45,75,.34),0 10px 30px rgba(52,126,177,.18),inset 0 1px 1px #fff,inset 0 -1px 0 rgba(112,165,204,.22); backdrop-filter:blur(30px) saturate(165%); -webkit-backdrop-filter:blur(30px) saturate(165%); animation:dialogPop .2s ease-out; }
        .action-dialog::before { content:""; position:absolute; z-index:-1; inset:-45% -20% auto; height:260px; background:radial-gradient(circle at 28% 55%,rgba(255,255,255,.95),transparent 35%),radial-gradient(circle at 70% 35%,rgba(116,205,255,.35),transparent 39%),radial-gradient(circle at 52% 80%,rgba(176,139,255,.2),transparent 42%); filter:blur(8px); pointer-events:none; }
        .action-dialog::after { content:""; position:absolute; z-index:-1; inset:1px; border-radius:21px; background:linear-gradient(115deg,rgba(255,255,255,.48),transparent 28%,transparent 68%,rgba(124,202,255,.14)); pointer-events:none; }
        .action-dialog-body { position:relative; padding:31px 28px 17px; text-align:center; }
        .action-dialog-close { position:absolute; top:17px; right:18px; width:32px; height:32px; border:0; border-radius:8px; color:#64748b; background:transparent; font-size:1.2rem; }
        .action-dialog-close:hover { background:rgba(71,103,135,.1); color:#1e293b; }
        .action-dialog-icon { display:grid; place-items:center; width:64px; height:64px; margin:0 auto 17px; border:3px solid #e52e3d; border-radius:50%; color:#d92232; background:rgba(255,255,255,.72); font-size:2rem; font-weight:800; box-shadow:0 8px 25px rgba(213,35,52,.2),inset 0 0 0 5px rgba(255,77,79,.08); }
        .action-dialog.success .action-dialog-icon { border-color:#27a963; color:#16854a; background:rgba(255,255,255,.74); box-shadow:0 8px 25px rgba(24,143,78,.2),inset 0 0 0 5px rgba(39,174,96,.08); }
        .action-dialog.warning .action-dialog-icon { border-color:#e29a09; color:#b86f00; background:rgba(255,255,255,.74); box-shadow:0 8px 25px rgba(207,132,8,.2),inset 0 0 0 5px rgba(251,191,36,.1); }
        .action-dialog h3 { margin:0 35px 9px; font:800 1.38rem/1.25 Mulish,sans-serif; color:#101828; text-shadow:0 1px 0 rgba(255,255,255,.9); }
        .action-dialog p { margin:0; color:#3f5065; font-size:.95rem; font-weight:600; line-height:1.58; white-space:pre-line; }
        .action-dialog-actions { display:flex; gap:12px; margin:0 20px 20px; padding-top:18px; border-top:1px solid rgba(71,103,135,.24); }
        .action-dialog-btn { flex:1; min-height:45px; border:0; outline:0; border-radius:12px; color:#1e293b; background:rgba(255,255,255,.82); box-shadow:inset 0 1px 1px #fff,0 7px 18px rgba(42,74,105,.14); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); font-weight:800; transition:transform .16s ease,box-shadow .16s ease,filter .16s ease; }
        .action-dialog-btn:hover { transform:translateY(-1px); filter:brightness(1.05); box-shadow:inset 0 1px 1px rgba(255,255,255,.9),0 10px 22px rgba(42,74,105,.14); }
        .action-dialog-btn:focus-visible { box-shadow:0 0 0 4px rgba(59,130,246,.2),0 9px 22px rgba(42,74,105,.13); }
        .action-dialog-btn.primary { color:#fff; background:linear-gradient(135deg,rgba(255,83,93,.94),rgba(204,24,45,.9)); box-shadow:inset 0 1px 1px rgba(255,255,255,.35),0 9px 22px rgba(207,38,55,.25); }
        .action-dialog.success .action-dialog-btn.primary { background:linear-gradient(135deg,rgba(67,211,137,.95),rgba(18,145,78,.9)); box-shadow:inset 0 1px 1px rgba(255,255,255,.35),0 9px 22px rgba(22,138,72,.23); }
        .action-dialog.warning .action-dialog-btn.primary { background:linear-gradient(135deg,rgba(255,190,53,.96),rgba(213,116,8,.9)); box-shadow:inset 0 1px 1px rgba(255,255,255,.38),0 9px 22px rgba(190,107,8,.22); }
        @keyframes dialogFade { from { opacity:0 } to { opacity:1 } }
        @keyframes dialogPop { from { opacity:0; transform:translateY(10px) scale(.97) } to { opacity:1; transform:none } }
        @media (max-width:480px) { .action-dialog-actions { flex-direction:column-reverse; } }
    </style>
    <link href="{{ asset('css/portal_overlays.css') }}" rel="stylesheet">
    <link href="{{ asset('css/student_portal_chrome.css') }}" rel="stylesheet">
    @stack('styles')
    <link href="{{ asset('css/main_admin_portal.css') }}" rel="stylesheet">
</head>
<body class="student-portal-theme portal-chrome-theme admin-glass-theme main-admin-portal">
@php
    $adminDisplayName = session('admin_name') ?? auth('admin')->user()?->name ?? 'Administrator';
    $adminDisplayEmail = session('admin_email') ?? auth('admin')->user()?->email ?? 'Main Admin';
@endphp
<div class="overlay" id="overlay" onclick="closeAdminOverlays()"></div>
@include('partials.action-feedback-modal')

{{-- TOPBAR --}}
<div class="topbar">
    <div class="topbar-left">
        <div class="brand student-brand-lockup">
            <button class="menu-btn" type="button" onclick="toggleSidebar()" aria-label="Open navigation"><i class="bi bi-list"></i></button>
            <img class="student-brand-logo" src="{{ asset('images/mcc-logo.png') }}" alt="MCC logo">
            <span class="portal-brand-copy">
                <span class="portal-brand-name"><span>Clearance</span>MS</span>
                <small>Administrator Portal</small>
            </span>
        </div>
    </div>
    <div class="topbar-right">
        <a class="user-pill admin-account-link" href="{{ route('profile.edit') }}" aria-label="Open account settings">
            <div class="user-avatar">{{ strtoupper(substr($adminDisplayName, 0, 1)) }}</div>
            <div>
                <div class="user-name">{{ $adminDisplayName }}</div>
                <div class="user-role">{{ $adminDisplayEmail }}</div>
            </div>
            <i class="bi bi-chevron-down student-account-chevron" aria-hidden="true"></i>
        </a>
        <button id="notifBtn" class="user-pill" style="position:relative;border:0;cursor:pointer;" type="button" onclick="toggleNotifications()" aria-label="Open notifications" aria-controls="notifPanel" aria-expanded="false">
            <i class="bi bi-bell-fill" style="font-size:18px"></i>
            <span id="notifBadge" style="display:none;position:absolute;top:-6px;right:-6px;background:#f43f5e;color:#fff;border-radius:999px;font-size:10px;padding:1px 5px;"></span>
        </button>
    </div>
</div>

<div class="notification-panel" id="notifPanel" role="dialog" aria-modal="true" aria-label="Notifications">
    <div class="notification-header">
        <div class="panel-heading">
            <span class="panel-icon"><i class="bi bi-bell-fill"></i></span>
            <div class="panel-heading-copy"><div class="notification-title">Notifications</div><div class="subtitle">Latest alerts and updates</div></div>
        </div>
        <div class="panel-actions">
            <button class="panel-text-button" id="markAllReadBtn" type="button"><i class="bi bi-check2-all me-1"></i>Mark all read</button>
            <button class="panel-close" type="button" onclick="closeNotifications()" aria-label="Close notifications"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
    <div class="notifications-list" id="notifList"><div class="empty-state">Loading notifications…</div></div>
</div>

{{-- SIDEBAR --}}
<div class="sidebar closed" id="sidebar">
    <div class="p-3 sidebar-inner">
        <div class="brand mb-4 sidebar-portal-card">
            <span class="sidebar-portal-icon"><i class="bi bi-shield-check"></i></span>
            <div class="sidebar-portal-copy">
                <div class="fs-5 fw-semibold">Administrator Portal</div>
                <div class="small text-secondary">System management</div>
            </div>
            <button class="sidebar-close-button" type="button" onclick="closeAdminOverlays()" aria-label="Close navigation"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="sidebar-nav-links">
            <div class="nav-section">Main</div>
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
            <div class="nav-section">Management</div>
            <a href="{{ route('students.index') }}" class="nav-link {{ request()->routeIs('students*') ? 'active' : '' }}"><i class="bi bi-people"></i> Students</a>
            <a href="{{ route('student-registry.index') }}" class="nav-link {{ request()->routeIs('student-registry*') ? 'active' : '' }}"><i class="bi bi-person-vcard"></i> Registration List</a>
            <a href="{{ route('instructors.index') }}" class="nav-link {{ request()->routeIs('instructors*') ? 'active' : '' }}"><i class="bi bi-person-video3"></i> Instructors</a>
            <a href="{{ route('personnel.index') }}" class="nav-link {{ request()->routeIs('personnel*') ? 'active' : '' }}"><i class="bi bi-person-badge"></i> Admin Personnel</a>
            <a href="{{ route('registrar.index') }}" class="nav-link {{ request()->routeIs('registrar*') ? 'active' : '' }}"><i class="bi bi-building"></i> Registrar</a>
            <a href="{{ route('subjects.index') }}" class="nav-link {{ request()->routeIs('subjects*') ? 'active' : '' }}"><i class="bi bi-journal-bookmark"></i> Subject Codes</a>
            <a href="{{ route('sections.index') }}" class="nav-link {{ request()->routeIs('sections*') ? 'active' : '' }}"><i class="bi bi-grid-3x3-gap"></i> Sections</a>
            <a href="{{ route('assignments.index') }}" class="nav-link {{ request()->routeIs('assignments*') ? 'active' : '' }}"><i class="bi bi-diagram-3"></i> Subject Assignments</a>
            <a href="{{ route('treasurers.index') }}" class="nav-link {{ request()->routeIs('treasurers*') ? 'active' : '' }}"><i class="bi bi-wallet2"></i> Treasurer Accounts</a>
            <div class="nav-section">Monitoring</div>
            <a href="{{ route('activity.index') }}" class="nav-link {{ request()->routeIs('activity*') ? 'active' : '' }}"><i class="bi bi-activity"></i> User Activity</a>
            <div class="nav-section">Communication</div>
            <a href="{{ route('chat.index') }}" class="nav-link {{ request()->routeIs('chat*') ? 'active' : '' }}"><i class="bi bi-chat-square-text"></i> Chat Support</a>
            <a href="{{ route('notifications.index') }}" class="nav-link {{ request()->routeIs('notifications*') ? 'active' : '' }}"><i class="bi bi-bell"></i> Notifications</a>
        </div>

        <div class="sidebar-account-group">
            <div class="nav-section">Account</div>
            <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}"><i class="bi bi-person-gear"></i> Account Settings</a>
            <a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}"><i class="bi bi-sliders"></i> System Settings</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right"></i> Log Out</button></form>
        </div>
    </div>
</div>

{{-- PAGE CONTENT --}}
<main class="main" id="mainContent">
    <div class="admin-content portal-page-container">
        @yield('content')
    </div>
</main>

@include('mainAdmin.partials.csv-import-modal')

<div class="action-dialog-overlay" id="actionDialogOverlay" role="dialog" aria-modal="true" aria-labelledby="actionDialogTitle" aria-describedby="actionDialogMessage">
    <div class="action-dialog" id="actionDialog">
        <div class="action-dialog-body">
            <button type="button" class="action-dialog-close" id="actionDialogClose" aria-label="Close"><i class="bi bi-x-lg"></i></button>
            <div class="action-dialog-icon" id="actionDialogIcon"><i class="bi bi-exclamation-lg"></i></div>
            <h3 id="actionDialogTitle">Confirm Action</h3>
            <p id="actionDialogMessage">Are you sure you want to continue?</p>
        </div>
        <div class="action-dialog-actions">
            <button type="button" class="action-dialog-btn" id="actionDialogCancel"><i class="bi bi-x-lg me-2"></i>Cancel</button>
            <button type="button" class="action-dialog-btn primary" id="actionDialogConfirm"><i class="bi bi-trash3 me-2"></i><span>Yes, Delete</span></button>
        </div>
    </div>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const main = document.getElementById('mainContent');
    const willOpen = !sidebar.classList.contains('open');

    if (willOpen) closeNotifications();
    sidebar.classList.toggle('open', willOpen);
    sidebar.classList.toggle('closed', !willOpen);
    if (overlay) overlay.classList.toggle('show', willOpen);
    main?.classList.remove('sidebar-open');
}
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const main = document.getElementById('mainContent');

    sidebar.classList.remove('open');
    sidebar.classList.add('closed');
    if (overlay) overlay.classList.remove('show');
    main?.classList.remove('sidebar-open');
}

function openCsvModal(type = '') {
    const modal = document.getElementById('csvImportModal');
    const select = document.getElementById('csvImportTypeSelect');
    const hidden = document.getElementById('csvImportType');
    if (!modal || !select || !hidden) return;
    if (type) {
        select.value = type;
        hidden.value = type;
    }
    modal.classList.add('show');
}

function closeCsvModal() {
    const modal = document.getElementById('csvImportModal');
    if (!modal) return;
    modal.classList.remove('show');
}

function setCsvType(value) {
    const hidden = document.getElementById('csvImportType');
    if (hidden) hidden.value = value;
}

function escapeNotificationText(value) {
    const element = document.createElement('div');
    element.textContent = value || '';
    return element.innerHTML;
}
async function loadNotifications() {
    const list = document.getElementById('notifList');
    const badge = document.getElementById('notifBadge');
    try {
        const response = await fetch(`${@json(route('notifications.api'))}?guard=admin`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Unable to load notifications');
        const data = await response.json();
        if (badge) {
            badge.style.display = data.unread > 0 ? 'inline-block' : 'none';
            badge.textContent = data.unread > 99 ? '99+' : data.unread;
        }
        if (!data.notifications?.length) {
            list.innerHTML = '<div class="empty-state">No notifications yet.</div>';
            return;
        }
        list.innerHTML = data.notifications.map(notification => {
            const link = notification.link_url ? `<a class="item-link" href="${escapeNotificationText(notification.link_url)}">View</a>` : '';
            const deleteButton = `<button type="button" class="item-delete" onclick="deleteNotification(${notification.id})"><i class="bi bi-trash"></i> Delete</button>`;
            return `<div class="item ${notification.is_read === 0 ? 'unread' : ''}"><div class="item-message">${escapeNotificationText(notification.message)}</div><div class="item-meta"><span>${escapeNotificationText(notification.created_at)}</span><span class="item-actions">${link}${deleteButton}</span></div></div>`;
        }).join('');
    } catch (error) {
        list.innerHTML = '<div class="empty-state">Unable to load notifications.</div>';
    }
}
async function deleteNotification(notificationId) {
    const confirmed = await showActionDialog({ title: 'Confirm Deletion', message: 'Are you sure you want to delete this notification?\nThis action cannot be undone.', confirmText: 'Yes, Delete' });
    if (!confirmed) return;
    const response = await fetch(`${@json(url('/notifications/api'))}/${notificationId}?guard=admin`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' },
    });
    if (response.ok) loadNotifications();
}
function toggleNotifications() {
    const panel = document.getElementById('notifPanel');
    const overlay = document.getElementById('overlay');
    const willOpen = !panel.classList.contains('open');

    if (willOpen) closeSidebar();
    panel.classList.toggle('open', willOpen);
    document.getElementById('notifBtn')?.setAttribute('aria-expanded', String(willOpen));
    overlay?.classList.toggle('show', willOpen);
    if (willOpen) loadNotifications();
}
function closeNotifications() {
    document.getElementById('notifPanel')?.classList.remove('open');
    document.getElementById('notifBtn')?.setAttribute('aria-expanded', 'false');
    document.getElementById('overlay')?.classList.remove('show');
}
function closeAdminOverlays() {
    closeSidebar();
    closeNotifications();
}
async function markAllNotificationsRead() {
    const response = await fetch(`${@json(route('notifications.api.readAll'))}?guard=admin`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' },
    });
    if (response.ok) loadNotifications();
}
document.addEventListener('DOMContentLoaded', () => {
    loadNotifications();
    document.getElementById('markAllReadBtn')?.addEventListener('click', markAllNotificationsRead);
});
setInterval(loadNotifications, 30000);

function prepareConditionalAccountRules(form) {
    const passwordField = form.querySelector('input[name="password"]');
    const confirmationField = form.querySelector('input[name="password_confirmation"]');
    if (passwordField && confirmationField) {
        const password = passwordField.value;
        const confirmation = confirmationField.value;

        confirmationField.required = password !== '';
        confirmationField.setCustomValidity(
            confirmation !== '' && confirmation !== password ? 'Passwords do not match.' : ''
        );
        updateRequiredMarker(confirmationField);
    }

    const typeField = form.querySelector('[name="treasurer_type"]');
    if (!typeField) return;
    const treasurerType = typeField.value;
    const conditionalFields = {
        department: treasurerType === 'department',
        program: treasurerType === 'section',
        year_level: treasurerType === 'section',
        section: treasurerType === 'section',
    };

    Object.entries(conditionalFields).forEach(([name, required]) => {
        const field = form.querySelector(`[name="${name}"]`);
        if (field && field.closest('.modal-box')) {
            field.required = required;
            updateRequiredMarker(field);
        }
    });
}

function updateRequiredMarker(field) {
    const group = field.closest('.fg');
    const label = group?.querySelector('label');
    if (!label) return;

    label.querySelectorAll('.field-required, .field-optional').forEach(marker => marker.remove());
    Array.from(label.childNodes).forEach(node => {
        if (node.nodeType === Node.TEXT_NODE) {
            node.textContent = node.textContent.replace(/\s*\*+\s*/g, ' ').replace(/\s{2,}/g, ' ');
        }
    });
    const marker = document.createElement('span');
    marker.className = field.required ? 'field-required' : 'field-optional';
    marker.textContent = field.required ? '*' : '(optional)';
    marker.setAttribute('aria-hidden', 'true');
    label.appendChild(marker);
}

function fieldLabel(field) {
    const label = field.closest('.fg')?.querySelector('label');
    if (!label) return 'This field';
    return Array.from(label.childNodes).filter(node => node.nodeType === Node.TEXT_NODE)
        .map(node => node.textContent).join(' ').replace(/\*/g, '').trim() || 'This field';
}

function validationMessageFor(field) {
    const name = fieldLabel(field);
    if (field.validity.customError) return field.validationMessage;
    if (field.validity.valueMissing) return `${name} is required.`;
    if (field.validity.typeMismatch) return `Enter a valid ${name.toLowerCase()}.`;
    if (field.validity.tooShort) return `${name} must contain at least ${field.minLength} characters.`;
    if (field.validity.tooLong) return `${name} must not exceed ${field.maxLength} characters.`;
    if (field.validity.patternMismatch && field.dataset.passwordField === 'true') return 'Use at least 8 characters with uppercase, lowercase, number, and special character.';
    if (field.validity.patternMismatch && field.name === 'student_id') return 'Use the student ID format YYYY-NNNN (e.g. 2023-0387).';
    if (field.validity.patternMismatch && field.name === 'instructor_id') return 'Use a 4-digit employee ID (e.g. 1234).';
    if (field.validity.patternMismatch) return `${name} may contain letters, spaces, apostrophes, and hyphens only.`;
    return `Check the value entered for ${name}.`;
}

function validateModalField(field, showSuccess = true) {
    const error = field.closest('.fg')?.querySelector('.field-error');
    const hasValue = String(field.value || '').trim() !== '';

    field.classList.remove('field-invalid', 'field-valid');
    field.removeAttribute('aria-invalid');
    if (error) {
        error.classList.remove('show');
        error.textContent = '';
    }

    if (!field.checkValidity()) {
        field.classList.add('field-invalid');
        field.setAttribute('aria-invalid', 'true');
        if (error) {
            error.textContent = validationMessageFor(field);
            error.classList.add('show');
        }
        return false;
    }

    if (showSuccess && hasValue) field.classList.add('field-valid');
    return true;
}

function initializePasswordVisibilityToggle(field) {
    if (field.dataset.passwordToggleReady === 'true') return;

    field.dataset.passwordField = 'true';
    field.dataset.passwordToggleReady = 'true';

    const wrapper = document.createElement('div');
    wrapper.className = 'password-input-wrap';
    field.before(wrapper);
    wrapper.appendChild(field);

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'password-visibility-toggle';
    toggle.setAttribute('aria-label', 'Show password');
    toggle.setAttribute('aria-pressed', 'false');
    toggle.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i>';
    wrapper.appendChild(toggle);

    toggle.addEventListener('click', () => {
        const willShow = field.type === 'password';
        field.type = willShow ? 'text' : 'password';
        toggle.setAttribute('aria-label', willShow ? 'Hide password' : 'Show password');
        toggle.setAttribute('aria-pressed', String(willShow));
        toggle.querySelector('i').className = willShow ? 'bi bi-eye-slash' : 'bi bi-eye';
        field.focus({ preventScroll: true });
    });
}

function resetPasswordVisibility(scope) {
    scope.querySelectorAll('.password-visibility-toggle').forEach(toggle => {
        const field = toggle.closest('.password-input-wrap')?.querySelector('[data-password-field="true"]');
        if (!field) return;

        field.type = 'password';
        toggle.setAttribute('aria-label', 'Show password');
        toggle.setAttribute('aria-pressed', 'false');
        toggle.querySelector('i').className = 'bi bi-eye';
    });
}

function initializeAdminModalValidation() {
    document.querySelectorAll('.modal-box form').forEach((form, formIndex) => {
        form.noValidate = true;
        const fields = form.querySelectorAll('.fg input:not([type="hidden"]):not([data-skip-modal-validation]), .fg select, .fg textarea');

        fields.forEach((field, fieldIndex) => {
            const group = field.closest('.fg');
            const label = group?.querySelector('label');
            if (!group || !label) return;

            if (!field.id) field.id = `modal-field-${formIndex}-${fieldIndex}`;
            label.setAttribute('for', field.id);

            if (['firstname', 'lastname'].includes(field.name)) {
                field.minLength = 2;
                field.maxLength = 20;
                field.pattern = "[A-Za-z '-]+";
                if (!field.placeholder) field.placeholder = field.name === 'firstname' ? 'e.g. Juan' : 'e.g. Dela Cruz';
            }
            if (field.name === 'middlename') {
                field.maxLength = 20;
                field.pattern = "[A-Za-z '-]+";
                if (!field.placeholder) field.placeholder = 'e.g. Santos (optional)';
            }
            if (field.type === 'email' && !field.placeholder) field.placeholder = 'e.g. juan.delacruz@example.com';
            if (field.type === 'password') {
                field.minLength = 8;
                field.pattern = '(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^A-Za-z0-9]).{8,}';
                if (!field.placeholder) field.placeholder = field.required ? 'e.g. StrongPass1!' : 'Leave blank to keep current password';
                field.title = 'At least 8 characters with uppercase, lowercase, number, and special character.';
                initializePasswordVisibilityToggle(field);
            }
            if (field.name === 'student_id') {
                field.pattern = '\\d{4}-\\d{4}';
                field.maxLength = 9;
                field.placeholder = 'e.g. 2023-0387';
                field.inputMode = 'numeric';
            }
            if (field.name === 'instructor_id') {
                field.pattern = '\\d{4}';
                field.minLength = 4;
                field.maxLength = 4;
                field.placeholder = 'e.g. 1234';
                field.inputMode = 'numeric';
            }

            let error = group.querySelector('.field-error');
            if (!error) {
                error = document.createElement('div');
                error.className = 'field-error';
                error.id = `${field.id}-error`;
                group.appendChild(error);
            }
            field.setAttribute('aria-describedby', error.id);
            updateRequiredMarker(field);

            field.addEventListener('blur', () => validateModalField(field));
            field.addEventListener('input', () => {
                prepareConditionalAccountRules(form);
                if (field.classList.contains('field-invalid')) validateModalField(field, false);
                if (field.name === 'password') {
                    const confirmation = form.querySelector('input[name="password_confirmation"]');
                    if (confirmation?.classList.contains('field-invalid')) validateModalField(confirmation, false);
                }
            });
            field.addEventListener('change', () => {
                prepareConditionalAccountRules(form);
                validateModalField(field);
            });
        });

        prepareConditionalAccountRules(form);

        form.addEventListener('submit', event => {
            prepareConditionalAccountRules(form);
            let firstInvalid = null;
            fields.forEach(field => {
                if (!validateModalField(field) && !firstInvalid) firstInvalid = field;
            });
            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus();
            }
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(modal => {
        new MutationObserver(() => resetPasswordVisibility(modal))
            .observe(modal, { attributes: true, attributeFilter: ['class'] });
    });
}

document.addEventListener('DOMContentLoaded', initializeAdminModalValidation);

function initializeAdminFilters() {
    const labels = {
        filter: 'Student Type', year_level: 'Year Level', year: 'Year Level',
        department: 'Department', program: 'Program', section: 'Section',
        semester: 'Semester', role: 'Role', type: 'Type', limit: 'Rows', order: 'Sort By',
    };

    document.querySelectorAll('.filter-bar form').forEach((form, formIndex) => {
        const row = form.querySelector('.row');
        if (!row) return;

        form.querySelectorAll('input, select').forEach((field, fieldIndex) => {
            const container = field.closest('[class*="col-"]');
            if (!container) return;
            container.classList.add('filter-field');

            if (!field.id) field.id = `admin-filter-${formIndex}-${fieldIndex}`;
            if (field.name === 'search') {
                container.classList.add('filter-search-field');
                field.placeholder = field.placeholder || 'Search by name or ID number...';
                if (!container.querySelector('.filter-search-icon')) {
                    container.insertAdjacentHTML('afterbegin', '<i class="bi bi-search filter-search-icon" aria-hidden="true"></i>');
                }
            } else {
                const label = document.createElement('label');
                label.className = 'filter-label';
                label.htmlFor = field.id;
                label.textContent = labels[field.name] || field.name.replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase());
                container.insertBefore(label, field);
            }

            if (field.name === 'order') container.classList.add('filter-sort-field');
        });

        const submitContainer = form.querySelector('button[type="submit"]')?.closest('[class*="col-"]');
        if (submitContainer) {
            submitContainer.classList.add('filter-action-field');
            const submitButton = submitContainer.querySelector('button[type="submit"]');
            submitButton.innerHTML = '<i class="bi bi-funnel"></i><span>Apply</span>';

            const resetContainer = document.createElement('div');
            resetContainer.className = 'col-auto filter-reset-field';
            const resetLink = document.createElement('a');
            resetLink.className = 'filter-reset';
            resetLink.href = form.action;
            resetLink.innerHTML = '<i class="bi bi-arrow-clockwise"></i><span>Reset</span>';
            resetContainer.appendChild(resetLink);
            row.insertBefore(resetContainer, submitContainer);
        }
    });
}

document.addEventListener('DOMContentLoaded', initializeAdminFilters);

let actionDialogResolver = null;
function closeActionDialog(result = false) {
    const overlay = document.getElementById('actionDialogOverlay');
    overlay.classList.remove('show');
    document.body.style.overflow = overlay.dataset.previousOverflow || '';
    if (actionDialogResolver) {
        const resolve = actionDialogResolver;
        actionDialogResolver = null;
        resolve(result);
    }
}

function showActionDialog(options = {}) {
    const overlay = document.getElementById('actionDialogOverlay');
    const dialog = document.getElementById('actionDialog');
    const icon = document.getElementById('actionDialogIcon');
    const confirmButton = document.getElementById('actionDialogConfirm');
    const cancelButton = document.getElementById('actionDialogCancel');
    const tone = options.tone || 'danger';
    dialog.className = `action-dialog ${tone}`;
    document.getElementById('actionDialogTitle').textContent = options.title || 'Confirm Action';
    document.getElementById('actionDialogMessage').textContent = options.message || 'Are you sure you want to continue?';
    confirmButton.querySelector('span').textContent = options.confirmText || 'Yes, Continue';
    confirmButton.querySelector('i').className = `bi ${tone === 'danger' ? 'bi-trash3' : 'bi-check-lg'} me-2`;
    icon.innerHTML = `<i class="bi ${tone === 'success' ? 'bi-check-lg' : 'bi-exclamation-lg'}"></i>`;
    cancelButton.style.display = options.notice ? 'none' : '';
    overlay.dataset.previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    overlay.classList.add('show');
    setTimeout(() => confirmButton.focus(), 0);
    return new Promise(resolve => { actionDialogResolver = resolve; });
}

document.getElementById('actionDialogConfirm').addEventListener('click', () => closeActionDialog(true));
document.getElementById('actionDialogCancel').addEventListener('click', () => closeActionDialog(false));
document.getElementById('actionDialogClose').addEventListener('click', () => closeActionDialog(false));
document.getElementById('actionDialogOverlay').addEventListener('click', event => {
    if (event.target === event.currentTarget) closeActionDialog(false);
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && document.getElementById('actionDialogOverlay').classList.contains('show')) closeActionDialog(false);
});
document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === 'true') return;
    event.preventDefault();
    const confirmed = await showActionDialog({
        title: form.dataset.confirmTitle || 'Confirm Action', message: form.dataset.confirm,
        confirmText: form.dataset.confirmButton || 'Yes, Continue', tone: form.dataset.confirmTone || 'danger',
    });
    if (confirmed) { form.dataset.confirmed = 'true'; form.requestSubmit(); }
}, true);

@if($errors->any())
document.addEventListener('DOMContentLoaded', () => {
    const validationErrors = @json($errors->all());
    const duplicateFound = validationErrors.some(message => message.toLowerCase().includes('already in use'));
    showActionDialog({
        title: duplicateFound ? 'Account Already Exists' : 'Check Your Entries',
        message: validationErrors.map(message => `• ${message}`).join('\n'),
        confirmText: 'Okay, Got it!',
        tone: 'danger',
        notice: true,
    });
});
@endif

// Close add/edit overlay when clicking on the backdrop.
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.classList.remove('show');
    }
});
</script>
<script src="{{ asset('js/protected-page-history.js') }}"></script>
<script src="{{ asset('js/auth-feedback-modals.js') }}"></script>
@stack('scripts')
</body>
</html>
