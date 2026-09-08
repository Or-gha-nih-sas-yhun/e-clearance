@extends('mainAdmin.layouts.admin')
@section('title', 'Chat Support — ClearanceMS')

@section('content')
<x-main-admin.page-header
    title="Chat Support"
    description="Review support messages submitted across the clearance portals."
    icon="bi bi-chat-square-text"
/>

<section class="admin-feed-card" aria-labelledby="supportMessagesHeading">
    <header class="admin-feed-toolbar">
        <div>
            <h2 id="supportMessagesHeading"><i class="bi bi-inboxes" aria-hidden="true"></i>Support Messages</h2>
            <p>Latest conversations and requests from portal users.</p>
        </div>
        <span class="admin-result-count">
            {{ number_format($messages->total()) }} {{ \Illuminate\Support\Str::plural('message', $messages->total()) }}
        </span>
    </header>

    <div class="admin-feed-list">
        @forelse($messages as $chatMessage)
            @php
                $senderRole = trim((string) ($chatMessage->sender_role ?? '')) ?: 'Portal user';
                $senderId = trim((string) ($chatMessage->sender_id ?? '')) ?: 'Unknown ID';
                $messageStatus = trim((string) ($chatMessage->status ?? '')) ?: 'Received';
                $createdAt = filled($chatMessage->created_at ?? null)
                    ? \Illuminate\Support\Carbon::parse($chatMessage->created_at)
                    : null;
            @endphp

            <article class="admin-feed-item{{ strtolower($messageStatus) === 'unread' ? ' is-unread' : '' }}">
                <span class="admin-feed-icon" aria-hidden="true">
                    <i class="bi bi-chat-left-text"></i>
                </span>

                <div class="admin-feed-copy">
                    <h3 class="admin-feed-title">
                        {{ ucwords(str_replace(['_', '-'], ' ', $senderRole)) }} · {{ $senderId }}
                    </h3>

                    <p class="admin-feed-message">{{ $chatMessage->message }}</p>

                    <div class="admin-feed-meta">
                        <span><i class="bi bi-person-badge" aria-hidden="true"></i> {{ $senderRole }}</span>
                        @if($createdAt)
                            <time datetime="{{ $createdAt->toIso8601String() }}">
                                <i class="bi bi-clock" aria-hidden="true"></i>
                                {{ $createdAt->format('M j, Y · g:i A') }}
                            </time>
                        @endif
                    </div>
                </div>

                <span class="admin-state-badge{{ strtolower($messageStatus) === 'unread' ? ' is-unread' : '' }}">{{ ucwords(str_replace(['_', '-'], ' ', $messageStatus)) }}</span>
            </article>
        @empty
            <div class="admin-empty-state">
                <div>
                    <i class="bi bi-chat-square" aria-hidden="true"></i>
                    <h3>No support messages yet</h3>
                    <p>New conversations will appear here when users contact support.</p>
                </div>
            </div>
        @endforelse
    </div>

    <x-main-admin.table-pagination :paginator="$messages" label="messages" />
</section>
@endsection
