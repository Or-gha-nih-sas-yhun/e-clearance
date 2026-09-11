@php
    /** @var \App\Models\StudentAccount $student */
    $pdfDownload = $pdfDownload ?? false;
    $embedded = request()->boolean('embed') || $pdfDownload;
    $pdfSubjectRowHeight = $pdfDownload && $subjects->isNotEmpty()
        ? max(58, (int) floor(($offices->count() * 70) / $subjects->count()))
        : null;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Student Clearance Form — {{ $student->student_id }}</title>
    @include('partials.favicon')
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { margin: 0; background: #e5e7eb; font: 11px 'Times New Roman', serif; color: #111; text-transform: uppercase; }
        .toolbar { max-width: 820px; margin: 16px auto; display: flex; gap: 8px; flex-wrap: wrap; }
        .toolbar a, .toolbar button { border: 0; border-radius: 5px; padding: 9px 14px; background: #1e3a8a; color: #fff; text-decoration: none; font: 700 13px Arial; cursor: pointer; }
        .toolbar button { background: #047857; }
        .paper { max-width: 820px; margin: 0 auto 20px; padding: 28px 32px; background: #fff; border: 1px solid #222; box-shadow: 0 2px 14px #0002; position: relative; }
        /* A logo either side of the centred school name. Dompdf has no flexbox,
           so a three-cell table is what keeps them aligned in the PDF. */
        .letterhead { width: 100%; border-collapse: collapse; }
        .letterhead td { border: 0; padding: 0; vertical-align: middle; }
        .letterhead-logo { width: 88px; text-align: center; }
        .letterhead-logo img { width: 80px; height: auto; }
        .letterhead-copy { text-align: center; padding: 0 10px; }

        /* The QR sits at the foot of the page now, clear of the letterhead. */
        .qr-verification { margin: 14px auto 0; width: 120px; text-align: center; font: 7px Arial, sans-serif; color: #444; page-break-inside: avoid; }
        .qr-verification img { width: 92px; height: 92px; display: block; margin: 0 auto 3px; }
        .header { text-align: center; padding-bottom: 8px; margin-bottom: 10px; }
        .school { font-size: 15px; font-weight: bold; text-transform: uppercase; }
        .title { margin: 6px 0 2px; font-size: 14px; font-weight: bold; letter-spacing: 2px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .info-table td { border: 1px solid #111; padding: 5px; }
        .body-layout { display: flex; border: 1px solid #111; margin-top: 10px; }
        .left-col { flex: 0 0 63%; border-right: 1px solid #111; }
        .subject-table { width: 100%; border-collapse: collapse; }
        .subject-table th, .subject-table td { border: 1px solid #111; padding: 5px; }
        /* The frame around this block is drawn once, by .body-layout and by
           .left-col's right edge. The table's own outer borders sat directly
           alongside those and printed as a 2px line, which is why the subject
           grid looked heavier than the offices column beside it. */
        .subject-table thead th { border-top: none; }
        .subject-table th:first-child, .subject-table td:first-child { border-left: none; }
        .subject-table th:last-child, .subject-table td:last-child { border-right: none; }
        .subject-table th { background: #e5e7eb; font-size: 9px; text-transform: uppercase; }
        .subject-table td:nth-child(1) { width: 26%; font-weight: bold; }
        .subject-table td:nth-child(2) { width: 22%; text-align: center; }
        .subject-table td:nth-child(3) { width: 52%; }
        .sig-cell { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 52px; gap: 2px; }
        .sig-cell img { max-width: 110px; max-height: 34px; object-fit: contain; }
        .sig-line { width: 88%; border-top: 1px solid #555; margin: 1px 0; }
        .sig-name { font-size: 9px; font-weight: bold; text-transform: uppercase; text-align: center; }
        .sig-pending { font-size: 9px; color: #bbb; font-style: italic; }
        .right-col { flex: 0 0 37%; display: flex; flex-direction: column; }
        .right-col-title { background: #e5e7eb; border-bottom: 1px solid #111; padding: 6px; font-size: 9px; text-transform: uppercase; text-align: center; font-weight: bold; }
        .office-sig-box { flex: 1; border-bottom: 1px solid #111; padding: 8px; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; min-height: 72px; }
        .office-sig-box:last-child { border-bottom: none; }
        .office-sig-box img { max-width: 120px; max-height: 40px; object-fit: contain; margin-bottom: 2px; }
        .office-line { width: 90%; border-top: 1px solid #111; margin: 4px auto 3px; }
        .office-name { font-size: 8px; font-weight: bold; text-align: center; text-transform: uppercase; }
        .office-role { font-size: 11px; font-weight: bold; color: #333; text-align: center; margin-top: 2px; }
        .office-blank { font-size: 8.5px; color: #ccc; font-style: italic; margin-bottom: 6px; }
        .bottom-sig { border: 1px solid #111; border-top: none; }
        .bottom-sig-box { text-align: center; padding: 8px 10px 6px; min-height: 80px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .bottom-sig-box strong, .bottom-sig-box span { text-align: center; }
        .bottom-sig-box img { max-height: 42px; object-fit: contain; margin-bottom: 2px; }
        .bottom-line { width: 40%; border-top: 1px solid #111; margin: 4px auto 3px; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 10px; font: 700 8px Arial; text-transform: uppercase; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        .status-banner { margin-top: 14px; padding: 8px 16px; text-align: center; font: 900 10.5px Arial; letter-spacing: 1px; text-transform: uppercase; border: 1px solid rgba(0,0,0,.1); background: {{ $overallStatus === 'Cleared' ? '#d1fae5' : '#fef3c7' }}; color: {{ $overallStatus === 'Cleared' ? '#065f46' : '#92400e' }}; }
        .footer { margin-top: 18px; text-align: center; font-size: 8.5px; color: #888; border-top: 1px solid #ccc; padding-top: 6px; }
        body.embedded { padding: 12px; background: #edf3f7; }
        body.embedded .paper { margin: 0 auto; }
        body.pdf-document { padding: 0; background: #fff; font-size: 9px; }
        body.pdf-document .paper { width: auto; max-width: none; margin: 12px; padding: 18px 22px; border: 1px solid #222; box-shadow: none; }
        body.pdf-document .body-layout { display: table; width: 100%; table-layout: fixed; page-break-inside: avoid; }
        body.pdf-document .left-col { display: table-cell; width: 63%; vertical-align: top; }
        body.pdf-document .right-col { display: table-cell; width: 37%; vertical-align: top; }
        body.pdf-document .subject-table td { vertical-align: middle; }
        body.pdf-document .sig-cell { display: block; min-height: 0; text-align: center; }
        body.pdf-document .sig-cell .badge { display: inline-block; }
        body.pdf-document .sig-cell .sig-name,
        body.pdf-document .sig-cell .sig-pending { display: block; margin-right: auto; margin-left: auto; text-align: center; }
        body.pdf-document .sig-cell .sig-pending { margin-top: 4px; }
        body.pdf-document .office-sig-box { display: block; min-height: 70px; padding: 7px 8px; text-align: center; page-break-inside: avoid; }
        body.pdf-document .office-sig-box .badge { margin-bottom: 5px; }
        body.pdf-document .bottom-sig-box { display: block; min-height: 72px; padding: 12px 10px 8px; text-align: center; }
        body.pdf-document .bottom-sig-box .badge { display: table; margin: 0 auto 6px; }
        body.pdf-document .bottom-sig-box strong,
        body.pdf-document .bottom-sig-box span { display: block; margin-right: auto; margin-left: auto; text-align: center; }
        body.pdf-document .bottom-sig,
        body.pdf-document .status-banner,
        body.pdf-document .footer { page-break-inside: avoid; }
        @page { size: A4 portrait; margin: 8mm; }
        @media print { body, body.embedded { padding: 0; background: #fff; } .toolbar { display: none !important; } .paper, body.embedded .paper { box-shadow: none; border: none; max-width: 100%; padding: 18px 24px; } }
    </style>
</head>
<body class="{{ $embedded ? 'embedded' : '' }} {{ $pdfDownload ? 'pdf-document' : '' }}">
@unless($embedded)
<div class="toolbar">
    <a href="{{ $isRegistrar ? route('registrar.student-clearance') : route('student.dashboard') }}">← Back</a>
    <button onclick="window.print()">Print / Save PDF</button>
</div>
@endunless

<main class="paper">
    <header class="header">
        <table class="letterhead">
            <tr>
                <td class="letterhead-logo">
                    @if($collegeLogo)<img src="{{ $collegeLogo }}" alt="Madridejos Community College logo">@endif
                </td>
                <td class="letterhead-copy">
                    <div class="school">Madridejos Community College</div>
                    <div style="font-size:10px;color:#444;">Bunakan, Madridejos, Cebu</div>
                    <div class="title">Student Clearance Form</div>
                    <div style="font-size:10px;color:#555;">Registrar's Office &nbsp;|&nbsp; For Student Use</div>
                    <div style="font-size:9px;color:#777;margin-top:2px;">Generated: {{ now()->format('F d, Y \a\t g:i A') }}</div>
                </td>
                <td class="letterhead-logo">
                    @if($municipalityLogo)<img src="{{ $municipalityLogo }}" alt="Municipality of Madridejos seal">@endif
                </td>
            </tr>
        </table>
    </header>

    <table class="info-table">
        <tr>
            <td width="8%"><strong>NAME:</strong></td>
            <td width="33%">{{ $student->full_name }}</td>
            <td width="13%"><strong>COURSE/YEAR:</strong></td>
            <td>{{ $student->program }} &nbsp;–&nbsp; {{ $student->year_level }} &nbsp;({{ $student->section }})</td>
        </tr>
        {{-- The term the college is running, set in Main Admin -> System Settings.
             These used to be guesses: the year came from the server clock and the
             semester from a `student_account.semester` column that does not exist,
             so the form always printed a hardcoded "2nd Semester". --}}
        <tr>
            <td><strong>A.Y.:</strong></td>
            <td>{{ \App\Support\AcademicTerm::academicYear() ?? now()->year.'–'.(now()->year + 1) }}</td>
            <td><strong>SEMESTER:</strong></td>
            <td>{{ \App\Support\AcademicTerm::semester() ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>ID NUM:</strong></td>
            <td>{{ $student->student_id }}</td>
            <td><strong>TYPE:</strong></td>
            <td>{{ $student->student_type ?? 'Regular' }}</td>
        </tr>
    </table>

    <div class="body-layout">
        <div class="left-col">
            <table class="subject-table">
                <thead>
                    <tr>
                        <th>Subject Code</th>
                        <th>Status</th>
                        <th>Instructor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subjects as $subject)
                        <tr @if($pdfSubjectRowHeight) style="height:{{ $pdfSubjectRowHeight }}px;" @endif>
                            <td>{{ $subject->subject_code }}<br><small>{{ $subject->subject_description }}</small></td>
                            <td>
                                <div class="sig-cell">
                                    @if($subject->status === 'Approved')
                                        <span class="badge badge-approved">Approved</span>
                                    @else
                                        <span class="badge badge-pending">Pending</span>
                                    @endif
                                    @if($subject->remarks)
                                        <div style="font-size:8px;color:#888;">{{ $subject->remarks }}</div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="sig-cell">
                                    <div class="sig-name">{{ $subject->firstname }} {{ $subject->lastname }}</div>
                                    @if($subject->status === 'Approved')
                                        <span class="sig-pending">Approved by instructor</span>
                                    @else
                                        <span class="sig-pending">— awaiting clearance —</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="text-align:center;color:#aaa;font-style:italic;padding:14px;">No instructor assignments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="right-col">
            <div class="right-col-title">Offices</div>
            @foreach($offices as $office)
                <div class="office-sig-box">
                    @if($office->status === 'Approved')
                        <span class="badge badge-approved">Approved</span>
                    @else
                        <span class="badge badge-pending">Pending</span>
                    @endif
                    @if($office->officer_name)
                        <div class="office-role">{{ $office->officer_name }}</div>
                    @else
                        <div class="office-role">Officer not assigned</div>
                    @endif
                    <div class="office-name">{{ $office->label }}</div>
                    @if($office->remarks)
                        <div style="font-size:8px;color:#666;text-align:center;margin-top:2px;">{{ $office->remarks }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="bottom-sig">
        <div class="bottom-sig-box">
            @if($deanStatus === 'Approved')
                <span class="badge badge-approved">Approved</span>
            @else
                <span class="badge badge-pending">Pending</span>
            @endif
            <strong style="font-size:10.5px;text-transform:uppercase;">{{ $deanName ?? 'Program Head / College Dean not assigned' }}</strong>
            <span style="font-size:9.5px;">Program Head / College Dean</span>
        </div>
        @if($educationHeadApplies)
        {{-- BSED and BEED clear their program head first, then the department
             head above them, so it signs directly below. --}}
        <div class="bottom-sig-box" style="border-top:1px solid #111;">
            @if($educationHeadStatus === 'Approved')
                <span class="badge badge-approved">Approved</span>
            @else
                <span class="badge badge-pending">Pending</span>
            @endif
            <strong style="font-size:10.5px;text-transform:uppercase;">{{ $educationHeadName ?? 'College of Education Department Head not assigned' }}</strong>
            <span style="font-size:9.5px;">College of Education Department Head</span>
        </div>
        @endif
    </div>

    <div class="status-banner">
        Overall Clearance Status: {{ strtoupper($overallStatus) }}
    </div>

    <div class="qr-verification">
        <img src="{{ $qrCodeDataUri }}" alt="Registrar verification QR code">
        Scan for registrar verification
    </div>

    <div class="footer">
        System-generated by ClearanceMS &nbsp;|&nbsp; {{ now()->year }} Madridejos Community College
    </div>
</main>
<script src="{{ asset('js/protected-page-history.js') }}"></script>
</body>
</html>
