@extends('layouts.portal')

@section('title', 'Messages')
@section('portal-name', 'Treasurer Portal')
@section('portal-subtitle', $contextMeta)
@section('page-title', 'Messages')
@section('user-label', $contextName)
@section('user-role', $contextMeta)

@section('nav')
    <a class="nav-link" href="{{ route('treasurer.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a class="nav-link" href="{{ route('treasurer.clearance-updates') }}"><i class="bi bi-bar-chart-line me-2"></i> Clearance Updates</a>
    <a class="nav-link" href="{{ route('treasurer.submission-remark') }}"><i class="bi bi-folder2-open me-2"></i> Submission &amp; Remark</a>
    <a class="nav-link active" href="{{ route('treasurer.chat') }}"><i class="bi bi-chat-square-text me-2"></i> Messages</a>
@endsection

@section('logout-form')
    <form method="POST" action="{{ route('treasurer.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>
@endsection

@push('styles')<link href="{{ asset('css/messenger_interface.css') }}" rel="stylesheet">@endpush

@section('content')
<x-portal.messenger
    id="treasurerMessenger"
    :contacts="$contacts"
    :messages-url="route('treasurer.chat.messages')"
    :send-url="route('treasurer.chat.send')"
    :delete-url="route('treasurer.chat.destroy', ['message' => '__MESSAGE__'])"
    :heading="$heading"
    :subheading="$subheading"
    :context-icon="$contextIcon"
    :context-name="$contextName"
    :context-meta="$contextMeta"
    search-placeholder="Search student name or ID…"
    empty-message="No students fall under this treasury assignment yet."
    thread-title="Select a student"
    thread-subtitle="Student program, year level, and section will appear here."
    thread-placeholder="Choose a student to open the conversation."
    :filters="$filters"
/>
@endsection
