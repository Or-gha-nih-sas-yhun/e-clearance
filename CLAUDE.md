# MCC e-Clearance

Laravel 13 / PHP 8.3 student clearance system for Madridejos Community College.
Runs locally under WAMP at `c:\wamp64\www\e-clearance`, deployed to
`https://mcceclearance.com` (Hostinger). An Android WebView wrapper lives in
`mobile/student-android`.

## Seven portals, seven guards

The central architectural fact: there is no single `User`. Each portal has its own
guard, model, table, auth controller, and authenticate middleware.

| Guard | Model | Table | Routes | Middleware |
|---|---|---|---|---|
| `student` | `StudentAccount` | `student_account` | `routes/portal.php` | `student.auth` |
| `registrar` | `Registrar` | `registrar` | `routes/portal.php` | `registrar.auth` |
| `office` | `AdminPersonnel` | `admin_personnel` | `routes/office_treasurer.php` | `office.auth` |
| `treasurer` | `Treasurer` | `treasurers` (inferred) | `routes/office_treasurer.php` | `treasurer.auth` |
| `instructor` | `Instructor` | `instructor_account` | `routes/instructor.php` | `instructor.auth` |
| `admin` | `MainAdmin` | `main_admin` | `routes/web.php` | `admin.auth` |
| `web` | `User` | `users` | (stock Laravel, largely unused) | — |

Most authenticated routes also carry `no.history` (`PreventBackHistory`, no-store
headers). Guards are declared in `config/auth.php`, aliases in `bootstrap/app.php`,
which also forces JSON error rendering for `api/*` and `notifications/api*`.

## New-device sign-in codes

Every portal emails a six-digit code the first time an account signs in from a
given browser. A correct password alone never opens a session.

- `App\Http\Controllers\Concerns\VerifiesNewDevices` is the whole flow. A portal's
  `AuthController` still owns its own credential check, then hands the account to
  `completeLogin()`; the trait supplies the three public route targets
  (`verifyLoginCode`/`resendLoginCode`/`cancelLoginCode`) and each portal declares
  only `deviceGuard()`, `devicePanel()`, `deviceLoginRoute()`, `deviceHomeRoute()`,
  `deviceAccount()`, and — where the login form is not keyed on `email` —
  `deviceErrorField()`.
- `App\Support\LoginChallenge` holds the code mechanics: session key
  `login_challenge_{guard}`, hashed code, 10-minute expiry, 5 attempts per code.
  The plain code is **never** rendered back into any HTTP response, in any
  environment — an earlier version echoed it onto the login page when running
  locally without real SMTP, which was a genuine leak: Laravel's own default
  mailer is `log` (`env('MAIL_MAILER', 'log')`), so any unconfigured environment
  silently fell back to it and the code became visible HTML to anyone loading
  the page, no email access required. A developer testing with the `log` mailer
  can already read the full rendered email in `storage/logs/laravel.log`.
- Brute-forcing the code is bounded two ways. The per-code `attempts` counter
  (5) protects one code, but resets to zero on every "Resend code", so alone it
  caps nothing across a longer attempt. `LoginChallengeLockout` (private to
  `LoginChallenge.php`) is the real ceiling: an account-level `RateLimiter` key,
  independent of resend, that accumulates wrong guesses across every code the
  account is issued (`login_security.otp_account_lockout_after`, default 8)
  before `send()` itself refuses to mail another code.
- `App\Support\TrustedDevice` remembers verified browsers in **one encrypted
  cookie**, not a table — deploys never run `artisan migrate`, and a table that
  never got created would mean a code on every login forever. Entries are keyed
  `guard:hash(accountId)` and bound to a fingerprint built from
  `DeviceFingerprint::describe()` (browser/platform/category, so a browser update
  is not a new device). Default trust window is 30 days.
- **Main Admin never banks a device** — `deviceTrustAllowed()` returns false there,
  so it re-verifies on every sign-in. Flipping that to true is the only change
  needed to make it behave like the rest.
- An account with no email on file cannot sign in at all: there is nowhere to send
  the code. That is deliberate, and audited as `authentication.mfa_unavailable`.
- Tests go through `Tests\TestCase::loginThroughDeviceCode()`, which posts the
  credentials and then swaps the challenge's `code_hash` for a code it knows. Any
  test that logs in over HTTP rather than `actingAs()` needs it.
- Route names are `{portal}.login.otp.{verify,resend,cancel}`, except Main Admin's,
  which are `login.otp.*`. `auth/portal-login.blade.php` derives them from
  `$recoveryPortal`; the student portal has its own login template and hardcodes
  them.

## Student self-registration

Students create their own portal account, but only if the college has listed
them first. `student_registry` is that roster: one row per
(`student_id`, `ms_account`), with `status` `inactive` (may register) or
`active` (already has an account).

- `App\Http\Controllers\Student\RegistrationController` runs the three steps —
  enter the Microsoft account, enter the emailed six-digit code, fill the form.
  Steps 1–2 are panels on the student login page; step 3 is its own page
  (`student/register`, `student.auth.register`) because the form is nine fields.
- **The form's identity fields are not trusted.** `student_id` and the email are
  read back from the verified session, never from the request — the inputs are
  only visually locked, and `StudentSelfRegistrationTest` posts a tampered
  `student_id`/`email` to prove the submitted values are ignored.
- Every table is MyISAM, so `DB::transaction()` rolls nothing back. Registration
  therefore *claims* the roster row with a conditional
  `update(...)->where('status','inactive')` and checks the affected-row count
  before creating the account, reverting the row if the insert throws. That
  compare-and-set is what stops a replayed session creating two accounts.
- Deleting a student account flips its roster row back to `inactive`
  (`RecordPurge::student`) — the row belongs to the college, not the student, so
  it is re-opened rather than deleted. Main Admin therefore *cannot* set an entry
  back to `inactive` or delete it while a `student_account` still exists.
- Main Admin manages the roster at `mainAdmin/student-registry`
  (`student-registry.index`) and can bulk-load it with the `student_registry`
  CSV type (`student_id,ms_account,status`).
- **The table needs creating by hand on any deployed database** — deploys never
  migrate. `database/sql/student_registry.sql` is the script; until it exists,
  reads are guarded by `Schema::hasTable()`, the admin page explains itself, and
  registration refuses politely instead of 500ing.

## End-of-term maintenance (Main Admin -> System Settings)

`mainAdmin/settings` (`settings.index`) holds the two irreversible operations
that let the system survive past one school year. `App\Support\SystemMaintenance`
does the work; `SystemSettingsController` only gates it.

- **Promotion** moves every *active* student up a year. It deactivates fourth
  years **first**, then bumps 3->4, 2->3, 1->2 in that order — promoting 3->4
  before deactivating would sweep the just-promoted third years out with the
  real graduates. Inactive students are skipped entirely so a graduate never
  keeps advancing.
- Graduating sets `student_account.status = 'inactive'` and re-opens the
  student's `student_registry` row. **Nothing is deleted** — the account keeps
  its clearance history, and `Student\AuthController::accountIsDeactivated()`
  blocks sign-in (checked *after* the password, so the login form cannot be used
  to enumerate deactivated accounts).
- **Clearance reset** clears the five clearance tables and deletes the uploaded
  files. Files go **before** their rows: the rows carry `file_path`, so deleting
  rows first would strand every file on disk with no record of where it is. The
  archive CSV is built before anything is deleted and returned as the response,
  so a reset always hands back a copy of what it removed.
- Every action shows live row/file counts and refuses to run unless its exact
  phrase is typed (`PROMOTE STUDENTS` / `RESET CLEARANCE`). The phrases differ
  deliberately, and `SystemSettingsTest` proves one cannot fire the other.
- `status`/`deactivated_at` need adding by hand on a deployed database
  (`database/sql/student_account_status.sql`) — deploys never migrate. Without
  the column, promotion is disabled and the page explains why rather than
  silently graduating nobody.

## Active term (Main Admin -> System Settings)

`system_settings` is a small key/value table; its first tenant is the term the
college is running. `App\Support\AcademicTerm` owns it —
`active_semester` (`1st Semester` / `2nd Semester` / `Summer`) and
`academic_year` (stored as `2026-2027`).

- The semester has **one functional effect**: only its subjects can be assigned.
  The Subject Assignments dropdown lists just that semester's subjects, an
  irregular student's own picker (`StudentSubjects::offeredSubjects()`) follows
  the same rule, and `ensureSubjectScope()` re-checks it server-side.
- That server check runs **on create only** (`enforceTerm: true` from `store()`,
  false from `update()`). Blocking edits would trap the admin: an assignment
  made last term is still listed, and they must be able to fix its section or
  year without being told to change the whole college's term first. The edit
  dropdown likewise keeps the assignment's own subject listed — labelled with
  its semester — even when it is out of term.
- The academic year is a label. It fixes the printed clearance form, which used
  to guess: the A.Y. came from `now()->year` and the semester from a
  `student_account.semester` column **that does not exist**, so every form
  printed a hardcoded "2nd Semester".
- **With no term set — or on a database without `system_settings` — every
  subject stays assignable**, which is exactly how the system behaved before
  the setting existed. `database/sql/system_settings.sql` creates the table by
  hand; deploys never migrate. `AcademicTerm::forget()` drops the per-request
  memo, which tests need when they change the term mid-run.

## Instructor faculty details

Two things about `instructor_account` that no other portal shares.

- **Position.** `employment_status` is `Regular` or `Part Timer`
  (`Instructor::EMPLOYMENT_STATUSES`). It needs adding by hand on a deployed
  database (`database/sql/instructor_employment_status.sql`) — deploys never
  migrate — so every read and write is gated on
  `Instructor::tracksEmploymentStatus()`: without the column the field, the
  filter and the table column disappear and the add form says why, instead of
  every save failing. Only Main Admin sets it; the instructor's own account
  panel shows it disabled.
- **BSED and BEED are one department here.** They are still two separate
  student programs everywhere else — `ImportCsvController::PROGRAMS`,
  sections, assignments, subject codes and `StudentController` are untouched —
  but instructors belong to the *College of Education*.
  `App\Support\InstructorDepartment` owns that: `OPTIONS` is what the dropdowns
  offer, `canonical()` folds a stored `'BSED'`/`'BEED'` onto the college for
  display and before every write, `accepted()` is the validation list (options
  plus the two legacy values, so a pre-merge row can still be saved), and
  `storedValues()` expands a filter on the college back to all three. The merge
  is safe precisely because **nothing scopes on `instructor_account.department`**
  — an instructor reaches students through `instructor_assignment`, never
  through their department — so it moves a label and not who sees whom.
  Rows are rewritten opportunistically as each one is saved; the optional
  `UPDATE` at the end of the SQL script does the rest.

## Subject assignments (Main Admin)

`mainAdmin/assignments` is a three-step drill-down, not a flat list, and every
step is one query string on the same `assignments.index` route so it can be
linked and returned to after a save.

- `?` -> the four faculty cards; `?department=X` -> that faculty's instructors;
  `?department=X&instructor=ID` -> that instructor's own add form and subject
  list. `?view=all` is the old college-wide filterable table, kept for a
  whole-college look; its forms carry `return_view=all` so a save or delete
  lands back there instead of in the drill-down.
- The cards are **instructor faculties** (`InstructorDepartment::OPTIONS`, BSED
  and BEED merged), while an assignment's `program` is a **student program** and
  stays one of the five. A College of Education instructor is assigned to BSED
  or BEED sections individually — do not merge the program dropdown.
- `requestedInstructor()` only accepts an instructor who is actually in the
  named department, so a hand-edited URL falls back to the roster rather than
  captioning someone under a faculty they are not in. Anyone whose department is
  not one of the four is collected in a visible `Unassigned` card instead of
  disappearing from the page.
- The subject dropdown is filtered client-side to the chosen program and year,
  mirroring `ensureSubjectScope()`, so the form cannot build the combination the
  server would reject. Sections come from `ProgramSection` the same way
  (`ensureManagedSections()`).

## Clearance domain

Two separate status tables — don't conflate them:

- **`office_clearance_status`** — one row per (student, office_role). Unique on
  `['student_id','office_role']`. `status` enum `Pending|Approved|Rejected`.
  Note `approver_id` is **NOT NULL** in the real migration.
- **`clearance_status`** — one row per (student, subject, instructor). The
  per-subject instructor sign-off.

`App\Support\ClearanceWorkflow` owns the rules:

- `OFFICE_ROLES` defines the nine offices and their order, ending at `registrar`.
- `normalizeOfficeRole()` — office role strings are stored inconsistently
  (`'Section Treasurer'`, `'section_treasurer'`, …). Always normalize; queries use
  `whereRaw("LOWER(TRIM(office_role)) = ?")`.
- `prerequisitesMet($student, $role)` — gates approval. `registrar` requires all
  eight earlier offices approved **and** every instructor clearance approved;
  `dean` requires the two treasurers plus instructors; `department treasurer`
  requires `section treasurer`.

Authorization is `StudentAccountPolicy` (`reviewSubject`/`reviewOffice`/
`reviewTreasury`/`reviewRegistrar`) delegating scope checks to
`App\Support\ClearanceAccess`.

Treasurer scope, both directions: a **department** treasurer acts only on
students whose `program` equals their `department`; a **section** treasurer only
on their exact program + year_level + section. Students never target a treasurer
directly — a submission writes one `office_clearance_status` row for the *role*
(`approver_id` is seeded with the student's own id because the column is NOT
NULL), and `scopeTreasurerStudents()` decides which treasurer ever sees it.

**Never compare section names with plain string equality.** `section` is free
text on every form and importer that writes it (`'section' => 'string|max:50'`),
so one section is stored as `SOUTHEAST` by the Main Admin dropdowns and
`South East` by hand — the treasurers table already holds both spellings.
`App\Support\SectionKey` is the only correct comparison: `of()`/`matches()` in
PHP and `sql()` for queries, all lowercasing and stripping spaces, hyphens and
underscores. `sql()` uses nested `REPLACE()`, which both MySQL and SQLite
support. Getting this wrong scopes a treasurer or an assigned instructor away
from their own students *silently* — an empty list, no error.

**Asymmetry to remember:** setting a clearance back to `Pending` runs no
prerequisite check; approving does. So a record can be reverted but then refuse to
re-approve — that is the rule working, not a bug.

## Irregular students pick their own subjects

A regular student's subjects are their section's block in `instructor_assignment`.
An **irregular** student (`student_account.student_type = 'Irregular'`) is not on
that block, so they declare each subject themselves together with the instructor
who will clear it. Those choices are the rows in `irregular_enrollment`.

- `App\Support\StudentSubjects` is the single answer to "which (subject,
  instructor) pairs does this student clear?" — `forStudent()` for one student,
  `covers()` for one pair, and `pairs()` as a derived table
  (`DB::query()->fromSub(StudentSubjects::pairs(), 'sp')`) for every listing.
  The two sources are **mutually exclusive**: an irregular student's section
  block does not apply to them at all, because clearing subjects they are not
  taking is exactly what the manual list exists to avoid.
- Six places used to hand-roll the regular half of that query and silently
  excluded irregular students — the student's clearance page, the submit gate
  (`ClearanceWorkflow::instructorIsAssigned`), the dean/registrar prerequisite
  (`allInstructorClearancesApproved`), the printed form, the QR verification,
  and every instructor listing. All six go through `StudentSubjects` now, so a
  student's own page and their instructor's listing can never disagree.
- `Student\SubjectEnrollmentController` (`student/my-subjects`,
  `student.subjects.*`) is the picker. The instructors offered for a subject are
  only those with an `instructor_assignment` for it, in **any** section — so a
  student can never route their clearance to someone who does not teach it, and
  the server re-checks that on submit rather than trusting the dropdown.
- Dropping a subject purges its clearance row, remarks and uploaded file
  (`RecordPurge::enrollment`); a stranded `clearance_status` row would keep
  counting toward the dean and registrar prerequisite for a subject the student
  no longer takes. An **Approved** subject cannot be dropped — the instructor
  has to set it back to Pending first.
- Chat needed no change: `ChatDirectory` already counted `irregular_enrollment`
  in both directions (`instructorTeaches`, `narrowToTeachingInstructors`,
  `scopeInstructorStudents`), so a student and their chosen instructor can reach
  each other as soon as the enrolment row exists.
- Everything is guarded with `Schema::hasTable()`/`hasColumn()`: on a database
  without `irregular_enrollment` or without `student_account.student_type`,
  every student is treated as regular and the system behaves exactly as before.

## Cross-role chat

`chat_messages` rows carry both a `sender_role`/`sender_id` and a
`receiver_role`/`receiver_id`, so a conversation can span any two portals. Four
staff portals talk to students: `instructor`, `office`, `treasurer`, `registrar`.

- `App\Support\ChatDirectory` is the single authorization gate — `permits($student,
  $role, $account|$id)` — plus the two contact lists (`staffContactsFor`,
  `studentContactsFor`). Scoping mirrors `ClearanceAccess` with one relaxation: a
  conversation does **not** require an existing `office_clearance_status` row, so a
  student can ask before submitting. Program heads are limited to their program;
  section treasurers to their exact program/year/section; instructors reach a
  student through `instructor_assignment` **or** `irregular_enrollment`.
- `App\Support\ChatThread` reads and writes one thread, marks it read on open, and
  creates the recipient notification. Partners are `['role' => …, 'id' => …]` pairs
  keyed as `role|id` (ids collide across portals, so the role is never optional).
- Staff portals share `App\Http\Controllers\StaffChatController`; each subclass only
  declares `guard()`, `view()`, `subheading()`. The UI is one component,
  `<x-portal.messenger>`, which pushes its own polling script.
- Keep SQL portable: production is MySQL, tests are SQLite. No `CONCAT`, and no
  multi-column `COUNT(DISTINCT a, b)`.

## Activity tracking

`security_audit_logs` was already being written by the `Login`/`Logout` listeners in
`AppServiceProvider`, the `AuditSecurityEvents` middleware (every non-safe request),
and `App\Support\LoginSecurity`. `App\Http\Controllers\ActivityLogController` is the
Main Admin read-only view over it at `mainAdmin/activity` (`activity.index`); it
guards on `Schema::hasTable()` because deploys never migrate. Device information is
derived from the stored `user_agent` by `App\Support\DeviceFingerprint` — there is no
device id column. `App\Support\PortalAccounts` resolves a (role, id) pair back to a
display name across the seven tables, one query per portal.

## Gotchas discovered the hard way

- **Deploys never run artisan.** `.github/workflows/deploy-hostinger.yml` fires on
  push to `main` and does `composer install` + `npm run build` + FTP sync to
  `/public_html/` — no `migrate`, no cache clear. `bootstrap/cache/` is gitignored,
  so any `config.php`/`routes-*.php` cached on the server is **frozen forever**
  until someone clears it over SSH. `deploy/hostinger-deploy.sh` (manual, SSH) does
  cache things. Never put a machine-specific absolute path in a config default.
- **Validation errors are invisible in the portals.** `layouts/portal.blade.php`
  renders neither `$errors` nor a summary, and
  `partials/action-feedback-modal.blade.php` only reads `session('flash')`,
  `login_success`, `success`, `status`. A bare `ValidationException` on a form POST
  therefore looks like the button did nothing. Use the
  `Concerns\ReportsClearanceRefusals` trait, or flash `['type','title','message']`.
  Bulk actions post JSON and surface errors through their own modal, so bulk and
  single-row paths can behave differently.
- **`@section('x', $maybeNull)` leaks an output buffer.** Blade's `startSection()`
  calls `ob_start()` when the value is null and waits for an `@endsection` that
  never comes, truncating the page. Several views do this with `full_name`
  (`office/*.blade.php`, `treasurer/*.blade.php` have no `??` fallback). Give test
  users a non-null label or PHPUnit flags the test risky.
- **There is no Tailwind in this project.** All three layouts load Bootstrap 5, and
  `main_admin_portal.css` styles `.pagination`/`.page-link` with `--bs-pagination-*`
  variables. Laravel's paginator nevertheless defaults to its *Tailwind* view, whose
  `sm:hidden` / `hidden sm:flex` blocks then both render and whose chevron
  `<svg class="w-5 h-5">` paints at full container width — a giant arrow between two
  sets of page links. `AppServiceProvider::boot()` now calls
  `Paginator::useBootstrapFive()`, so plain `->links()` is correct everywhere; do not
  pass a view name per call. The same trap applies to any Tailwind utility class
  copied in from documentation — none of them do anything here.
- **Uploads are private.** Student documents live in `storage/app/private`, served
  through controllers via `App\Support\SubmissionFileResponse`. Never move them into
  `public/` or behind the storage symlink. `App\Support\SecureUpload` does MIME and
  content validation.
- `app/Models/` contains five empty leftover directories (`Instractors`, `Offices`,
  `Registrar`, `Student`, `Treasures`) alongside the real `.php` models.

## Commands

```bash
php artisan test                          # 291 tests, all should pass
php artisan test --filter=SomeTest        # prefer this while iterating
npm run build                             # vite -> public/build
php artisan security:preflight --document-root=/path/to/public
```

No Pint or static analysis is configured. Tests build their own schema with
`Schema::create` in `setUp()` rather than running migrations — match that pattern
(see `tests/Feature/BulkClearanceStatusTest.php`).

## Conventions

- Controllers are namespaced per portal (`Http/Controllers/Registrar/…`,
  `Office/…`, `Treasurer/…`, `Student/…`, `Instructors/…`).
- Heavy use of the query builder (`DB::table`) over Eloquent in clearance code.
- Success feedback: `return back()->with('flash', ['type' => 'success', 'message' => …])`.
- Blade views are dense and often single-line; match the surrounding density.
