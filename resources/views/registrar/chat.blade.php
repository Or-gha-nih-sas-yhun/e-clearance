@extends('layouts.portal')

@section('title', 'Messages')
@section('portal-name', 'Registrar Portal')
@section('portal-subtitle', 'Clearance Management')
@section('page-title', 'Messages')
@section('user-label', $contextName)
@section('user-role', 'Registrar')

@section('nav')
    <a class="nav-link" href="{{ route('registrar.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a class="nav-link" href="{{ route('registrar.student-clearance') }}"><i class="bi bi-bar-chart-line me-2"></i> Student Clearance</a>
    <a class="nav-link" href="{{ route('registrar.qr-scanner') }}"><i class="bi bi-qr-code-scan me-2"></i> QR Code Scanner</a>
    <a class="nav-link active" href="{{ route('registrar.chat') }}"><i class="bi bi-chat-square-text me-2"></i> Messages</a>
@endsection

@section('logout-form')
    <form method="POST" action="{{ route('registrar.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>
@endsection

@push('styles')<link href="{{ asset('css/messenger_interface.css') }}" rel="stylesheet">@endpush

@section('content')
<x-portal.messenger
    id="registrarMessenger"
    :contacts="$contacts"
    :messages-url="route('registrar.chat.messages')"
    :send-url="route('registrar.chat.send')"
    :delete-url="route('registrar.chat.destroy', ['message' => '__MESSAGE__'])"
    :heading="$heading"
    :subheading="$subheading"
    :context-icon="$contextIcon"
    :context-name="$contextName"
    :context-meta="$contextMeta"
    search-placeholder="Search student name or ID…"
    empty-message="No student records are available yet."
    thread-title="Select a student"
    thread-subtitle="Student program, year level, and section will appear here."
    thread-placeholder="Choose a student to open the conversation."
    :filters="$filters"
/>
@endsection
