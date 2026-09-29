@extends('layouts.portal')
@section('theme-body-class', 'student-portal-theme')
@section('title', 'Messages')
@section('portal-name', 'Student Portal')
@section('portal-subtitle', $student->student_id)
@section('page-title', 'Messages')
@section('user-label', $student->full_name . ' · ' . $student->program . ' ' . $student->year_level . '-' . $student->section)
@section('user-role', 'Student')
@section('nav')
<a class="nav-link" href="{{ route('student.dashboard') }}"><i class="bi bi-grid-1x2"></i> Dashboard</a><a class="nav-link" href="{{ route('student.subjects.index') }}"><i class="bi bi-journal-bookmark"></i> My Subjects</a><a class="nav-link" href="{{ route('student.clearance-updates') }}"><i class="bi bi-clipboard2-check"></i> Clearance Updates</a><a class="nav-link" href="{{ route('student.submission-remark') }}"><i class="bi bi-file-earmark-arrow-up"></i> Submission & Remark</a><a class="nav-link active" href="{{ route('student.chat-support') }}"><i class="bi bi-chat-square-text"></i> Chat Support</a>
@endsection
@section('logout-form')<form method="POST" action="{{ route('student.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>@endsection
@push('styles')<link href="{{ asset('css/messenger_interface.css') }}" rel="stylesheet">@endpush
@section('content')
@php($studentClass = $student->program . ' · Year ' . $student->year_level . ' · Section ' . $student->section)
<x-portal.messenger
    id="studentMessenger"
    :contacts="$contacts"
    :messages-url="route('student.chat.messages')"
    :send-url="route('student.chat.send')"
    :delete-url="route('student.chat.destroy', ['message' => '__MESSAGE__'])"
    heading="Clearance messages"
    subheading="Chat privately with your instructors, offices, treasurers, and the registrar."
    context-icon="bi bi-mortarboard"
    :context-name="$student->full_name"
    :context-meta="$studentClass"
    search-placeholder="Search name, office, or department…"
    empty-message="No instructor, office, treasurer, or registrar account is assigned to you yet."
    thread-title="Select a contact"
    :thread-subtitle="$studentClass"
    thread-placeholder="Choose a contact to start chatting."
    :filters="[['key' => 'group', 'label' => 'All portals', 'options' => $portalFilter]]"
/>
@endsection
