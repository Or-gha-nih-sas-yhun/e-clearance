@extends('layouts.portal')

@section('title', 'Messages')
@section('portal-name', 'Office Portal')
@section('portal-subtitle', $contextMeta)
@section('page-title', 'Messages')
@section('user-label', $contextName)
@section('user-role', $contextMeta)

@section('nav')
    <a class="nav-link" href="{{ route('office.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a class="nav-link" href="{{ route('office.submissions') }}"><i class="bi bi-folder2-open me-2"></i> Submissions & Remark</a>
    <a class="nav-link" href="{{ route('office.clearance.requests') }}"><i class="bi bi-clipboard2-check me-2"></i> Student Clearance Requests</a>
    <a class="nav-link active" href="{{ route('office.chat') }}"><i class="bi bi-chat-square-text me-2"></i> Messages</a>
@endsection

@section('logout-form')
    <form method="POST" action="{{ route('office.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>
@endsection

@push('styles')<link href="{{ asset('css/messenger_interface.css') }}" rel="stylesheet">@endpush

@section('content')
<x-portal.messenger
    id="officeMessenger"
    :contacts="$contacts"
    :messages-url="route('office.chat.messages')"
    :send-url="route('office.chat.send')"
    :delete-url="route('office.chat.destroy', ['message' => '__MESSAGE__'])"
    :heading="$heading"
    :subheading="$subheading"
    :context-icon="$contextIcon"
    :context-name="$contextName"
    :context-meta="$contextMeta"
    search-placeholder="Search student name or ID…"
    empty-message="No students fall under this office yet."
    thread-title="Select a student"
    thread-subtitle="Student program, year level, and section will appear here."
    thread-placeholder="Choose a student to open the conversation."
    :filters="$filters"
/>
@endsection
