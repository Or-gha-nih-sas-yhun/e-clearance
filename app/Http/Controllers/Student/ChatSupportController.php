<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentAccount;
use App\Support\ChatDirectory;
use App\Support\ChatThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * The student half of cross-role chat. A student's sidebar mixes four staff
 * portals, so every request names the portal it is aimed at (`partner_role`)
 * alongside the account id, and App\Support\ChatDirectory decides whether that
 * pair is allowed to talk.
 */
class ChatSupportController extends Controller
{
    public function __construct(
        private readonly ChatDirectory $directory = new ChatDirectory,
        private readonly ChatThread $thread = new ChatThread,
    ) {}

    public function index()
    {
        $student = $this->student();
        $viewer = $this->viewer($student);

        $unread = $this->thread->unreadByPartner($viewer);
        $latest = $this->thread->latestByPartner($viewer);

        $contacts = $this->directory->staffContactsFor($student)
            ->map(function (object $contact) use ($unread, $latest) {
                $key = ChatThread::key($contact->role, $contact->id);
                $contact->unread = $unread->get($key, 0);
                $contact->preview = $latest->get($key)['message'] ?? $contact->title;
                $contact->sort = $latest->get($key)['sort'] ?? 0;

                return $contact;
            })
            ->sortByDesc('sort')
            ->values();

        return view('student.chat-support', [
            'student' => $student,
            'contacts' => $contacts,
            'portalFilter' => $contacts->pluck('group')->filter()->unique()->sort()
                ->mapWithKeys(fn (string $group) => [strtolower($group) => $group])->all(),
        ]);
    }

    public function messages(Request $request): JsonResponse
    {
        $student = $this->student();
        $partner = $this->authorizedPartner($student, $request->all());

        return response()->json($this->thread->messages(
            $this->viewer($student),
            $partner,
            $request->boolean('sync') ? 0 : (int) $request->query('since', 0),
            100,
            $request->boolean('sync'),
        ));
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'receiver_id' => ['required', 'string', 'max:50'],
            'partner_role' => ['required', 'string', Rule::in(ChatDirectory::STAFF_ROLES)],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $student = $this->student();
        $partner = $this->authorizedPartner($student, $data);

        $message = $this->thread->send(
            $this->viewer($student),
            $partner,
            $data['message'],
            $student->full_name,
        );

        return response()->json(['success' => true, 'id' => $message->id]);
    }

    public function destroy(int $message): JsonResponse
    {
        $student = $this->student();

        abort_unless($this->thread->delete($this->viewer($student), $message), 404);

        return response()->json(['success' => true]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{role: string, id: string}
     */
    private function authorizedPartner(StudentAccount $student, array $input): array
    {
        $role = trim((string) ($input['partner_role'] ?? ''));
        $id = trim((string) ($input['receiver_id'] ?? $input['with'] ?? ''));

        abort_unless($id !== '' && $this->directory->permits($student, $role, $id), 403);

        return ['role' => $role, 'id' => $id];
    }

    private function student(): StudentAccount
    {
        $student = Auth::guard('student')->user();

        abort_unless($student !== null, 403);

        return $student;
    }

    /** @return array{role: string, id: string} */
    private function viewer(StudentAccount $student): array
    {
        return ['role' => 'student', 'id' => (string) $student->student_id];
    }
}
