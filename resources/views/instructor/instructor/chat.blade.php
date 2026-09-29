@extends('instructor.layouts.instructor')
@section('title', 'Messages')
@push('styles')<link href="{{ asset('css/messenger_interface.css') }}" rel="stylesheet">
<style>
    /* This layout's wrapper is min-height based so dashboards can grow past the
       viewport. The messenger instead needs a definite height to hand its thread
       an internal scrollbar, so pin the wrapper on this page only. */
    .main > .page-content-fit { height: 100%; min-height: 0; }
</style>
@endpush

@section('content')
<x-portal.messenger
    id="instructorMessenger"
    :contacts="$contacts"
    :messages-url="route('instructor.chat.messages')"
    :send-url="route('instructor.chat.send')"
    :delete-url="route('instructor.chat.destroy', ['message' => '__MESSAGE__'])"
    :heading="$heading"
    :subheading="$subheading"
    :context-icon="$contextIcon"
    :context-name="$contextName"
    :context-meta="$contextMeta"
    search-placeholder="Search student name or ID…"
    empty-message="No students are assigned to your classes yet."
    thread-title="Select a student"
    thread-subtitle="Student program, year level, and section will appear here."
    thread-placeholder="Choose a student to open the conversation."
    :filters="$filters"
/>
@endsection
