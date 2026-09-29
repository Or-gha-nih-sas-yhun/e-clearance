<?php

namespace App\Http\Controllers;

use App\Models\StudentAccount;
use App\Support\ChatDirectory;
use App\Support\ChatThread;
use App\Support\PortalAccounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The staff half of cross-role chat.
 *
 * Instructors, office personnel, both treasurers and the registrar all message
 * students through the same three endpoints, differing only in guard, view and
 * which students they are scoped to — so they share this base and each portal
 * subclass stays a handful of lines. Scoping and authorization are decided by
 * App\Support\ChatDirectory, never here.
 */
abstract class StaffChatController extends Controller
{
    public function __construct(
        protected ChatDirectory $directory = new ChatDirectory,
        protected ChatThread $thread = new ChatThread,
    ) {}

    /** Guard name, which doubles as the role stored on every chat_messages row. */
    abstract protected function guard(): string;

    abstract protected function view(): string;

    protected function subheading(): string
    {
        return 'Search and message the students you are assigned to.';
    }

    public function index()
    {
        $staff = $this->staff();
        $viewer = $this->viewer($staff);

        $unread = $this->thread->unreadByPartner($viewer);
        $latest = $this->thread->latestByPartner($viewer);

        $contacts = $this->directory->studentContactsFor($this->guard(), $staff)
            ->map(function (object $contact) use ($unread, $latest) {
                $key = ChatThread::key($contact->role, $contact->id);
                $contact->unread = $unread->get($key, 0);
                $contact->preview = $latest->get($key)['message'] ?? $contact->title;
                $contact->sort = $latest->get($key)['sort'] ?? 0;

                return $contact;
            })
            ->sortByDesc('sort')
            ->values();

        return view($this->view(), [
            'contacts' => $contacts,
            'totalUnread' => (int) $contacts->sum('unread'),
            'filters' => $this->filters($contacts),
            'heading' => 'Student conversations',
            'subheading' => $this->subheading(),
            'contextName' => $this->staffName($staff),
            'contextMeta' => $this->directory->staffTitle($this->guard(), $staff),
            'contextIcon' => PortalAccounts::icon($this->guard()),
        ]);
    }

    public function messages(Request $request): JsonResponse
    {
        $staff = $this->staff();
        $student = $this->authorizedStudent($staff, (string) $request->query('with', ''));

        return response()->json($this->thread->messages(
            $this->viewer($staff),
            ['role' => 'student', 'id' => (string) $student->student_id],
            $request->boolean('sync') ? 0 : (int) $request->query('since', 0),
            100,
            $request->boolean('sync'),
        ));
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'receiver_id' => ['required', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $staff = $this->staff();
        $student = $this->authorizedStudent($staff, $data['receiver_id']);

        $message = $this->thread->send(
            $this->viewer($staff),
            ['role' => 'student', 'id' => (string) $student->student_id],
            $data['message'],
            $this->staffName($staff),
        );

        return response()->json(['success' => true, 'id' => $message->id]);
    }

    public function destroy(int $message): JsonResponse
    {
        $staff = $this->staff();

        abort_unless($this->thread->delete($this->viewer($staff), $message), 404);

        return response()->json(['success' => true]);
    }

    /** Aborts unless the directory says this staff account may message the student. */
    protected function authorizedStudent(object $staff, string $studentId): StudentAccount
    {
        $student = trim($studentId) === '' ? null : StudentAccount::where('student_id', $studentId)->first();

        abort_unless($student && $this->directory->permits($student, $this->guard(), $staff), 403);

        return $student;
    }

    protected function staff(): object
    {
        $staff = Auth::guard($this->guard())->user();

        abort_unless($staff !== null, 403);

        return $staff;
    }

    /** @return array{role: string, id: string} */
    protected function viewer(object $staff): array
    {
        $key = PortalAccounts::PORTALS[$this->guard()]['key'];

        return ['role' => $this->guard(), 'id' => (string) ($staff->{$key} ?? $staff->getAuthIdentifier())];
    }

    protected function staffName(object $staff): string
    {
        return trim(($staff->firstname ?? '').' '.($staff->lastname ?? ''))
            ?: trim((string) ($staff->name ?? ''))
            ?: PortalAccounts::label($this->guard());
    }

    /**
     * Program, year and section dropdowns, built from whoever is actually in the
     * list — an office serving one program should not offer four others.
     *
     * @param  Collection<int, object>  $contacts
     * @return array<int, array{key: string, label: string, options: array<string, string>}>
     */
    protected function filters(Collection $contacts): array
    {
        $options = fn (string $field, callable $label) => $contacts
            ->pluck($field)->filter()->unique()->sort()
            ->mapWithKeys(fn ($value) => [strtolower((string) $value) => $label($value)])
            ->all();

        return collect([
            ['key' => 'program', 'label' => 'All programs', 'options' => $options('program', fn ($value) => $value)],
            ['key' => 'year', 'label' => 'All year levels', 'options' => $options('year_level', fn ($value) => 'Year '.$value)],
            ['key' => 'section', 'label' => 'All sections', 'options' => $options('section', fn ($value) => 'Section '.$value)],
        ])->filter(fn (array $filter) => count($filter['options']) > 1)->values()->all();
    }
}
