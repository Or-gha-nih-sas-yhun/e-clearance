@extends('layouts.portal')
@section('title', $officeLabel.' Evaluation')
@section('portal-name', 'Office Portal')
@section('portal-subtitle', $officeLabel)
@section('page-title', $officeLabel.' Evaluation')
@section('user-label', trim($office->firstname . ' ' . $office->lastname) ?: $officeLabel.' staff')
@section('user-role', $officeLabel)
@push('styles')<link href="{{ asset('css/library_evaluation.css') }}" rel="stylesheet">@endpush
@push('scripts')<script src="{{ asset('js/library-evaluation-actions.js') }}" defer></script>@endpush
@section('nav')
    <a class="nav-link" href="{{ route('office.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a class="nav-link" href="{{ route('office.submissions') }}"><i class="bi bi-folder2-open me-2"></i> Submissions & Remark</a>
    <a class="nav-link" href="{{ route('office.clearance.requests') }}"><i class="bi bi-clipboard2-check me-2"></i> Student Clearance Requests</a>
    <a class="nav-link" href="{{ route('office.chat') }}"><i class="bi bi-chat-square-text me-2"></i> Messages</a>
@endsection
@section('logout-form')
    <form method="POST" action="{{ route('office.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>
@endsection
@section('content')
<div class="evaluation-workspace">
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><strong>Please check the form.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('evaluation-content')
</div>
@endsection
