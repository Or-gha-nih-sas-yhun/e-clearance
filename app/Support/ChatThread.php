<?php

namespace App\Support;

use App\Models\ChatMessage;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reading and writing one conversation, whichever two portals it spans.
 *
 * chat_messages was already role-aware — every row carries a sender_role and a
 * receiver_role — but each portal used to hard-code its own half of the pair.
 * Centralising it here means a thread renders and marks itself read identically
 * from the student side and the staff side, and there is one place that decides
 * what the recipient's notification says.
 *
 * A participant is a plain ['role' => …, 'id' => …] pair; authorization is not
 * this class's job, callers gate on App\Support\ChatDirectory first.
 */
final class ChatThread
{
    /** Where a "new message" notification should land, per recipient portal. */
    private const INBOX_ROUTES = [
        'student' => 'student.chat-support',
        'instructor' => 'instructor.chat',
        'office' => 'office.chat',
        'treasurer' => 'treasurer.chat',
        'registrar' => 'registrar.chat',
    ];

    /**
     * Messages exchanged by the two participants after `$since`, marking the
     * partner's side of the thread read as a side effect — opening a thread is
     * what "reading" means in this UI.
     *
     * @param  array{role: string, id: string}  $viewer
     * @param  array{role: string, id: string}  $partner
     * @return Collection<int, array<string, mixed>>
     */
    public function messages(array $viewer, array $partner, int $since = 0, int $limit = 100, bool $latest = false): Collection
    {
        $this->markRead($viewer, $partner);

        $messages = ChatMessage::query()
            ->where(fn (Builder $thread) => $this->scopeThread($thread, $viewer, $partner))
            ->where('id', '>', $since)
            ->orderBy('id', $latest ? 'desc' : 'asc')
            ->limit($limit)
            ->get();

        if ($latest) {
            $messages = $messages->reverse()->values();
        }

        return $messages
            ->map(fn (ChatMessage $message) => [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'sender_role' => $message->sender_role,
                'mine' => $message->sender_role === $viewer['role'] && (string) $message->sender_id === $viewer['id'],
                'message' => $message->message,
                'is_read' => (int) $message->is_read,
                'time_fmt' => $message->created_at?->format('M d, Y g:i A'),
            ]);
    }

    /**
     * Permanently removes a message only when the authenticated participant is
     * its sender. Matching both the role and identifier prevents equal-looking
     * IDs from another portal from claiming the same message.
     *
     * @param  array{role: string, id: string}  $sender
     */
    public function delete(array $sender, int $messageId): bool
    {
        return ChatMessage::query()
            ->whereKey($messageId)
            ->where('sender_role', $sender['role'])
            ->where('sender_id', $sender['id'])
            ->delete() === 1;
    }

    /**
     * @param  array{role: string, id: string}  $sender
     * @param  array{role: string, id: string}  $partner
     */
    public function send(array $sender, array $partner, string $message, string $senderName = ''): ChatMessage
    {
        $created = ChatMessage::create([
            'sender_id' => $sender['id'],
            'sender_role' => $sender['role'],
            'receiver_id' => $partner['id'],
            'receiver_role' => $partner['role'],
            'message' => $message,
        ]);

        $this->notify($sender, $partner, $message, $senderName);

        return $created;
    }

    /** Contact-list keys mix portals, so a partner is identified by role *and* id. */
    public static function key(string $role, string|int $id): string
    {
        return $role.'|'.$id;
    }

    /**
     * Unread counts for every partner the viewer has, keyed by role|id. One query
     * covers a student sidebar that mixes four staff portals.
     *
     * @param  array{role: string, id: string}  $viewer
     * @return Collection<string, int>
     */
    public function unreadByPartner(array $viewer): Collection
    {
        return ChatMessage::query()
            ->where('receiver_role', $viewer['role'])
            ->where('receiver_id', $viewer['id'])
            ->where('is_read', 0)
            ->selectRaw('sender_role, sender_id, COUNT(*) as total')
            ->groupBy('sender_role', 'sender_id')
            ->get()
            ->mapWithKeys(fn ($row) => [self::key((string) $row->sender_role, (string) $row->sender_id) => (int) $row->total]);
    }

    /**
     * The most recent message exchanged with each partner, keyed by role|id, used
     * to preview and order the contact list.
     *
     * @param  array{role: string, id: string}  $viewer
     * @return Collection<string, array{message: string, at: string|null, sort: int}>
     */
    public function latestByPartner(array $viewer): Collection
    {
        // Grouping by all four participant columns keeps this portable across the
        // MySQL the portals run on and the SQLite the test suite builds; the two
        // directions of a thread arrive as separate groups and are folded below.
        $latestIds = ChatMessage::query()
            ->selectRaw('sender_role, sender_id, receiver_role, receiver_id, MAX(id) as last_id')
            ->where(fn (Builder $sent) => $sent->where('sender_role', $viewer['role'])->where('sender_id', $viewer['id']))
            ->orWhere(fn (Builder $received) => $received->where('receiver_role', $viewer['role'])->where('receiver_id', $viewer['id']))
            ->groupBy('sender_role', 'sender_id', 'receiver_role', 'receiver_id')
            ->get()
            ->reduce(function (array $carry, $row) use ($viewer) {
                $sentByViewer = $row->sender_role === $viewer['role'] && (string) $row->sender_id === $viewer['id'];
                $key = self::key(
                    $sentByViewer ? (string) $row->receiver_role : (string) $row->sender_role,
                    $sentByViewer ? (string) $row->receiver_id : (string) $row->sender_id,
                );
                $carry[$key] = max($carry[$key] ?? 0, (int) $row->last_id);

                return $carry;
            }, []);

        if ($latestIds === []) {
            return collect();
        }

        return ChatMessage::whereIn('id', array_values($latestIds))->get()
            ->mapWithKeys(function (ChatMessage $message) use ($viewer) {
                $sentByViewer = $message->sender_role === $viewer['role'] && (string) $message->sender_id === $viewer['id'];

                return [self::key(
                    $sentByViewer ? (string) $message->receiver_role : (string) $message->sender_role,
                    $sentByViewer ? (string) $message->receiver_id : (string) $message->sender_id,
                ) => [
                    'message' => (string) $message->message,
                    'at' => $message->created_at?->format('M d, g:i A'),
                    'sort' => (int) ($message->created_at?->timestamp ?? 0),
                ]];
            });
    }

    /**
     * @param  array{role: string, id: string}  $viewer
     * @param  array{role: string, id: string}  $partner
     */
    private function markRead(array $viewer, array $partner): void
    {
        ChatMessage::query()
            ->where('sender_role', $partner['role'])
            ->where('sender_id', $partner['id'])
            ->where('receiver_role', $viewer['role'])
            ->where('receiver_id', $viewer['id'])
            ->where('is_read', 0)
            ->update(['is_read' => 1]);
    }

    /**
     * @param  array{role: string, id: string}  $sender
     * @param  array{role: string, id: string}  $partner
     */
    private function notify(array $sender, array $partner, string $message, string $senderName): void
    {
        $name = trim($senderName) !== '' ? trim($senderName) : PortalAccounts::label($sender['role']);
        $route = self::INBOX_ROUTES[$partner['role']] ?? null;

        Notification::create([
            'user_id' => $partner['id'],
            'recipient_role' => $partner['role'],
            'message' => "💬 New message from {$name}: \"".Str::limit($message, 80).'"',
            'notif_type' => 'message',
            'link_url' => $route ? route($route) : null,
        ]);
    }

    /**
     * Both directions of one conversation.
     *
     * @param  array{role: string, id: string}  $viewer
     * @param  array{role: string, id: string}  $partner
     */
    private function scopeThread(Builder $query, array $viewer, array $partner): Builder
    {
        return $query
            ->where(fn (Builder $sent) => $sent
                ->where('sender_role', $viewer['role'])->where('sender_id', $viewer['id'])
                ->where('receiver_role', $partner['role'])->where('receiver_id', $partner['id']))
            ->orWhere(fn (Builder $received) => $received
                ->where('sender_role', $partner['role'])->where('sender_id', $partner['id'])
                ->where('receiver_role', $viewer['role'])->where('receiver_id', $viewer['id']));
    }
}
