@extends('layouts.portal')
@section('theme-body-class', 'student-portal-theme')

@section('title', 'Clearance Updates')
@section('portal-name', 'Student Portal')
@section('portal-subtitle', $student->student_id)
@section('page-title', 'Clearance Updates')
@section('user-label', $student->full_name . ' · ' . $student->program . ' ' . $student->year_level . '-' . $student->section)
@section('user-role', 'Student')

@section('nav')
    <a class="nav-link" href="{{ route('student.dashboard') }}"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    @if(\App\Support\StudentSubjects::isIrregular($student))
    <a class="nav-link" href="{{ route('student.subjects.index') }}"><i class="bi bi-journal-bookmark"></i> My Subjects</a>
    @endif
    <a class="nav-link active" href="{{ route('student.clearance-updates') }}"><i class="bi bi-clipboard2-check"></i> Clearance Updates</a>
    <a class="nav-link" href="{{ route('student.submission-remark') }}"><i class="bi bi-file-earmark-arrow-up"></i> Submission & Remark</a>
    <a class="nav-link" href="{{ route('student.chat-support') }}"><i class="bi bi-chat-square-text"></i> Chat Support</a>
@endsection

@section('logout-form')
    <form method="POST" action="{{ route('student.logout') }}">
        @csrf
        <button type="submit" class="sidebar-action text-dark">
            <i class="bi bi-box-arrow-right me-2"></i> Log Out
        </button>
    </form>
@endsection

@push('styles')
<style>
    .clearance-progress-card { overflow:hidden; border-radius:18px !important; }
    .clearance-progress-card > .card-header { display:flex; align-items:center; gap:10px; min-height:56px; padding:14px 18px; color:#17304f; }
    .clearance-progress-card > .card-body { padding:18px; }
    .clearance-summary-tile { position:relative; overflow:hidden; min-height:112px; padding:16px; border:1px solid rgba(181,211,231,.58); border-radius:15px; background:linear-gradient(135deg,rgba(255,255,255,.65),rgba(226,244,255,.38)); box-shadow:inset 0 1px 1px rgba(255,255,255,.9); }
    .clearance-summary-title { display:flex; align-items:center; gap:9px; color:#243a55; font-weight:800; }
    .clearance-summary-title i { display:grid; width:34px; height:34px; place-items:center; border-radius:10px; background:rgba(220,241,255,.82); }
    .clearance-summary-copy { margin:8px 0 10px; color:#6a7e94; font-size:.75rem; }
    .clearance-summary-tile .progress { height:8px !important; overflow:hidden; border-radius:999px; background:rgba(173,205,227,.28); }
    .clearance-summary-tile .progress-bar { border-radius:inherit; }
    .clearance-form-panel { position:relative; isolation:isolate; display:flex; align-items:center; gap:14px; margin-top:16px; padding:15px 16px; overflow:hidden; border:1px solid rgba(112,202,160,.48); border-radius:16px; background:radial-gradient(circle at 10% 0,rgba(255,255,255,.96),transparent 38%),linear-gradient(135deg,rgba(233,255,244,.82),rgba(219,244,255,.58)); box-shadow:0 12px 28px rgba(36,120,99,.1),inset 0 1px 1px #fff; }
    .clearance-form-panel::after { content:""; position:absolute; z-index:-1; top:-58px; right:8%; width:190px; height:130px; border-radius:50%; background:radial-gradient(circle,rgba(75,213,146,.2),transparent 68%); filter:blur(5px); }
    .clearance-form-panel.is-locked { border-color:rgba(184,207,225,.62); background:radial-gradient(circle at 10% 0,rgba(255,255,255,.94),transparent 38%),linear-gradient(135deg,rgba(242,248,252,.86),rgba(226,239,248,.6)); box-shadow:inset 0 1px 1px #fff; }
    .clearance-form-panel.is-locked::after { background:radial-gradient(circle,rgba(123,174,210,.16),transparent 68%); }
    .clearance-form-icon { display:grid; width:48px; height:48px; flex:0 0 auto; place-items:center; border-radius:14px; color:#128251; background:rgba(211,249,229,.9); box-shadow:inset 0 1px 1px #fff; font-size:1.18rem; }
    .clearance-form-panel.is-locked .clearance-form-icon { color:#637d94; background:rgba(224,237,246,.9); }
    .clearance-form-copy { min-width:0; flex:1; }
    .clearance-form-copy strong { display:block; color:#18364a; font-size:.9rem; font-weight:850; }
    .clearance-form-copy span { display:block; margin-top:3px; color:#688096; font-size:.72rem; line-height:1.45; }
    .clearance-form-actions { display:flex; flex:0 0 auto; align-items:center; gap:8px; }
    .clearance-form-action { display:inline-flex; min-height:42px; flex:0 0 auto; align-items:center; justify-content:center; gap:8px; padding:10px 15px; border:0; outline:0; border-radius:12px; color:#fff; background:linear-gradient(135deg,#24b879,#118d5b); box-shadow:0 9px 21px rgba(19,145,91,.22),inset 0 1px 1px rgba(255,255,255,.32); font-size:.76rem; font-weight:850; text-decoration:none; transition:transform .18s ease,box-shadow .18s ease; }
    .clearance-form-action:hover { color:#fff; transform:translateY(-2px); box-shadow:0 12px 25px rgba(19,145,91,.28),inset 0 1px 1px rgba(255,255,255,.32); }
    .clearance-form-action:focus-visible { outline:0; box-shadow:0 0 0 4px rgba(37,184,121,.18),0 12px 25px rgba(19,145,91,.25); }
    .clearance-form-action.is-download { background:linear-gradient(135deg,#269ee1,#176fd1); box-shadow:0 9px 21px rgba(23,111,209,.2),inset 0 1px 1px rgba(255,255,255,.3); }
    .clearance-form-waiting { display:inline-flex; min-height:38px; flex:0 0 auto; align-items:center; gap:7px; padding:9px 12px; border-radius:11px; color:#647b91; background:rgba(255,255,255,.58); font-size:.69rem; font-weight:800; white-space:nowrap; }
    .clearance-document-overlay { position:fixed; z-index:91000; inset:79px 0 0; display:grid; place-items:center; padding:20px; visibility:hidden; opacity:0; background:rgba(27,56,84,.38); backdrop-filter:blur(12px) saturate(125%); -webkit-backdrop-filter:blur(12px) saturate(125%); transition:opacity .22s ease,visibility .22s ease; }
    .clearance-document-overlay.show { visibility:visible; opacity:1; }
    .clearance-document-backdrop { position:absolute; inset:0; width:100%; height:100%; padding:0; border:0; outline:0; background:transparent; }
    .clearance-document-modal { position:relative; isolation:isolate; display:flex; width:min(1040px,100%); height:min(790px,calc(100vh - 119px)); overflow:hidden; flex-direction:column; border:1px solid rgba(255,255,255,.94); border-radius:23px; background:linear-gradient(135deg,rgba(255,255,255,.95),rgba(224,242,254,.88)); box-shadow:0 35px 90px rgba(15,45,75,.32),0 10px 30px rgba(52,126,177,.16),inset 0 1px 1px #fff; backdrop-filter:blur(30px) saturate(165%); -webkit-backdrop-filter:blur(30px) saturate(165%); transform:translateY(14px) scale(.98); transition:transform .24s cubic-bezier(.22,1,.36,1); }
    .clearance-document-overlay.show .clearance-document-modal { transform:none; }
    .clearance-document-header { display:flex; min-height:66px; align-items:center; justify-content:space-between; gap:15px; padding:12px 15px 12px 18px; border-bottom:1px solid rgba(166,198,222,.55); background:rgba(255,255,255,.48); }
    .clearance-document-title { display:flex; min-width:0; align-items:center; gap:11px; }
    .clearance-document-title > i { display:grid; width:40px; height:40px; flex:0 0 auto; place-items:center; border-radius:12px; color:#137c52; background:rgba(213,249,230,.9); font-size:1.05rem; }
    .clearance-document-title h3 { margin:0 0 2px; color:#172b43; font-size:.98rem; font-weight:850; }
    .clearance-document-title p { margin:0; color:#6a7e94; font-size:.68rem; }
    .clearance-document-controls { display:flex; align-items:center; gap:8px; }
    .clearance-document-download,.clearance-document-print,.clearance-document-close { display:inline-grid; min-height:38px; place-items:center; border:0; outline:0; border-radius:10px; cursor:pointer; }
    .clearance-document-download,.clearance-document-print { grid-auto-flow:column; gap:7px; padding:8px 12px; color:#fff; font-size:.72rem; font-weight:800; text-decoration:none; }
    .clearance-document-print { grid-auto-flow:column; gap:7px; padding:8px 12px; color:#fff; background:linear-gradient(135deg,#239be0,#176fd1); box-shadow:0 8px 18px rgba(23,111,209,.2); font-size:.72rem; font-weight:800; }
    .clearance-document-download { background:linear-gradient(135deg,#24b879,#118d5b); box-shadow:0 8px 18px rgba(19,145,91,.2); }
    .clearance-document-download:hover { color:#fff; }
    .clearance-document-close { width:38px; padding:0; color:#5d7288; background:rgba(255,255,255,.72); box-shadow:inset 0 1px 1px #fff; }
    .clearance-document-download:focus-visible,.clearance-document-print:focus-visible,.clearance-document-close:focus-visible { outline:0; box-shadow:0 0 0 4px rgba(32,143,218,.16); }
    .clearance-document-frame-wrap { position:relative; min-height:0; flex:1; padding:12px; }
    .clearance-document-frame { display:block; width:100%; height:100%; border:1px solid rgba(174,202,222,.7); border-radius:14px; background:#edf3f7; }
    .clearance-document-loader { position:absolute; inset:12px; display:grid; place-items:center; border-radius:14px; color:#648096; background:linear-gradient(135deg,#f8fcff,#e7f3fb); font-size:.76rem; font-weight:750; text-align:center; transition:opacity .18s ease,visibility .18s ease; }
    .clearance-document-loader i { display:block; margin:0 auto 8px; color:#168dcc; font-size:1.4rem; }
    .clearance-document-frame-wrap.loaded .clearance-document-loader { visibility:hidden; opacity:0; }
    body.clearance-form-viewer-open .main { overflow:hidden; }
    .clearance-icon { display:inline-grid; width:34px; height:34px; flex:0 0 auto; place-items:center; color:#0d6efd; border-radius:10px; background:rgba(13,110,253,.1); font-size:1rem; }
    .office-name { display:flex; align-items:center; gap:.65rem; }
    .office-card-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:14px; padding:18px; }
    .office-clearance-card { position:relative; isolation:isolate; display:flex; min-height:176px; flex-direction:column; gap:14px; overflow:hidden; padding:17px; border:1px solid rgba(255,255,255,.9); border-radius:17px; color:#22334a; background:radial-gradient(circle at 8% 0,rgba(255,255,255,.98),transparent 38%),linear-gradient(135deg,rgba(255,255,255,.76),rgba(220,240,255,.48)); box-shadow:0 14px 34px rgba(32,94,145,.12),inset 0 1px 1px #fff; text-align:left; backdrop-filter:blur(22px) saturate(160%); -webkit-backdrop-filter:blur(22px) saturate(160%); cursor:pointer; transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease; }
    .office-clearance-card::before { content:""; position:absolute; z-index:-1; top:-55px; right:-40px; width:145px; height:125px; border-radius:50%; background:radial-gradient(circle,rgba(93,193,255,.3),rgba(166,132,255,.1) 48%,transparent 72%); filter:blur(5px); }
    .office-clearance-card:hover { transform:translateY(-3px); border-color:rgba(124,196,242,.82); box-shadow:0 20px 44px rgba(32,94,145,.18),inset 0 1px 1px #fff; }
    .office-clearance-card:focus-visible { outline:0; box-shadow:0 0 0 4px rgba(13,110,253,.2),0 20px 44px rgba(32,94,145,.18); }
    .office-card-head,.office-card-foot { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .office-card-identity { display:flex; flex:1 1 auto; align-items:center; gap:11px; min-width:0; overflow:hidden; }
    .office-card-identity > span:last-child { min-width:0; }
    .office-card-identity .clearance-icon { width:42px; height:42px; border-radius:13px; background:rgba(222,241,255,.82); box-shadow:inset 0 1px 1px #fff; font-size:1.08rem; }
    .office-card-identity strong { display:block; overflow:hidden; color:#17253a; font-size:.93rem; text-overflow:ellipsis; white-space:nowrap; }
    .office-card-identity small,.office-card-foot span { color:#64748b; font-size:.72rem; }
    .office-card-meta { display:flex; align-items:center; justify-content:space-between; gap:10px; min-width:0; }
    .office-card-approver { display:flex; flex:1 1 auto; min-width:0; align-items:center; gap:8px; overflow:hidden; }
    .office-card-approver > i { display:grid; flex:0 0 auto; width:28px; height:28px; place-items:center; border-radius:9px; color:#1978ad; background:rgba(220,241,255,.82); }
    .office-card-approver > span { min-width:0; }
    .office-card-approver small { display:block; color:#718096; font-size:.62rem; }
    .office-card-approver strong { display:block; overflow:hidden; color:#33475e; font-size:.72rem; text-overflow:ellipsis; white-space:nowrap; }
    .office-card-status,.office-detail-status { display:inline-flex; align-items:center; justify-content:center; min-width:74px; padding:6px 9px; border-radius:999px; font-size:.67rem; font-weight:800; white-space:nowrap; }
    .office-card-status { flex:0 0 auto; }
    .office-card-status.success,.office-detail-status.success { color:#16854a; background:rgba(211,250,226,.9); }
    .office-card-status.warning,.office-detail-status.warning { color:#a86400; background:rgba(255,240,203,.9); }
    .office-card-status.danger,.office-detail-status.danger { color:#c72d3e; background:rgba(255,222,226,.9); }
    .office-card-status.neutral,.office-detail-status.neutral { color:#53657a; background:rgba(231,238,245,.9); }
    .office-card-foot { margin-top:auto; padding-top:13px; border-top:1px solid rgba(173,202,224,.45); }
    .office-card-foot i { color:#168dcc; transition:transform .2s ease; }
    .office-clearance-card:hover .office-card-foot i { transform:translateX(3px); }
    .office-detail-overlay { position:fixed; z-index:90000; inset:79px 0 0; display:grid; place-items:center; padding:20px; visibility:hidden; opacity:0; background:rgba(31,61,91,.38); backdrop-filter:blur(12px) saturate(125%); -webkit-backdrop-filter:blur(12px) saturate(125%); transition:opacity .2s ease,visibility .2s ease; }
    .office-detail-overlay.show { visibility:visible; opacity:1; }
    .office-detail-backdrop { position:absolute; inset:0; width:100%; height:100%; padding:0; border:0; background:transparent; cursor:default; }
    .office-detail-modal { position:relative; isolation:isolate; width:min(680px,100%); max-height:calc(100vh - 119px); overflow:hidden; border:1px solid rgba(255,255,255,.94); border-radius:23px; color:#1e2e43; background:radial-gradient(circle at 10% 0,rgba(255,255,255,1),transparent 38%),linear-gradient(135deg,rgba(255,255,255,.94),rgba(222,241,254,.86)); box-shadow:0 35px 90px rgba(15,45,75,.3),0 10px 30px rgba(52,126,177,.16),inset 0 1px 1px #fff; backdrop-filter:blur(32px) saturate(170%); -webkit-backdrop-filter:blur(32px) saturate(170%); transform:translateY(14px) scale(.98); transition:transform .24s cubic-bezier(.22,1,.36,1); }
    .office-detail-overlay.show .office-detail-modal { transform:none; }
    .office-detail-header { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:18px 20px; border-bottom:1px solid rgba(166,198,222,.52); background:rgba(255,255,255,.38); }
    .office-detail-heading { display:flex; align-items:center; gap:12px; min-width:0; }
    .office-detail-heading .clearance-icon { width:45px; height:45px; border-radius:14px; font-size:1.15rem; }
    .office-detail-heading h3 { margin:0 0 3px; color:#132238; font-size:1.08rem; font-weight:800; }
    .office-detail-heading p { margin:0; color:#67788d; font-size:.73rem; }
    .office-detail-close { display:grid; flex:0 0 auto; width:35px; height:35px; padding:0; place-items:center; border:0; border-radius:10px; color:#53657a; background:rgba(255,255,255,.62); box-shadow:inset 0 1px 1px #fff; cursor:pointer; }
    .office-detail-body { display:grid; gap:14px; max-height:calc(100vh - 205px); overflow-y:auto; padding:18px 20px 20px; }
    .office-detail-summary { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
    .office-detail-field { padding:12px 13px; border:1px solid rgba(184,207,225,.65); border-radius:12px; background:rgba(255,255,255,.5); box-shadow:inset 0 1px 1px rgba(255,255,255,.9); }
    .office-detail-field > span { display:block; margin-bottom:6px; color:#718096; font-size:.67rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
    .office-detail-field strong,.office-detail-field p { margin:0; color:#293b52; font-size:.82rem; line-height:1.5; overflow-wrap:anywhere; }
    .office-detail-field.full { grid-column:1/-1; }
    .office-detail-requirements { display:flex; flex-wrap:wrap; gap:7px; }
    .office-detail-requirements span { padding:6px 9px; border-radius:8px; color:#35607f; background:rgba(222,241,254,.78); font-size:.7rem; font-weight:700; }
    .office-submission-file { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .office-submission-copy { min-width:0; }
    .office-submission-copy strong { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .office-submission-copy small { display:block; margin-top:3px; color:#718096; font-size:.69rem; }
    .office-file-link { display:inline-flex; flex:0 0 auto; align-items:center; gap:6px; padding:8px 10px; border:0; border-radius:9px; color:#176da0; background:rgba(224,243,255,.9); font-size:.7rem; font-weight:800; text-decoration:none; }
    .office-detail-actions { display:grid; gap:10px; padding-top:2px; }
    .office-detail-actions h4 { margin:0; color:#26384e; font-size:.8rem; font-weight:800; }
    .office-request-form button,.office-upload-form button { border:0; border-radius:10px; }
    .office-upload-form { display:grid; gap:8px; padding:13px; border:1px solid rgba(184,207,225,.64); border-radius:13px; background:rgba(255,255,255,.46); }
    .office-upload-form .form-control { border-color:rgba(174,201,221,.75); border-radius:9px; background:rgba(255,255,255,.75); font-size:.76rem; }
    .office-detail-locked { display:flex; align-items:center; gap:8px; padding:11px 12px; border-radius:11px; color:#697a8e; background:rgba(232,239,245,.7); font-size:.75rem; }
    body.office-detail-open .main { overflow:hidden; }
    @media(max-width:600px){.clearance-progress-card>.card-body{padding:13px}.clearance-form-panel{align-items:flex-start;flex-wrap:wrap;padding:14px}.clearance-form-copy{width:calc(100% - 62px);flex:1 1 calc(100% - 62px)}.clearance-form-actions{display:grid;width:100%;grid-template-columns:1fr 1fr}.clearance-form-action,.clearance-form-waiting{width:100%}.clearance-document-overlay{inset:67px 0 0;padding:10px}.clearance-document-modal{height:calc(100vh - 87px);border-radius:17px}.clearance-document-header{min-height:62px;padding:10px 11px}.clearance-document-title>i{width:36px;height:36px}.clearance-document-title p{display:none}.clearance-document-download,.clearance-document-print{width:38px;padding:0;font-size:0}.clearance-document-download i,.clearance-document-print i{font-size:.9rem}.clearance-document-frame-wrap{padding:8px}.clearance-document-loader{inset:8px}.office-card-grid{grid-template-columns:1fr;padding:12px}.office-card-meta{align-items:flex-start;flex-direction:column}.office-card-status{align-self:flex-start}.office-detail-overlay{inset:67px 0 0;padding:12px}.office-detail-modal{max-height:calc(100vh - 91px);border-radius:18px}.office-detail-header{padding:14px}.office-detail-body{max-height:calc(100vh - 170px);padding:14px}.office-detail-summary{grid-template-columns:1fr}.office-detail-field.full{grid-column:auto}.office-submission-file{align-items:flex-start;flex-direction:column}}
    @media(prefers-reduced-motion:reduce){.office-clearance-card,.office-card-foot i,.office-detail-overlay,.office-detail-modal{transition:none}}
</style>
<link href="{{ asset('css/student_clearance_updates.css') }}" rel="stylesheet">
@endpush

@section('content')
@php
    $officeIcons = [
        'section treasurer' => 'bi-cash-stack', 'department treasurer' => 'bi-bank',
        'property custodian' => 'bi-box-seam', 'scc adviser' => 'bi-people',
        'sas director' => 'bi-person-badge', 'guidance office' => 'bi-heart',
        'library' => 'bi-book', 'dean' => 'bi-mortarboard', 'registrar' => 'bi-file-earmark-check',
    ];
    $subjectsTotal = (int) $summary['subjectsTotal'];
    $subjectsApproved = (int) $summary['subjectsApproved'];
    $officeTotal = (int) $summary['officeTotal'];
    $officeApproved = (int) $summary['officeApproved'];
    $requiredTotal = $subjectsTotal + $officeTotal;
    $approvedTotal = $subjectsApproved + $officeApproved;
    $remainingTotal = max(0, $requiredTotal - $approvedTotal);
    $subjectRate = $subjectsTotal ? (int) round(($subjectsApproved / $subjectsTotal) * 100) : 0;
    $officeRate = $officeTotal ? (int) round(($officeApproved / $officeTotal) * 100) : 0;
    $overallRate = $requiredTotal ? (int) round(($approvedTotal / $requiredTotal) * 100) : 0;
    $nextStepTitle = $registrarApproved
        ? 'Your clearance is complete'
        : ($subjectsApproved < $subjectsTotal ? 'Complete instructor clearances' : 'Continue office clearances');
    $nextStepCopy = $registrarApproved
        ? 'Your official clearance form is ready to view, download, or print.'
        : ($subjectsApproved < $subjectsTotal
            ? 'Request the remaining subject approvals and review any returned remarks.'
            : 'Open the remaining office cards to review prerequisites and submit requests.');
@endphp

<div class="student-clearance-workspace">
    @if(isset($errors) && $errors->any())
        <div class="student-clearance-alert" role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><div><strong>Some information needs attention.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif

    <section class="student-clearance-statusbar {{ $registrarApproved ? 'is-complete' : '' }}" aria-labelledby="clearanceStatusTitle">
        <div class="student-status-summary">
            <span class="student-status-summary__icon"><i class="bi {{ $registrarApproved ? 'bi-patch-check-fill' : 'bi-hourglass-split' }}"></i></span>
            <div><small>Clearance status</small><h2 id="clearanceStatusTitle">{{ $registrarApproved ? 'Clearance complete' : 'Clearance in progress' }}</h2><p>{{ $approvedTotal }} of {{ $requiredTotal }} required approvals completed</p></div>
        </div>
        <div class="student-status-progress">
            <div class="student-status-progress__heading"><span>Overall progress</span><strong>{{ $overallRate }}%</strong></div>
            <div class="student-status-track" role="progressbar" aria-valuenow="{{ $overallRate }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $overallRate }}%"></span></div>
            <div class="student-status-breakdown"><span><i class="bi bi-person-video3"></i>{{ $subjectsApproved }}/{{ $subjectsTotal }} instructors</span><span><i class="bi bi-buildings"></i>{{ $officeApproved }}/{{ $officeTotal }} offices</span><span><i class="bi bi-hourglass"></i>{{ $remainingTotal }} remaining</span></div>
        </div>
        <div class="student-status-next"><small>{{ $registrarApproved ? 'Completed' : 'Next step' }}</small><strong>{{ $nextStepTitle }}</strong><p>{{ $nextStepCopy }}</p></div>
    </section>

    @if($registrarApproved)
        <section class="clearance-form-panel"><span class="clearance-form-icon"><i class="bi bi-file-earmark-check"></i></span><div class="clearance-form-copy"><strong>Your official clearance form is ready</strong><span>The Registrar completed the final approval. Open the document to review, download, or print your clearance.</span></div><div class="clearance-form-actions"><button class="clearance-form-action" type="button" data-clearance-form-open data-clearance-form-src="{{ route('student.clearance.form', ['embed' => 1]) }}"><i class="bi bi-eye"></i>View Form</button><a class="clearance-form-action is-download" href="{{ route('student.clearance.form.download') }}"><i class="bi bi-download"></i>Download PDF</a></div></section>
    @else
        <section class="clearance-form-panel is-locked"><span class="clearance-form-icon"><i class="bi bi-file-earmark-lock"></i></span><div class="clearance-form-copy"><strong>Official clearance form not yet available</strong><span>Complete the remaining steps and wait for the Registrar's final approval to unlock your printable form.</span></div><span class="clearance-form-waiting"><i class="bi bi-lock"></i>Awaiting Registrar</span></section>
    @endif

    <section class="student-clearance-section" aria-labelledby="instructorClearanceTitle">
        <header class="student-section-heading">
            <div class="student-section-title"><span><i class="bi bi-journal-check"></i></span><div><small>Subject approvals</small><h2 id="instructorClearanceTitle">Instructor Clearances</h2><p>Request approval and review feedback for each assigned subject.</p></div></div>
            <span class="student-section-count"><strong>{{ $subjectsApproved }}</strong>/{{ $subjectsTotal }} approved</span>
        </header>

        <div class="student-instructor-grid">
            @forelse ($instructorItems as $item)
                @php
                    $itemTone = match(strtolower($item['status'])) {
                        'approved', 'cleared' => 'success',
                        'rejected', 'disapproved' => 'danger',
                        'pending' => 'warning',
                        default => 'neutral',
                    };
                @endphp
                <article class="student-instructor-card is-{{ $itemTone }}">
                    <header class="student-instructor-head">
                        <div class="student-subject-identity"><span><i class="bi bi-book"></i></span><div><strong>{{ $item['subject_code'] }}</strong><p>{{ $item['subject_description'] ?: 'Assigned subject' }}</p></div></div>
                        <span class="student-status-pill {{ $itemTone }}">{{ $item['status'] }}</span>
                    </header>
                    <div class="student-instructor-meta">
                        <div><span><i class="bi bi-person-video3"></i>Instructor</span><strong>{{ $item['instructor_name'] ?: 'Not assigned' }}</strong></div>
                        <div><span><i class="bi bi-clock-history"></i>Last activity</span><strong>{{ $item['updated_at'] ? \Illuminate\Support\Carbon::parse($item['updated_at'])->diffForHumans() : 'No activity yet' }}</strong></div>
                    </div>
                    <div class="student-instructor-remark {{ $itemTone === 'danger' ? 'needs-attention' : '' }}"><span><i class="bi bi-chat-left-text"></i>Reviewer remark</span><p>{{ $item['remarks'] ?: 'No remark has been provided yet.' }}</p></div>
                    <footer class="student-instructor-actions">
                        @if($item['is_approved'])
                            <span class="student-clearance-action is-complete"><i class="bi bi-check2-circle"></i>Clearance approved</span>
                        @elseif($item['can_submit'])
                            <form method="POST" action="{{ route('student.clearance.submit-instructor') }}">
                                @csrf
                                <input type="hidden" name="subject_id" value="{{ $item['subject_id'] }}">
                                <input type="hidden" name="instructor_id" value="{{ $item['instructor_id'] }}">
                                <button type="submit" class="student-clearance-action is-primary"><i class="bi bi-send-check"></i>{{ $item['status'] === 'Rejected' ? 'Request Again' : 'Request Clearance' }}</button>
                            </form>
                        @else
                            <span class="student-clearance-action is-waiting"><i class="bi bi-hourglass-split"></i>Request submitted</span>
                        @endif
                    </footer>
                </article>
            @empty
                <div class="student-clearance-empty"><i class="bi bi-journal-x"></i><h3>No instructor assignments yet</h3><p>Your assigned subjects will appear here when class assignments are available.</p></div>
            @endforelse
        </div>
    </section>

    <section class="student-clearance-section" aria-labelledby="officeClearanceTitle">
        <header class="student-section-heading">
            <div class="student-section-title"><span><i class="bi bi-buildings"></i></span><div><small>Campus approvals</small><h2 id="officeClearanceTitle">Office Clearance Checklist</h2><p>Open a card to view prerequisites, remarks, documents, and available actions.</p></div></div>
            <span class="student-section-count"><strong>{{ $officeApproved }}</strong>/{{ $officeTotal }} approved</span>
        </header>
        <div class="office-card-grid">
            @forelse ($officeItems as $office)
                @php
                    $officeTone = match($office['status']) {
                        'Approved' => 'success',
                        'Rejected' => 'danger',
                        'Pending' => 'warning',
                        default => 'neutral',
                    };
                    $officeModalId = 'office-detail-' . $loop->index;
                @endphp
                <button class="office-clearance-card" type="button" data-office-modal-open="{{ $officeModalId }}" aria-controls="{{ $officeModalId }}" aria-haspopup="dialog">
                    <span class="office-card-head"><span class="office-card-identity"><span class="clearance-icon"><i class="bi {{ $officeIcons[strtolower($office['key'])] ?? 'bi-building' }}"></i></span><span><strong>{{ $office['label'] }}</strong><small>Office clearance</small></span></span></span>
                    <span class="office-card-meta"><span class="office-card-approver"><i class="bi bi-person-badge"></i><span><small>Clearance approver</small><strong>{{ $office['approver_name'] }}</strong></span></span><span class="office-card-status {{ $officeTone }}">{{ $office['status'] }}</span></span>
                    <span class="office-card-foot"><span>View requirements, remarks, and submission</span><i class="bi bi-arrow-right"></i></span>
                </button>
            @empty
                <div class="student-clearance-empty"><i class="bi bi-building-x"></i><h3>No office clearance entries yet</h3><p>Required office approvals will appear here when they become available.</p></div>
            @endforelse
        </div>
    </section>
</div>

    @if($registrarApproved)
        <div class="clearance-document-overlay" id="clearanceDocumentViewer" aria-hidden="true">
            <button class="clearance-document-backdrop" type="button" data-clearance-form-close aria-label="Close clearance form"></button>
            <section class="clearance-document-modal" role="dialog" aria-modal="true" aria-labelledby="clearanceDocumentTitle">
                <header class="clearance-document-header">
                    <div class="clearance-document-title">
                        <i class="bi bi-file-earmark-check"></i>
                        <div>
                            <h3 id="clearanceDocumentTitle">Official Clearance Form</h3>
                            <p>{{ $student->full_name }} · {{ $student->student_id }}</p>
                        </div>
                    </div>
                    <div class="clearance-document-controls">
                        <a class="clearance-document-download" href="{{ route('student.clearance.form.download') }}"><i class="bi bi-download"></i>Download PDF</a>
                        <button class="clearance-document-print" type="button" data-clearance-form-print><i class="bi bi-printer"></i>Print / Save PDF</button>
                        <button class="clearance-document-close" type="button" data-clearance-form-close aria-label="Close clearance form"><i class="bi bi-x-lg"></i></button>
                    </div>
                </header>
                <div class="clearance-document-frame-wrap">
                    <div class="clearance-document-loader"><div><i class="bi bi-hourglass-split"></i>Loading official clearance form…</div></div>
                    <iframe class="clearance-document-frame" title="Official student clearance form" src="about:blank"></iframe>
                </div>
            </section>
        </div>
    @endif

    @foreach ($officeItems as $office)
        @php
            $officeTone = match($office['status']) {
                'Approved' => 'success',
                'Rejected' => 'danger',
                'Pending' => 'warning',
                default => 'neutral',
            };
            $officeModalId = 'office-detail-' . $loop->index;
            $submission = $office['submission'];
        @endphp
        <div class="office-detail-overlay" id="{{ $officeModalId }}" aria-hidden="true">
            <button class="office-detail-backdrop" type="button" data-office-modal-close aria-label="Close {{ $office['label'] }} details"></button>
            <section class="office-detail-modal" role="dialog" aria-modal="true" aria-labelledby="{{ $officeModalId }}-title">
                <header class="office-detail-header">
                    <div class="office-detail-heading">
                        <span class="clearance-icon"><i class="bi {{ $officeIcons[strtolower($office['key'])] ?? 'bi-building' }}"></i></span>
                        <div><h3 id="{{ $officeModalId }}-title">{{ $office['label'] }}</h3><p>Clearance office details</p></div>
                    </div>
                    <button class="office-detail-close" type="button" data-office-modal-close aria-label="Close details"><i class="bi bi-x-lg"></i></button>
                </header>
                <div class="office-detail-body">
                    <div class="office-detail-summary">
                        <div class="office-detail-field">
                            <span>Current status</span>
                            <div class="office-detail-status {{ $officeTone }}">{{ $office['status'] }}</div>
                        </div>
                        <div class="office-detail-field">
                            <span>Last updated</span>
                            <strong>{{ $office['updated_at'] ? \Illuminate\Support\Carbon::parse($office['updated_at'])->format('M d, Y · h:i A') : 'No activity yet' }}</strong>
                        </div>
                        <div class="office-detail-field full">
                            <span>Account holder / clearance approver</span>
                            <strong><i class="bi bi-person-badge text-primary me-1"></i>{{ $office['approver_name'] }}</strong>
                        </div>
                        <div class="office-detail-field full">
                            <span>Required clearances</span>
                            @if($office['requires'] || $office['key'] === 'dean')
                                <div class="office-detail-requirements">
                                    @foreach($office['requires'] as $requiredOffice)<span><i class="bi bi-check2-circle me-1"></i>{{ ucwords($requiredOffice) }}</span>@endforeach
                                    @if($office['key'] === 'dean')<span><i class="bi bi-journals me-1"></i>All Subject Clearances</span>@endif
                                </div>
                            @else
                                <p>No prerequisite office clearance is required.</p>
                            @endif
                        </div>
                        <div class="office-detail-field full">
                            <span>Office remarks</span>
                            <p>{{ $office['remarks'] ?: 'No remark has been provided by this office yet.' }}</p>
                        </div>
                        <div class="office-detail-field full">
                            <span>Latest submitted document</span>
                            @if($submission)
                                <div class="office-submission-file">
                                    <div class="office-submission-copy">
                                        <strong><i class="bi bi-file-earmark-check text-primary me-1"></i>{{ $submission->file_name }}</strong>
                                        <small>{{ $submission->description ?: 'No description' }} · Sent {{ \Illuminate\Support\Carbon::parse($submission->submitted_at)->diffForHumans() }}</small>
                                    </div>
                                    <a class="office-file-link" href="{{ route('student.clearance.office-submission', $submission->id) }}" target="_blank" rel="noopener"><i class="bi bi-eye"></i>View file</a>
                                </div>
                            @else
                                <p>No document has been submitted to this office.</p>
                            @endif
                        </div>
                    </div>

                    <div class="office-detail-actions">
                        <h4>Available action</h4>
                        @if($office['can_submit'])
                            <form method="POST" action="{{ route('student.clearance.submit-office') }}" class="office-request-form">
                                @csrf
                                <input type="hidden" name="office_role" value="{{ $office['label'] }}">
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send-check me-1"></i>Submit clearance request</button>
                            </form>
                        @elseif($office['status'] === 'Not Requested')
                            <div class="office-detail-locked"><i class="bi bi-lock"></i><span>Complete the required clearance steps before submitting to this office.</span></div>
                        @endif

                        @if($office['status'] !== 'Not Requested')
                            <form method="POST" action="{{ route('student.clearance.upload-office') }}" enctype="multipart/form-data" class="office-upload-form">
                                @csrf
                                <input type="hidden" name="office_role" value="{{ $office['key'] }}">
                                <label class="small fw-semibold" for="office-file-{{ $loop->index }}">{{ $submission ? 'Send another document' : 'Send a document' }}</label>
                                <input id="office-file-{{ $loop->index }}" type="file" name="submission_file" accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm" required>
                                <input type="text" name="description" class="form-control form-control-sm" placeholder="Optional note about this document">
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i>Upload document</button>
                            </form>
                        @elseif(! $office['can_submit'])
                            <div class="office-detail-locked"><i class="bi bi-file-earmark-lock"></i><span>Document upload becomes available after the clearance request is submitted.</span></div>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    @endforeach
@endsection

@push('scripts')
<script>
(() => {
    const viewer = document.getElementById('clearanceDocumentViewer');
    const frame = viewer?.querySelector('.clearance-document-frame');
    const frameWrap = viewer?.querySelector('.clearance-document-frame-wrap');
    const openButton = document.querySelector('[data-clearance-form-open]');
    let lastTrigger = null;

    const closeViewer = () => {
        if (!viewer?.classList.contains('show')) return;
        viewer.classList.remove('show');
        viewer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('clearance-form-viewer-open');
        const trigger = lastTrigger;
        lastTrigger = null;
        if (trigger) window.setTimeout(() => trigger.focus(), 160);
    };

    openButton?.addEventListener('click', () => {
        if (!viewer || !frame) return;
        lastTrigger = openButton;
        frameWrap?.classList.remove('loaded');
        if (frame.src === 'about:blank' || !frame.src) {
            frame.src = openButton.dataset.clearanceFormSrc;
        } else {
            frameWrap?.classList.add('loaded');
        }
        viewer.classList.add('show');
        viewer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('clearance-form-viewer-open');
        window.setTimeout(() => viewer.querySelector('.clearance-document-close')?.focus(), 0);
    });

    frame?.addEventListener('load', () => frameWrap?.classList.add('loaded'));
    viewer?.querySelectorAll('[data-clearance-form-close]').forEach(button => button.addEventListener('click', closeViewer));
    viewer?.querySelector('[data-clearance-form-print]')?.addEventListener('click', () => {
        frame?.contentWindow?.focus();
        frame?.contentWindow?.print();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && viewer?.classList.contains('show')) closeViewer();
    });
})();

(() => {
    let activeOfficeModal = null;
    let modalTrigger = null;

    const closeOfficeModal = () => {
        if (!activeOfficeModal) return;
        activeOfficeModal.classList.remove('show');
        activeOfficeModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('office-detail-open');
        const trigger = modalTrigger;
        activeOfficeModal = null;
        modalTrigger = null;
        if (trigger) window.setTimeout(() => trigger.focus(), 160);
    };

    document.querySelectorAll('[data-office-modal-open]').forEach(trigger => {
        trigger.addEventListener('click', () => {
            const modal = document.getElementById(trigger.dataset.officeModalOpen);
            if (!modal) return;
            activeOfficeModal = modal;
            modalTrigger = trigger;
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('office-detail-open');
            window.setTimeout(() => modal.querySelector('.office-detail-close')?.focus(), 0);
        });
    });

    document.querySelectorAll('[data-office-modal-close]').forEach(button => button.addEventListener('click', closeOfficeModal));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && activeOfficeModal) closeOfficeModal();
    });
})();
</script>
@endpush
