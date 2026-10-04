@extends('layouts.portal')

@section('title', 'Student Clearance')
@section('portal-name', 'Registrar Portal')
@section('portal-subtitle', 'Clearance Management')
@section('page-title', 'Student Clearance')
@section('user-label', $registrar->full_name ?? $registrar->email)
@section('user-role', 'Registrar')
@push('styles')<link href="{{ asset('css/clearance_workspace.css') }}?v={{ filemtime(public_path('css/clearance_workspace.css')) }}" rel="stylesheet"><link href="{{ asset('css/clearance_document_viewer.css') }}" rel="stylesheet">@endpush

@section('nav')
    <a class="nav-link" href="{{ route('registrar.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a class="nav-link active" href="{{ route('registrar.student-clearance') }}"><i class="bi bi-bar-chart-line me-2"></i> Student Clearance</a>
    <a class="nav-link" href="{{ route('registrar.qr-scanner') }}"><i class="bi bi-qr-code-scan me-2"></i> QR Code Scanner</a>
    <a class="nav-link" href="{{ route('registrar.chat') }}"><i class="bi bi-chat-square-text me-2"></i> Messages</a>
@endsection

@section('logout-form')
    <form method="POST" action="{{ route('registrar.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>
@endsection

@section('content')
<div class="clearance-workspace">
    @include('partials.clearance-summary', compact('pendingCount', 'approvedCount', 'totalStudents'))
    @include('partials.clearance-filters', ['action' => route('registrar.student-clearance'), 'programs' => $filterPrograms, 'years' => $filterYears, 'sections' => $filterSections])
    <section class="clearance-table-card">
        <div class="clearance-table-heading"><h3>Student Clearance Requests</h3><span>{{ $clearanceRequests->total() }} records</span></div>
        @include('partials.clearance-bulk-toolbar', ['endpoint' => route('registrar.student-clearance.bulk-status')])
        <div class="clearance-table-wrap"><table class="clearance-table">
            <thead><tr><th class="clearance-select-cell"><input class="clearance-select" type="checkbox" data-bulk-select-all aria-label="Select all clearance records on this page"></th><th>#</th><th>Student</th><th>Program</th><th>Year Level</th><th>Section</th><th>Date Requested</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($clearanceRequests as $request)
                <tr>
                    <td class="clearance-select-cell"><input class="clearance-select" type="checkbox" data-bulk-select data-student="{{ $request->student_id }}" aria-label="Select {{ $request->firstname }} {{ $request->lastname }}"></td>
                    <td>{{ $clearanceRequests->firstItem() + $loop->index }}</td>
                    <td><div class="clearance-student"><span class="clearance-avatar">{{ strtoupper(substr($request->firstname,0,1)) }}</span><div><strong>{{ $request->firstname }} {{ $request->lastname }}</strong><small>{{ $request->student_id }}</small></div></div></td>
                    <td>{{ $request->program }}</td><td>{{ $request->year_level }}</td><td>{{ $request->section }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($request->requested_at)->format('M d, Y') }}<small class="d-block text-secondary">{{ \Illuminate\Support\Carbon::parse($request->requested_at)->format('h:i A') }}</small></td>
                    <td><span class="clearance-status {{ $request->status === 'Approved' ? 'approved' : 'pending' }}">{{ $request->status === 'Approved' ? 'Approved' : 'Pending' }}</span></td>
                    <td><div class="clearance-actions"><button type="button" class="clearance-action secondary" data-clearance-form-open data-clearance-form-src="{{ route('registrar.clearance.form', [$request->student_id, 'embed' => 1]) }}" data-clearance-form-title="Official Clearance Form" data-clearance-form-subtitle="{{ $request->firstname }} {{ $request->lastname }} · {{ $request->student_id }}"><i class="bi bi-eye"></i> Form</button><form method="POST" action="{{ route('registrar.student-clearance.status') }}">@csrf<input type="hidden" name="student_id" value="{{ $request->student_id }}"><button name="status" value="Approved" class="clearance-action approve" {{ $request->status === 'Approved' ? 'disabled' : '' }}><i class="bi bi-check-lg"></i> Approve</button></form><form method="POST" action="{{ route('registrar.student-clearance.status') }}">@csrf<input type="hidden" name="student_id" value="{{ $request->student_id }}"><button name="status" value="Pending" class="clearance-action pending" {{ $request->status !== 'Approved' ? 'disabled' : '' }}><i class="bi bi-clock"></i> Pending</button></form></div></td>
                </tr>
            @empty
                <tr><td colspan="9" class="clearance-empty">No clearance requests found.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="clearance-pagination"><span>Showing {{ $clearanceRequests->firstItem() ?? 0 }}–{{ $clearanceRequests->lastItem() ?? 0 }} of {{ $clearanceRequests->total() }} records</span>{{ $clearanceRequests->links() }}</div>
    </section>
</div>

<x-portal.document-viewer subtitle="Select a student to view their clearance" />
@endsection
