<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#e7f5ff">
    <meta name="description" content="MCC e-Clearance System portal directory for students, instructors, offices, treasury, registrar, and administrators.">
    <title>MCC e-Clearance System</title>
    @include('partials.favicon')
    @include('partials.app-shell')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/landing.css') }}" rel="stylesheet">
</head>
<body>
@include('partials.app-shell-body')
    <div class="landing-orb orb-one" aria-hidden="true"></div>
    <div class="landing-orb orb-two" aria-hidden="true"></div>
    <div class="landing-grid" aria-hidden="true"></div>

    <header class="landing-header" id="home">
        <a class="landing-brand" href="#home" aria-label="MCC e-Clearance home">
            <span class="landing-logo"><img src="{{ asset('images/mcc-logo.png') }}" alt="Madridejos Community College logo"></span>
            <span><strong>MCC e-Clearance System</strong><small>Madridejos Community College</small></span>
        </a>

        <button class="landing-menu" id="landingMenuButton" type="button" aria-label="Open navigation" aria-controls="landingNavigation" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>

        <nav class="landing-nav" id="landingNavigation" aria-label="Landing page navigation">
            <a class="active" href="#home">Home</a>
            <a href="#about">About</a>
            <a href="#features">Features</a>
            <a href="#process">Process</a>
            <a href="#help">Help Center</a>
            <a class="contact-link" href="#help"><i class="bi bi-headset"></i>Contact Us</a>
        </nav>
    </header>

    <main>
        <section class="landing-hero" aria-labelledby="landingTitle">
            <div class="hero-badge"><span></span>Welcome to Madridejos Community College<span></span></div>
            <h1 id="landingTitle"><span>MCC</span> e-Clearance System</h1>
            <div class="hero-mark" aria-hidden="true"><span></span><i class="bi bi-shield-check"></i><span></span></div>
            <p>A secure digital clearance platform that simplifies requests, improves transparency, and keeps every clearance step connected.</p>
            <a class="hero-action" href="#portals"><span>Select your portal</span><i class="bi bi-arrow-down"></i></a>
        </section>

        <section class="portal-section" id="portals" aria-labelledby="portalTitle">
            <div class="section-heading">
                <span>Choose your access</span>
                <h2 id="portalTitle">Select your portal</h2>
                <p>Sign in to the portal assigned to your account and role.</p>
            </div>

            <div class="portal-grid">
                <article class="portal-card student-card">
                    <div class="portal-icon"><i class="bi bi-mortarboard-fill"></i></div>
                    <div class="portal-copy">
                        <h3>Student Portal</h3>
                        <p>Submit clearance requests, upload requirements, track progress, and message instructors.</p>
                    </div>
                    <a href="{{ route('student.login') }}"><span>Go to Student Login</span><i class="bi bi-arrow-right"></i></a>
                </article>

                <article class="portal-card instructor-card">
                    <div class="portal-icon"><i class="bi bi-person-video3"></i></div>
                    <div class="portal-copy">
                        <h3>Instructor Portal</h3>
                        <p>Review student submissions, manage assigned subjects, and approve clearances.</p>
                    </div>
                    <a href="{{ route('instructor.login') }}"><span>Go to Instructor Login</span><i class="bi bi-arrow-right"></i></a>
                </article>

                <article class="portal-card office-card">
                    <div class="portal-icon"><i class="bi bi-buildings-fill"></i></div>
                    <div class="portal-copy">
                        <h3>Office Personnel Portal</h3>
                        <p>Verify student obligations, review documents, send remarks, and update status.</p>
                    </div>
                    <a href="{{ route('office.login') }}"><span>Go to Office Login</span><i class="bi bi-arrow-right"></i></a>
                </article>

                <article class="portal-card treasurer-card">
                    <div class="portal-icon"><i class="bi bi-cash-stack"></i></div>
                    <div class="portal-copy">
                        <h3>Treasurer Portal</h3>
                        <p>Review financial requirements and process section or department clearances.</p>
                    </div>
                    <a href="{{ route('treasurer.login') }}"><span>Go to Treasurer Login</span><i class="bi bi-arrow-right"></i></a>
                </article>

                <article class="portal-card registrar-card">
                    <div class="portal-icon"><i class="bi bi-file-earmark-check-fill"></i></div>
                    <div class="portal-copy">
                        <h3>Registrar Portal</h3>
                        <p>Finalize student clearance, verify completed steps, and generate clearance forms.</p>
                    </div>
                    <a href="{{ route('registrar.login') }}"><span>Go to Registrar Login</span><i class="bi bi-arrow-right"></i></a>
                </article>

                <article class="portal-card admin-card">
                    <div class="portal-icon"><i class="bi bi-shield-lock-fill"></i></div>
                    <div class="portal-copy">
                        <h3>Main Administrator</h3>
                        <p>Manage accounts, assignments, programs, sections, and overall system activity.</p>
                    </div>
                    <a href="{{ route('login') }}"><span>Go to Admin Login</span><i class="bi bi-arrow-right"></i></a>
                </article>
            </div>
        </section>

        <section class="about-section" id="about" aria-labelledby="aboutTitle">
            <div class="about-visual">
                <div class="about-shield"><i class="bi bi-shield-check"></i></div>
                <span class="visual-chip chip-one"><i class="bi bi-check2-circle"></i>Clear status</span>
                <span class="visual-chip chip-two"><i class="bi bi-lightning-charge"></i>Faster processing</span>
                <span class="visual-chip chip-three"><i class="bi bi-bell"></i>Live updates</span>
            </div>
            <div class="about-copy">
                <span class="section-kicker">About the platform</span>
                <h2 id="aboutTitle">A simpler path to complete student clearance</h2>
                <p>MCC e-Clearance brings students, instructors, offices, treasury, and the registrar into one coordinated workflow. Every request, document, remark, and approval stays visible to the right account.</p>
                <div class="about-points">
                    <span><i class="bi bi-check-lg"></i>Role-based portal access</span>
                    <span><i class="bi bi-check-lg"></i>Centralized clearance progress</span>
                    <span><i class="bi bi-check-lg"></i>Digital document submission</span>
                    <span><i class="bi bi-check-lg"></i>Secure account recovery</span>
                </div>
            </div>
        </section>

        <section class="feature-section" id="features" aria-labelledby="featureTitle">
            <div class="section-heading">
                <span>Built for the MCC community</span>
                <h2 id="featureTitle">Clearance without the guesswork</h2>
                <p>The tools each portal needs, with a consistent and transparent experience.</p>
            </div>
            <div class="feature-grid">
                <article><i class="bi bi-shield-check"></i><div><h3>Secure & reliable</h3><p>Protected, role-based access keeps student records and actions within the proper portal.</p></div></article>
                <article><i class="bi bi-speedometer2"></i><div><h3>Fast & efficient</h3><p>Submit, review, remark, and approve clearance requirements without unnecessary paperwork.</p></div></article>
                <article><i class="bi bi-bar-chart-line"></i><div><h3>Transparent progress</h3><p>Students can see pending and approved steps while personnel receive actionable queues.</p></div></article>
                <article><i class="bi bi-chat-dots"></i><div><h3>Connected support</h3><p>Built-in messaging and notifications keep students and instructors informed.</p></div></article>
            </div>
        </section>

        <section class="process-section" id="process" aria-labelledby="processTitle">
            <div class="section-heading">
                <span>How it works</span>
                <h2 id="processTitle">From request to final clearance</h2>
            </div>
            <div class="process-grid">
                <article><span>01</span><i class="bi bi-box-arrow-in-right"></i><h3>Sign in</h3><p>Choose the correct portal and use your registered MCC account.</p></article>
                <article><span>02</span><i class="bi bi-send-check"></i><h3>Submit</h3><p>Send clearance requests and upload required supporting documents.</p></article>
                <article><span>03</span><i class="bi bi-clipboard2-check"></i><h3>Review</h3><p>Assigned instructors and offices review requirements and provide remarks.</p></article>
                <article><span>04</span><i class="bi bi-patch-check"></i><h3>Complete</h3><p>The registrar confirms the completed workflow and releases the clearance form.</p></article>
            </div>
        </section>

        <section class="help-section" id="help" aria-labelledby="helpTitle">
            <div class="help-content">
                <span class="help-icon"><i class="bi bi-life-preserver"></i></span>
                <div>
                    <span class="section-kicker">Account support</span>
                    <h2 id="helpTitle">Need help accessing your portal?</h2>
                    <p>Use the Forgot Password option on your login page. For account or portal-assignment concerns, contact your designated MCC office.</p>
                </div>
            </div>
            <a href="#portals"><span>View portal options</span><i class="bi bi-arrow-right"></i></a>
        </section>
    </main>

    <footer class="landing-footer">
        <div class="footer-brand"><img src="{{ asset('images/mcc-logo.png') }}" alt=""><span><strong>MCC e-Clearance System</strong><small>Madridejos Community College</small></span></div>
        <p>&copy; {{ date('Y') }} Madridejos Community College. All rights reserved.</p>
        <div class="footer-links">
            <a href="{{ route('privacy-policy') }}">Privacy Policy</a>
            <a href="#portals">Portal access</a>
            <a href="#home">Back to top <i class="bi bi-arrow-up"></i></a>
        </div>
    </footer>

    <script>
        (() => {
            const menuButton = document.getElementById('landingMenuButton');
            const navigation = document.getElementById('landingNavigation');
            const navigationLinks = [...navigation.querySelectorAll('a[href^="#"]')];
            const observedSections = [...document.querySelectorAll('header[id], main section[id]')];

            const closeNavigation = () => {
                navigation.classList.remove('open');
                menuButton.setAttribute('aria-expanded', 'false');
                menuButton.innerHTML = '<i class="bi bi-list"></i>';
            };

            menuButton.addEventListener('click', () => {
                const isOpen = navigation.classList.toggle('open');
                menuButton.setAttribute('aria-expanded', String(isOpen));
                menuButton.innerHTML = `<i class="bi ${isOpen ? 'bi-x-lg' : 'bi-list'}"></i>`;
            });
            navigationLinks.forEach(link => link.addEventListener('click', closeNavigation));

            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) return;
                    navigationLinks.forEach(link => link.classList.toggle('active', link.getAttribute('href') === `#${entry.target.id}`));
                });
            }, { rootMargin: '-35% 0px -58%', threshold: 0 });
            observedSections.forEach(section => observer.observe(section));
        })();
    </script>
</body>
</html>
