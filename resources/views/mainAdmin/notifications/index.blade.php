@extends('mainAdmin.layouts.admin')
@section('title', 'Notifications — ClearanceMS')

@section('content')
<x-main-admin.page-header
    title="Notifications"
    description="Review system notices and clearance activity from one place."
    icon="bi bi-bell"
/>

<section class="admin-feed-card" aria-labelledby="notificationFeedHeading">
    <header class="admin-feed-toolbar">
        <div>
            <h2 id="notificationFeedHeading"><i class="bi bi-bell" aria-hidden="true"></i>Notification Center</h2>
            <p>System alerts, reminders, and recent clearance updates.</p>
        </div>
        <span class="admin-result-count">
            {{ number_format($notifications->total()) }} {{ \Illuminate\Support\Str::plural('notification', $notifications->total()) }}
        </span>
    </header>

    <div class="admin-feed-list">
        @forelse($notifications as $notification)
            @php
                $notificationType = trim((string) ($notification->notif_type ?? '')) ?: 'System notification';
                $notificationTitle = ucwords(str_replace(['_', '-'], ' ', $notificationType));
                $isRead = (int) ($notification->is_read ?? 0) === 1;
                $createdAt = filled($notification->created_at ?? null)
                    ? \Illuminate\Support\Carbon::parse($notification->created_at)
                    : null;
                $linkUrl = trim((string) ($notification->link_url ?? ''));
                $hasSafeInternalLink = str_starts_with($linkUrl, '/') && ! str_starts_with($linkUrl, '//');
            @endphp

            <article class="admin-feed-item{{ $isRead ? '' : ' is-unread' }}">
                <span class="admin-feed-icon" aria-hidden="true">
                    <i class="bi {{ $isRead ? 'bi-bell' : 'bi-bell-fill' }}"></i>
                </span>

                <div class="admin-feed-copy">
                    <h3 class="admin-feed-title">{{ $notificationTitle }}</h3>

                    <p class="admin-feed-message">{{ $notification->message }}</p>

                    <div class="admin-feed-meta">
                        @if($createdAt)
                            <time datetime="{{ $createdAt->toIso8601String() }}">
                                <i class="bi bi-clock" aria-hidden="true"></i>
                                {{ $createdAt->format('M j, Y · g:i A') }}
                            </time>
                        @endif

                        @if($hasSafeInternalLink)
                            <a class="admin-feed-link" href="{{ $linkUrl }}">
                                View related page <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                            </a>
                        @endif
                    </div>
                </div>

                <span class="admin-state-badge{{ $isRead ? '' : ' is-unread' }}">{{ $isRead ? 'Read' : 'Unread' }}</span>
            </article>
        @empty
            <div class="admin-empty-state">
                <div>
                    <i class="bi bi-bell-slash" aria-hidden="true"></i>
                    <h3>No notifications yet</h3>
                    <p>System notices and clearance updates will appear here.</p>
                </div>
            </div>
        @endforelse
    </div>

    <x-main-admin.table-pagination :paginator="$notifications" label="notifications" />
</section>
@endsection
