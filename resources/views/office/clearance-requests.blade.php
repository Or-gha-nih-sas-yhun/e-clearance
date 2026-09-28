@extends('layouts.portal')

@section('title', 'Office Clearance Requests')
@section('portal-name', 'Office Portal')
@section('portal-subtitle', ucwords($officeName))
@section('page-title', ucwords($officeName) . ' Clearance Requests')
@section('user-label', $office->full_name ?? trim($office->firstname . ' ' . $office->lastname))
@section('user-role', ucwords($officeName))
@push('styles')<link href="{{ asset('css/clearance_workspace.css') }}" rel="stylesheet">@endpush

@section('nav')
    <a class="nav-link" href="{{ route('office.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a class="nav-link" href="{{ route('office.submissions') }}"><i class="bi bi-folder2-open me-2"></i> Submissions & Remark</a>
    <a class="nav-link active" href="{{ route('office.clearance.requests') }}"><i class="bi bi-bar-chart-line me-2"></i> Student Clearance Requests</a>
    <a class="nav-link" href="{{ route('office.chat') }}"><i class="bi bi-chat-square-text me-2"></i> Messages</a>
@endsection

@section('logout-form')
    <form method="POST" action="{{ route('office.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>
@endsection

@section('content')
<div class="clearance-workspace">
    @include('partials.clearance-summary', compact('pendingCount', 'approvedCount', 'totalStudents'))
    @include('partials.clearance-filters', ['action' => route('office.clearance.requests'), 'programs' => $filterPrograms, 'years' => $filterYears, 'sections' => $filterSections])
    <section class="clearance-table-card">
        <div class="clearance-table-heading"><h3>{{ ucwords($officeName) }} Clearance Requests</h3><span>{{ $requests->total() }} assigned records</span></div>
        @include('partials.clearance-bulk-toolbar', ['endpoint' => route('office.clearance.bulk-status')])
        <div class="clearance-table-wrap"><table class="clearance-table">
            <thead><tr><th class="clearance-select-cell"><input class="clearance-select" type="checkbox" data-bulk-select-all aria-label="Select all clearance records on this page"></th><th>#</th><th>Student</th><th>Program</th><th>Year Level</th><th>Section</th><th>Updated</th><th>Status</th>@if(in_array($officeName, ['library', 'guidance office'], true))<th>Evaluation</th>@endif<th>Actions</th></tr></thead>
            <tbody>
            @forelse($requests as $request)
                <tr>
                    <td class="clearance-select-cell"><input class="clearance-select" type="checkbox" data-bulk-select data-student="{{ $request->student_id }}" aria-label="Select {{ $request->firstname }} {{ $request->lastname }}"></td>
                    <td>{{ $requests->firstItem() + $loop->index }}</td>
                    <td><div class="clearance-student"><span class="clearance-avatar">{{ strtoupper(substr($request->firstname,0,1)) }}</span><div><strong>{{ $request->firstname }} {{ $request->lastname }}</strong><small>{{ $request->student_id }}</small></div></div></td>
                    <td>{{ $request->program }}</td><td>{{ $request->year_level }}</td><td>{{ $request->section }}</td>
                    <td>{{ \Carbon\Carbon::parse($request->updated_at)->format('M d, Y') }}<small class="d-block text-secondary">{{ \Carbon\Carbon::parse($request->updated_at)->format('h:i A') }}</small></td>
                    <td><span class="clearance-status {{ $request->status === 'Approved' ? 'approved' : 'pending' }}">{{ $request->status === 'Approved' ? 'Approved' : 'Pending' }}</span></td>
                    @if(in_array($officeName, ['library', 'guidance office'], true))
                        <td>
                            @if(! $officeEvaluation)
                                <span class="text-secondary small">No published evaluation</span>
                            @elseif($evaluationCompletions->has($request->student_id))
                                <span class="clearance-status approved"><i class="bi bi-check-circle me-1"></i>Completed</span>
                                <small class="d-block text-secondary">{{ \Carbon\Carbon::parse($evaluationCompletions[$request->student_id])->format('M d, Y h:i A') }}</small>
                            @else
                                <span class="clearance-status pending">Not completed</span>
                            @endif
                        </td>
                    @endif
                    <td><form method="POST" action="{{ route('office.clearance.status') }}">@csrf<input type="hidden" name="student_id" value="{{ $request->student_id }}"><div class="clearance-actions"><input type="text" name="remarks" class="clearance-remark" value="{{ $request->remarks ?? '' }}" placeholder="Add remark"><button name="status" value="Approved" class="clearance-action approve" {{ $request->status === 'Approved' ? 'disabled' : '' }}><i class="bi bi-check-lg"></i> Approve</button><button name="status" value="Pending" class="clearance-action pending"><i class="bi bi-clock"></i> Keep Pending</button></div></form></td>
                </tr>
            @empty
                <tr><td colspan="{{ in_array($officeName, ['library', 'guidance office'], true) ? 10 : 9 }}" class="clearance-empty">No clearance requests found.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="clearance-pagination"><span>Showing {{ $requests->firstItem() ?? 0 }}–{{ $requests->lastItem() ?? 0 }} of {{ $requests->total() }} records</span>{{ $requests->links() }}</div>
    </section>
</div>
@endsection
