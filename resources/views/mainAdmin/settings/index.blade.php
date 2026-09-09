@extends('mainAdmin.layouts.admin')
@section('title', 'System Settings — ClearanceMS')

@section('content')
<x-main-admin.page-header
    title="System Settings"
    description="End-of-term maintenance. Every action here is irreversible and affects all students at once."
    icon="bi bi-sliders"
    eyebrow="Administration"
/>

{{-- ============ ACTIVE TERM ============ --}}
<div class="data-table-wrap">
    <div class="data-table-header">
        <h3><i class="bi bi-calendar3"></i> Active Term</h3>
        <span>{{ $activeSemester ? \App\Support\AcademicTerm::label() : 'Not set' }}</span>
    </div>

    <div style="padding:18px 22px;">
        <p style="margin:0 0 14px;color:var(--muted);font-size:13px;line-height:1.6;">
            The semester and academic year the college is currently running. Changing the semester decides which
            subjects can be assigned to instructors — <strong>only subjects belonging to the active semester appear
            in the subject dropdown</strong> on the Subject Assignments page and in an irregular student's own
            subject picker. Existing assignments are left alone. This setting is safe to change back at any time.
        </p>

        @if($termSubjectCounts !== [])
        <div class="dept-pills" style="margin-bottom:16px;">
            @foreach($termSubjectCounts as $semester => $count)
                <span class="dept-pill {{ $activeSemester === $semester ? 'active' : '' }}">{{ $semester }}: <strong>{{ $count }}</strong> subject(s)</span>
            @endforeach
        </div>
        @endif

        @if(! $termAvailable)
            <div class="empty-state" style="text-align:left;margin-bottom:14px;">
                <i class="bi bi-database-exclamation"></i>
                <strong style="display:block;margin-bottom:5px;">The <code>system_settings</code> table is missing.</strong>
                <span style="color:var(--muted);font-size:13px;">
                    The term cannot be saved until it exists. Run <code>php artisan migrate</code>, or apply
                    <code>database/sql/system_settings.sql</code>. Until then every subject stays available for
                    assignment, exactly as before.
                </span>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.term') }}" autocomplete="off">
            @csrf
            <div class="form-row" style="align-items:flex-end;">
                <div class="fg" style="max-width:260px;">
                    <label>Semester</label>
                    <select name="semester" required @disabled(! $termAvailable)>
                        <option value="">Choose semester</option>
                        @foreach($semesters as $semester)
                        <option value="{{ $semester }}" {{ $activeSemester === $semester ? 'selected' : '' }}>{{ $semester }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fg" style="max-width:260px;">
                    <label>Academic Year <small>(optional)</small></label>
                    <input name="academic_year" value="{{ old('academic_year', $academicYear) }}" placeholder="2026-2027"
                           pattern="\d{4}\s*-\s*\d{4}" title="Two years, for example 2026-2027"
                           @disabled(! $termAvailable)>
                </div>
                <div class="fg" style="max-width:210px;">
                    <button type="submit" class="btn-save" @disabled(! $termAvailable)>
                        <i class="bi bi-check-circle-fill"></i> Save Term
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="data-table-wrap" style="margin-top:18px;border-left:4px solid var(--danger,#d9534f);">
    <div style="padding:18px 22px;">
        <h3 style="margin:0 0 6px;font-size:15px;"><i class="bi bi-exclamation-triangle-fill" style="color:var(--danger,#d9534f);margin-right:7px;"></i>Read before using the actions below</h3>
        <p style="margin:0;color:var(--muted);font-size:13px;line-height:1.6;">
            The two actions below this point cannot be undone. Each shows exactly what it will affect and needs its
            confirmation phrase typed in full before it runs. Run these at the end of a term, never during one.
        </p>
    </div>
</div>

{{-- ============ END-OF-YEAR PROMOTION ============ --}}
<div class="data-table-wrap" style="margin-top:18px;">
    <div class="data-table-header">
        <h3><i class="bi bi-arrow-up-circle"></i> End-of-Year Promotion</h3>
        <span style="font-size:12px;color:var(--muted);">{{ $promotion['total'] }} active student(s) affected</span>
    </div>

    <div style="padding:18px 22px;">
        <p style="margin:0 0 14px;color:var(--muted);font-size:13px;line-height:1.6;">
            Moves every <strong>active</strong> student up one year level. Students finishing 4th year are
            <strong>deactivated</strong> instead — they keep every record, but can no longer sign in, and their
            entry on the registration list re-opens so the Microsoft account can be reissued to a future intake.
            Already-inactive students are left alone.
        </p>

        <div class="dept-pills" style="margin-bottom:16px;">
            @foreach($promotion['promoting'] as $year => $count)
                <span class="dept-pill">Year {{ $year }} &rarr; {{ (int) $year + 1 }}: <strong>{{ $count }}</strong></span>
            @endforeach
            <span class="dept-pill" style="border-color:var(--danger,#d9534f);color:var(--danger,#d9534f);">
                Year 4 &rarr; deactivated: <strong>{{ $promotion['graduating'] }}</strong>
            </span>
        </div>

        @if(! $statusColumnAvailable)
            <div class="empty-state" style="text-align:left;margin-bottom:14px;">
                <i class="bi bi-database-exclamation"></i>
                <strong style="display:block;margin-bottom:5px;">The <code>status</code> column is missing.</strong>
                <span style="color:var(--muted);font-size:13px;">
                    Graduating students cannot be deactivated until it exists. Run <code>php artisan migrate</code>,
                    or apply <code>database/sql/student_account_status.sql</code>. Promotion is disabled until then.
                </span>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.promote') }}" autocomplete="off"
              data-confirm-title="Promote all students?"
              data-confirm="Every active student moves up a year and 4th years are deactivated.&#10;This cannot be undone."
              data-confirm-button="Yes, Promote" data-confirm-tone="warning">
            @csrf
            <div class="form-row" style="align-items:flex-end;">
                <div class="fg" style="max-width:340px;">
                    <label>Type <code>{{ $promotePhrase }}</code> to confirm</label>
                    <input name="confirmation" required autocomplete="off" placeholder="{{ $promotePhrase }}"
                           @disabled(! $statusColumnAvailable)>
                </div>
                <div class="fg" style="max-width:230px;">
                    <button type="submit" class="btn-save" style="background:var(--danger,#d9534f);"
                            @disabled(! $statusColumnAvailable || $promotion['total'] === 0)>
                        <i class="bi bi-arrow-up-circle-fill"></i> Promote All Students
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ============ CLEARANCE RESET ============ --}}
<div class="data-table-wrap" style="margin-top:18px;">
    <div class="data-table-header">
        <h3><i class="bi bi-eraser"></i> Clear Term Clearance Records</h3>
        <span style="font-size:12px;color:var(--muted);">{{ $reset['total'] }} row(s), {{ $reset['files'] }} file(s)</span>
    </div>

    <div style="padding:18px 22px;">
        <p style="margin:0 0 14px;color:var(--muted);font-size:13px;line-height:1.6;">
            Clears every clearance status, submission, and remark so the new term starts unsigned, and deletes the
            uploaded files from storage. <strong>An archive CSV downloads automatically as the reset runs</strong> —
            that download is your only copy, so keep it.
        </p>

        <div class="table-scroll" style="margin-bottom:16px;">
            <table class="cms-table">
                <thead><tr><th>Table</th><th>Rows to delete</th></tr></thead>
                <tbody>
                @foreach($reset['tables'] as $table => $count)
                    <tr>
                        <td style="font-family:monospace;color:var(--accent2);">{{ $table }}</td>
                        <td>{{ $count }}</td>
                    </tr>
                @endforeach
                    <tr>
                        <td style="font-family:monospace;">uploaded files on disk</td>
                        <td>{{ $reset['files'] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <a href="{{ route('settings.clearance-archive') }}" class="btn-csv" style="display:inline-flex;margin-bottom:16px;">
            <i class="bi bi-download"></i> Download archive only (changes nothing)
        </a>

        <form method="POST" action="{{ route('settings.reset') }}" autocomplete="off"
              data-confirm-title="Clear all clearance records?"
              data-confirm="Every clearance status, submission, remark and uploaded file is deleted.&#10;An archive CSV will download. This cannot be undone."
              data-confirm-button="Yes, Reset" data-confirm-tone="warning">
            @csrf
            <div class="form-row" style="align-items:flex-end;">
                <div class="fg" style="max-width:340px;">
                    <label>Type <code>{{ $resetPhrase }}</code> to confirm</label>
                    <input name="confirmation" required autocomplete="off" placeholder="{{ $resetPhrase }}">
                </div>
                <div class="fg" style="max-width:270px;">
                    <button type="submit" class="btn-save" style="background:var(--danger,#d9534f);"
                            @disabled($reset['total'] === 0 && $reset['files'] === 0)>
                        <i class="bi bi-eraser-fill"></i> Reset &amp; Download Archive
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
