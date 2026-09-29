@props([
    'id' => 'portalMessenger',
    'contacts' => [],
    'messagesUrl',
    'sendUrl',
    'deleteUrl',
    'heading' => 'Conversations',
    'subheading' => 'Search and open a conversation.',
    'contextIcon' => 'bi bi-chat-dots',
    'contextName' => '',
    'contextMeta' => '',
    'searchPlaceholder' => 'Search name or ID…',
    'emptyMessage' => 'No conversations are available yet.',
    'threadPlaceholder' => 'Choose a conversation to start chatting.',
    'threadTitle' => 'Select a conversation',
    'threadSubtitle' => 'Conversation details will appear here.',
    'filters' => [],
])
{{--
    One messenger UI for every portal. Chat spans five portals now, so the markup,
    the polling loop and the read-marking live here instead of being copied per
    view. Each contact carries its own partner_role, which is what lets a single
    student sidebar mix instructors, offices, treasurers and the registrar.
--}}
@php
    $contacts = collect($contacts);
    $filters = collect($filters)->filter(fn ($filter) => ! empty($filter['options']));
@endphp
{{-- data-no-splash: a messenger sends and polls in place, so the loading
     splash has nothing to cover and would only get in the way. --}}
<div class="portal-messenger" id="{{ $id }}" data-messenger data-no-splash data-messages-url="{{ $messagesUrl }}" data-send-url="{{ $sendUrl }}" data-delete-url="{{ $deleteUrl }}">
    <aside class="messenger-sidebar">
        <div class="messenger-sidebar-head">
            <div class="messenger-sidebar-title"><h3>{{ $heading }}</h3><span>{{ $contacts->count() }}</span></div>
            <p>{{ $subheading }}</p>
            @if($contextName)
                <div class="messenger-context">
                    <i class="{{ $contextIcon }}"></i>
                    <div><strong>{{ $contextName }}</strong><small>{{ $contextMeta }}</small></div>
                </div>
            @endif
        </div>
        <div class="messenger-tools">
            <label class="messenger-search" for="{{ $id }}Search"><i class="bi bi-search"></i><input id="{{ $id }}Search" type="search" data-messenger-search placeholder="{{ $searchPlaceholder }}" autocomplete="off"></label>
            @if($filters->isNotEmpty())
                <div class="messenger-filter-row @if($filters->count() === 1) messenger-filter-single @endif">
                    @foreach($filters as $filter)
                        <select class="messenger-filter" data-messenger-filter="{{ $filter['key'] }}" aria-label="{{ $filter['label'] }}">
                            <option value="">{{ $filter['label'] }}</option>
                            @foreach($filter['options'] as $value => $label)
                                <option value="{{ strtolower($value) }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="messenger-contact-list">
            @forelse($contacts as $contact)
                @php
                    $contact = (object) $contact;
                    $unread = (int) ($contact->unread ?? 0);
                    $preview = trim((string) ($contact->preview ?? '')) ?: (string) ($contact->title ?? '');
                    $meta = collect($contact->meta ?? [])->filter()->values();
                @endphp
                <button class="messenger-contact" type="button"
                    data-contact
                    data-id="{{ $contact->id }}"
                    data-role="{{ $contact->role }}"
                    data-name="{{ $contact->name }}"
                    data-title="{{ $contact->title ?? '' }}"
                    data-group="{{ strtolower((string) ($contact->group ?? '')) }}"
                    data-program="{{ strtolower((string) ($contact->program ?? '')) }}"
                    data-year="{{ strtolower((string) ($contact->year_level ?? '')) }}"
                    data-section="{{ strtolower((string) ($contact->section ?? '')) }}"
                    data-search="{{ strtolower(implode(' ', array_filter([$contact->name ?? '', $contact->id, $contact->title ?? '', $contact->group ?? '', ...$meta->all()]))) }}">
                    <span class="messenger-avatar">{{ strtoupper(substr((string) ($contact->name ?: $contact->id), 0, 1)) }}</span>
                    <span class="messenger-contact-copy">
                        <span class="messenger-contact-line"><strong>{{ $contact->name }}</strong>@if($unread)<span class="messenger-unread">{{ $unread }}</span>@endif</span>
                        <small>{{ \Illuminate\Support\Str::limit($preview, 46) }}</small>
                        @if($meta->isNotEmpty())
                            <span class="messenger-contact-meta">@foreach($meta as $item)<span>{{ $item }}</span>@endforeach</span>
                        @endif
                    </span>
                </button>
            @empty
                <div class="messenger-list-empty"><i class="bi bi-person-x"></i>{{ $emptyMessage }}</div>
            @endforelse
            <div class="messenger-list-empty d-none" data-messenger-no-results><i class="bi bi-search"></i>No conversations match your search and filters.</div>
        </div>
    </aside>

    <section class="messenger-chat">
        <header class="messenger-chat-head">
            <div class="messenger-chat-person">
                <button class="messenger-back" type="button" data-messenger-back aria-label="Back to conversations"><i class="bi bi-arrow-left"></i></button>
                <span class="messenger-avatar" data-messenger-avatar><i class="bi bi-chat-dots"></i></span>
                <div><h3 data-messenger-title>{{ $threadTitle }}</h3><p data-messenger-subtitle>{{ $threadSubtitle }}</p></div>
            </div>
            <span class="messenger-online">Messaging available</span>
        </header>
        <div class="messenger-thread" data-messenger-thread>
            <div class="messenger-empty-thread"><i class="bi bi-chat-square-text"></i>{{ $threadPlaceholder }}</div>
        </div>
        <form class="messenger-composer" data-messenger-composer>
            <textarea rows="1" maxlength="2000" placeholder="Write a message…" data-messenger-input disabled></textarea>
            <button class="messenger-send" type="submit" data-messenger-send disabled aria-label="Send message"><i class="bi bi-send-fill"></i></button>
        </form>
    </section>
</div>

@once
@push('scripts')
<script>
// Drives every portal messenger on the page. Reads its endpoints from the
// element's data attributes so the same loop serves the student sidebar (which
// mixes four staff portals) and each staff sidebar (students only).
document.querySelectorAll('[data-messenger]').forEach(messenger => {
    const messagesUrl = messenger.dataset.messagesUrl;
    const sendUrl = messenger.dataset.sendUrl;
    const deleteUrlTemplate = messenger.dataset.deleteUrl;
    const contacts = [...messenger.querySelectorAll('[data-contact]')];
    const search = messenger.querySelector('[data-messenger-search]');
    const filters = [...messenger.querySelectorAll('[data-messenger-filter]')];
    const noResults = messenger.querySelector('[data-messenger-no-results]');
    const thread = messenger.querySelector('[data-messenger-thread]');
    const title = messenger.querySelector('[data-messenger-title]');
    const subtitle = messenger.querySelector('[data-messenger-subtitle]');
    const avatar = messenger.querySelector('[data-messenger-avatar]');
    const input = messenger.querySelector('[data-messenger-input]');
    const sendButton = messenger.querySelector('[data-messenger-send]');
    let active = null;
    let lastMessageId = 0;
    let pollTimer = null;
    let pollCount = 0;
    let loading = false;
    let rendered = new Set();

    const chatFetch = (url, options = {}) => fetch(url, {
        ...options,
        headers: {
            ...(options.headers || {}),
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            Accept: 'application/json',
        },
    });

    const filterContacts = () => {
        const query = (search?.value || '').trim().toLowerCase();
        let visible = 0;
        contacts.forEach(contact => {
            const matchesFilters = filters.every(filter => !filter.value || contact.dataset[filter.dataset.messengerFilter] === filter.value);
            const matches = (!query || contact.dataset.search.includes(query)) && matchesFilters;
            contact.hidden = !matches;
            if (matches) visible++;
        });
        noResults?.classList.toggle('d-none', visible !== 0 || contacts.length === 0);
    };

    // An opened thread with no history used to leave the panel completely blank,
    // which reads as "the messages are not showing" rather than "there are none".
    const emptyThread = () => {
        thread.innerHTML = '';
        const empty = document.createElement('div');
        empty.className = 'messenger-empty-thread';
        empty.dataset.messengerThreadEmpty = '';
        empty.innerHTML = '<i class="bi bi-chat-dots"></i>';
        empty.append('No messages in this conversation yet. Write the first one below.');
        thread.appendChild(empty);
    };

    const updateActivePreview = () => {
        if (!active) return;
        const bubbles = [...thread.querySelectorAll('[data-message-id]')];
        const latest = bubbles.at(-1)?.querySelector('[data-message-copy]')?.textContent || active.dataset.title || '';
        const preview = active.querySelector('.messenger-contact-copy small');
        if (preview) preview.textContent = latest;
    };

    const deleteMessage = async (message, bubble, button) => {
        if (!message.mine || !deleteUrlTemplate || !window.showConfirmationModal) return;
        const confirmed = await window.showConfirmationModal({
            tone: 'warning',
            title: 'Delete message?',
            message: 'This message will be permanently removed from the conversation.',
            confirmText: 'Delete message',
        });
        if (!confirmed) return;

        button.disabled = true;
        try {
            const response = await chatFetch(deleteUrlTemplate.replace('__MESSAGE__', encodeURIComponent(message.id)), { method: 'DELETE' });
            if (!response.ok) throw new Error('Delete request failed');

            rendered.delete(message.id);
            bubble.remove();
            if (!thread.querySelector('[data-message-id]')) emptyThread();
            updateActivePreview();
        } catch (error) {
            button.disabled = false;
            if (window.showFeedbackModal) {
                window.showFeedbackModal({ tone: 'danger', title: 'Message not deleted', message: 'The message may already be removed. Refresh the conversation and try again.' });
            }
        }
    };

    const appendMessage = message => {
        thread.querySelector('[data-messenger-thread-empty]')?.remove();
        const bubble = document.createElement('div');
        bubble.className = `messenger-bubble ${message.mine ? 'mine' : 'theirs'}`;
        bubble.dataset.messageId = message.id;
        const copy = document.createElement('span');
        copy.className = 'messenger-message-copy';
        copy.dataset.messageCopy = '';
        copy.textContent = message.message;
        const meta = document.createElement('div');
        meta.className = 'messenger-message-meta';
        const time = document.createElement('div');
        time.className = 'messenger-message-time';
        time.textContent = message.time_fmt || '';
        meta.appendChild(time);
        if (message.mine && deleteUrlTemplate) {
            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'messenger-message-delete';
            deleteButton.setAttribute('aria-label', 'Delete this message');
            deleteButton.title = 'Delete message';
            deleteButton.innerHTML = '<i class="bi bi-trash3"></i>';
            deleteButton.addEventListener('click', () => deleteMessage(message, bubble, deleteButton));
            meta.appendChild(deleteButton);
        }
        bubble.append(copy, meta);
        thread.appendChild(bubble);
    };

    const loadMessages = async (sync = false) => {
        if (!active || loading) return;
        loading = true;
        try {
            const nearBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 80;
            const query = new URLSearchParams({ with: active.dataset.id, partner_role: active.dataset.role, since: sync ? 0 : lastMessageId });
            if (sync) query.set('sync', '1');
            const response = await chatFetch(`${messagesUrl}?${query}`);
            if (!response.ok) return;
            const rows = await response.json();
            if (sync) {
                const currentIds = new Set(rows.map(message => Number(message.id)));
                thread.querySelectorAll('[data-message-id]').forEach(bubble => {
                    const id = Number(bubble.dataset.messageId);
                    if (!currentIds.has(id)) {
                        bubble.remove();
                        rendered.delete(id);
                    }
                });
            }
            let appended = false;
            rows.forEach(message => {
                if (rendered.has(message.id)) return;
                rendered.add(message.id);
                appendMessage(message);
                appended = true;
                lastMessageId = Math.max(lastMessageId, message.id);
            });
            if (sync) {
                rendered = new Set(rows.map(message => Number(message.id)));
                lastMessageId = rows.reduce((maximum, message) => Math.max(maximum, Number(message.id)), 0);
                if (!rows.length) emptyThread();
                updateActivePreview();
            }
            if (appended && nearBottom) thread.scrollTop = thread.scrollHeight;
        } finally {
            loading = false;
        }
    };

    const openThread = contact => {
        contacts.forEach(item => item.classList.remove('active'));
        contact.classList.add('active');
        contact.querySelector('.messenger-unread')?.remove();
        active = contact;
        lastMessageId = 0;
        pollCount = 0;
        rendered = new Set();
        title.textContent = contact.dataset.name;
        subtitle.textContent = [contact.dataset.title, contact.dataset.id].filter(Boolean).join(' · ');
        avatar.textContent = (contact.dataset.name || contact.dataset.id).charAt(0).toUpperCase();
        emptyThread();
        input.disabled = false;
        sendButton.disabled = false;
        messenger.classList.add('is-thread-open');
        loadMessages(true);
        clearInterval(pollTimer);
        pollTimer = setInterval(() => {
            pollCount += 1;
            loadMessages(pollCount % 5 === 0);
        }, 3500);
        window.setTimeout(() => input.focus(), 0);
    };

    const sendMessage = async () => {
        const message = input.value.trim();
        if (!message || !active) return;
        input.value = '';
        const response = await chatFetch(sendUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ receiver_id: active.dataset.id, partner_role: active.dataset.role, message }),
        });
        if (response.ok) {
            loadMessages();
        } else if (window.showFeedbackModal) {
            window.showFeedbackModal({ tone: 'danger', title: 'Message not sent', message: 'Please try sending your message again.' });
        }
    };

    contacts.forEach(contact => contact.addEventListener('click', () => openThread(contact)));
    search?.addEventListener('input', filterContacts);
    filters.forEach(filter => filter.addEventListener('change', filterContacts));
    messenger.querySelector('[data-messenger-composer]').addEventListener('submit', event => { event.preventDefault(); sendMessage(); });
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendMessage(); }
    });
    messenger.querySelector('[data-messenger-back]')?.addEventListener('click', () => messenger.classList.remove('is-thread-open'));
    window.addEventListener('beforeunload', () => clearInterval(pollTimer));
});
</script>
@endpush
@endonce
