<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#e7f5ff">
    <meta name="description" content="Privacy Policy for the Madridejos Community College e-Clearance System.">
    <title>Privacy Policy | MCC e-Clearance System</title>
    @include('partials.favicon')
    @include('partials.app-shell')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root{--blue:#087be8;--blue-dark:#0a3b70;--ink:#14233b;--muted:#60738e;--line:#cfe2f3;--surface:rgba(255,255,255,.9)}
        *{box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{min-width:320px;margin:0;color:var(--ink);font-family:"Inter",system-ui,sans-serif;background:#e8f6ff url("{{ asset('images/mcc-campus.jpg') }}") center/cover fixed no-repeat}
        body::before{position:fixed;z-index:-1;inset:0;content:"";background:linear-gradient(135deg,rgba(238,249,255,.97),rgba(218,239,255,.92) 54%,rgba(229,236,255,.9))}
        a{color:inherit}
        .policy-shell{width:min(1080px,calc(100% - 32px));margin:0 auto;padding:28px 0 48px}
        .policy-header{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:22px}
        .brand{display:flex;align-items:center;gap:13px;text-decoration:none}.brand img{width:58px;height:58px;padding:4px;border:1px solid rgba(255,255,255,.9);border-radius:50%;background:#fff;box-shadow:0 9px 24px rgba(38,92,145,.14)}.brand strong,.brand small{display:block}.brand strong{color:#0a3b70;font-size:1.04rem}.brand small{margin-top:3px;color:#657b96;font-size:.75rem}
        .back-link{display:inline-flex;min-height:44px;padding:0 16px;align-items:center;gap:8px;color:#15568f;border:1px solid #bcd8ef;border-radius:13px;background:rgba(255,255,255,.78);font-size:.84rem;font-weight:700;text-decoration:none;transition:.2s}.back-link:hover{border-color:#7eb9e8;background:#fff;transform:translateY(-1px)}
        .policy-hero{position:relative;padding:42px clamp(24px,5vw,58px);overflow:hidden;border:1px solid rgba(255,255,255,.9);border-radius:30px;background:linear-gradient(125deg,rgba(255,255,255,.97),rgba(226,243,255,.92));box-shadow:0 22px 60px rgba(40,92,140,.16)}
        .policy-hero::after{position:absolute;right:-70px;bottom:-100px;width:260px;height:260px;content:"";border:32px solid rgba(65,162,234,.08);border-radius:50%}
        .hero-icon{display:grid;width:58px;height:58px;margin-bottom:18px;place-items:center;color:#067fe5;border:1px solid #b9ddf7;border-radius:17px;background:#e1f4ff;font-size:1.55rem}
        .eyebrow{display:block;margin-bottom:8px;color:#0876d5;font-size:.78rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.policy-hero h1{margin:0;color:#0c315f;font-size:clamp(2rem,5vw,3.15rem);line-height:1.08}.policy-hero p{max-width:760px;margin:15px 0 0;color:#5f7491;font-size:1rem;line-height:1.7}.updated{display:inline-flex;margin-top:18px;padding:8px 12px;align-items:center;gap:7px;color:#315d87;border-radius:999px;background:rgba(255,255,255,.78);font-size:.76rem;font-weight:700}
        .policy-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px;margin-top:18px}.policy-card{padding:25px;border:1px solid rgba(182,214,239,.82);border-radius:22px;background:var(--surface);box-shadow:0 12px 34px rgba(51,100,147,.09)}.policy-card.wide{grid-column:1/-1}.card-heading{display:flex;align-items:center;gap:12px;margin-bottom:12px}.card-heading i{display:grid;flex:0 0 42px;width:42px;height:42px;place-items:center;color:#087be8;border-radius:13px;background:#e4f4ff;font-size:1.08rem}.policy-card h2{margin:0;color:#163e69;font-size:1.05rem}.policy-card p,.policy-card li{color:var(--muted);font-size:.9rem;line-height:1.7}.policy-card p{margin:0}.policy-card ul{margin:8px 0 0;padding-left:21px}.policy-card li+li{margin-top:7px}.policy-card strong{color:#284b70}
        .contact-card{display:flex;align-items:flex-start;gap:16px;background:linear-gradient(135deg,rgba(227,246,255,.96),rgba(241,246,255,.96))}.contact-card>i{display:grid;flex:0 0 50px;width:50px;height:50px;place-items:center;color:#087be8;border-radius:15px;background:#fff;font-size:1.3rem;box-shadow:0 8px 20px rgba(37,100,155,.1)}.contact-card h2{margin:2px 0 7px}.policy-footer{margin-top:22px;color:#54708d;text-align:center;font-size:.76rem}
        a:focus-visible{outline:3px solid rgba(7,91,234,.24);outline-offset:3px}
        @media(max-width:720px){.policy-shell{width:min(100% - 22px,1080px);padding-top:15px}.policy-header{align-items:flex-start}.brand small{display:none}.back-link span{display:none}.back-link{width:44px;padding:0;justify-content:center}.policy-hero{padding:30px 22px;border-radius:24px}.policy-grid{grid-template-columns:1fr}.policy-card.wide{grid-column:auto}.policy-card{padding:21px}.contact-card{display:block}.contact-card>i{margin-bottom:14px}}
    </style>
</head>
<body>
@include('partials.app-shell-body')
<main class="policy-shell">
    <header class="policy-header">
        <a class="brand" href="{{ route('landing') }}" aria-label="MCC e-Clearance home">
            <img src="{{ asset('images/mcc-logo.png') }}" alt="Madridejos Community College logo">
            <span><strong>MCC e-Clearance System</strong><small>Madridejos Community College</small></span>
        </a>
        <a class="back-link" href="{{ route('landing') }}"><i class="bi bi-arrow-left"></i><span>Back to portal selection</span></a>
    </header>

    <section class="policy-hero" aria-labelledby="policy-title">
        <span class="hero-icon"><i class="bi bi-shield-lock"></i></span>
        <span class="eyebrow">Your information and privacy</span>
        <h1 id="policy-title">Privacy Policy</h1>
        <p>This policy explains how the MCC e-Clearance System collects, uses, protects, and manages information needed to provide digital clearance services to the Madridejos Community College community.</p>
        <span class="updated"><i class="bi bi-calendar3"></i>Last updated: September 28, 2026</span>
    </section>

    <div class="policy-grid">
        <section class="policy-card">
            <div class="card-heading"><i class="bi bi-database"></i><h2>Information we collect</h2></div>
            <ul>
                <li><strong>Account and academic information:</strong> names, Student ID or personnel identifiers, email addresses, program, year level, section, role, and account credentials.</li>
                <li><strong>Clearance information:</strong> requests, requirements, uploaded documents, remarks, approval decisions, completion status, and issued clearance records.</li>
                <li><strong>Evaluation and communication data:</strong> submitted ratings, evaluation responses, messages, and notifications.</li>
                <li><strong>Security and activity data:</strong> sign-in events, IP address, browser or device details, timestamps, and actions recorded in the activity log.</li>
                <li><strong>Location data:</strong> approximate coordinates and accuracy may be recorded during authentication when browser permission is available.</li>
            </ul>
        </section>

        <section class="policy-card">
            <div class="card-heading"><i class="bi bi-clipboard2-check"></i><h2>How we use information</h2></div>
            <ul>
                <li>Create and manage accounts and confirm a user’s assigned role.</li>
                <li>Process, review, approve, and document student clearance requirements.</li>
                <li>Provide evaluations, messaging, notifications, account recovery, and downloadable records.</li>
                <li>Protect accounts, detect misuse, investigate security events, and maintain an audit trail.</li>
                <li>Prepare operational summaries and reports for authorized college functions.</li>
            </ul>
        </section>

        <section class="policy-card">
            <div class="card-heading"><i class="bi bi-people"></i><h2>Access and disclosure</h2></div>
            <p>Information is available only to authorized MCC users according to their portal role and assigned responsibilities. Records may also be disclosed when required for an authorized college process, system operation, security investigation, or applicable legal obligation. The system does not sell personal information.</p>
        </section>

        <section class="policy-card">
            <div class="card-heading"><i class="bi bi-cookie"></i><h2>Cookies and sessions</h2></div>
            <p>The system uses essential cookies and session storage to keep users signed in, protect form submissions, remember approved devices, and maintain security settings. These technologies support core system functions and account protection.</p>
        </section>

        <section class="policy-card">
            <div class="card-heading"><i class="bi bi-shield-check"></i><h2>Security</h2></div>
            <p>Administrative, technical, and access controls are used to protect records. These include role-based permissions, password protection, verification codes, request limits, restricted file access, and activity logging. Users must keep their credentials and verification codes private.</p>
        </section>

        <section class="policy-card">
            <div class="card-heading"><i class="bi bi-archive"></i><h2>Retention</h2></div>
            <p>Records are retained for as long as they are needed for active clearance processing, academic and administrative documentation, security, and applicable college retention requirements. Information may then be archived, anonymized, or securely removed under authorized procedures.</p>
        </section>

        <section class="policy-card wide">
            <div class="card-heading"><i class="bi bi-person-check"></i><h2>Your choices and requests</h2></div>
            <p>Users may ask the appropriate MCC office to review or correct inaccurate account and clearance information. Location permission can be managed through browser or device settings. Some information is required to authenticate users and complete the clearance process, so limiting it may prevent parts of the system from working.</p>
        </section>

        <section class="policy-card wide contact-card">
            <i class="bi bi-envelope-paper"></i>
            <div>
                <h2>Questions about your information</h2>
                <p>For privacy questions, record correction requests, or concerns about how information is handled, contact the appropriate Madridejos Community College office or the system administrator. Identity verification may be required before account or record information is released or changed.</p>
            </div>
        </section>
    </div>

    <p class="policy-footer">&copy; {{ date('Y') }} Madridejos Community College. All rights reserved.</p>
</main>
</body>
</html>
