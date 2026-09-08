<?php

namespace App\Http\Controllers\Instructors;

use App\Support\SectionKey;
use App\Support\StudentSubjects;

use App\Http\Controllers\Controller;
use App\Models\ClearanceStatus;
use App\Models\Notification;
use App\Models\StudentAccount;
use App\Models\SubjectCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ClearanceController extends Controller
{
    // POST /instructor/clearance/approve  (was approve.php)
    public function approve(Request $request)
    {
        return $this->setStatus($request, 'Approved');
    }

    // POST /instructor/clearance/pending
    public function pending(Request $request)
    {
        return $this->setStatus($request, 'Pending');
    }

    private function setStatus(Request $request, string $status)
    {
        $data = $request->validate([
            'student_id' => ['required', 'string', 'max:50'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $instructor = Auth::guard('instructor')->user();
        $instructorId = $instructor->instructor_id ?? Auth::guard('instructor')->id();
        $student = StudentAccount::where('student_id', $data['student_id'])->firstOrFail();

        Gate::forUser($instructor)->authorize('reviewSubject', [$student, (int) $data['subject_id']]);

        $previousStatus = ClearanceStatus::where('student_id', $data['student_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('instructor_id', $instructorId)
            ->value('status');

        ClearanceStatus::upsertStatus($data['student_id'], $data['subject_id'], $instructorId, [
            'status' => $status,
            'remarks' => $data['remarks'] ?? null,
        ]);

        $subjectCode = SubjectCode::where('subject_id', $data['subject_id'])->value('subject_code') ?? 'your subject';

        if ($status === 'Approved' && $previousStatus !== 'Approved') {
            Notification::create([
                'user_id' => $data['student_id'],
                'recipient_role' => 'student',
                'message' => "Your instructor approved your submission for {$subjectCode}.",
                'notif_type' => 'clearance',
                'link_url' => route('student.clearance-updates'),
            ]);
        }

        return response()->json(['success' => true, 'message' => "Clearance {$status}."]);
    }

    // POST /instructor/clearance/bulk  (was bulk_update.php)
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1|max:100',
            'items.*.student' => 'required|string|max:50',
            'items.*.subject' => 'required|integer|min:1',
            'status' => 'required|in:Approved,Pending',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Check the selected clearance records.', 'errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();

        $instructor = Auth::guard('instructor')->user();
        $instructorId = $instructor->instructor_id ?? Auth::guard('instructor')->id();
        $requestedItems = collect($data['items'])
            ->unique(fn (array $item) => $item['student'].'|'.$item['subject'])
            ->values();
        $allowedPairs = DB::query()
            ->fromSub(StudentSubjects::pairs(), 'ia')
            ->join('student_account as sa', 'sa.student_id', '=', 'ia.student_id')
            ->where('ia.instructor_id', $instructorId)
            ->where(function ($query) use ($requestedItems) {
                foreach ($requestedItems as $item) {
                    $query->orWhere(function ($pair) use ($item) {
                        $pair->where('sa.student_id', $item['student'])
                            ->where('ia.subject_id', $item['subject']);
                    });
                }
            })
            ->select('sa.student_id', 'ia.subject_id')
            ->distinct()
            ->get()
            ->mapWithKeys(fn ($item) => ["{$item->student_id}|{$item->subject_id}" => true]);

        if ($requestedItems->contains(fn (array $item) => ! $allowedPairs->has($item['student'].'|'.$item['subject']))) {
            $message = 'One or more selected clearances are outside your assigned subjects.';

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['items' => [$message]]], 422);
        }

        $updated = 0;

        DB::transaction(function () use ($requestedItems, $data, $instructorId, &$updated) {
            foreach ($requestedItems as $item) {
                $previousStatus = ClearanceStatus::where('student_id', $item['student'])
                    ->where('subject_id', $item['subject'])
                    ->where('instructor_id', $instructorId)
                    ->value('status');

                ClearanceStatus::upsertStatus($item['student'], (int) $item['subject'], $instructorId, [
                    'status' => $data['status'],
                ]);
                $updated++;

                $subjectCode = SubjectCode::where('subject_id', $item['subject'])->value('subject_code') ?? 'your subject';
                if ($data['status'] === 'Approved' && $previousStatus !== 'Approved') {
                    Notification::create([
                        'user_id' => $item['student'],
                        'recipient_role' => 'student',
                        'message' => "Your instructor approved your submission for {$subjectCode}.",
                        'notif_type' => 'clearance',
                        'link_url' => route('student.clearance-updates'),
                    ]);
                }
            }
        });

        $message = $data['status'] === 'Approved'
            ? "{$updated} instructor clearance records approved successfully."
            : "{$updated} instructor clearance records returned to pending.";

        return response()->json(['success' => true, 'updated' => $updated, 'message' => $message]);
    }
}
