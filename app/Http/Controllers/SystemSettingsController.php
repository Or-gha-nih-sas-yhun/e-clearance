<?php

namespace App\Http\Controllers;

use App\Support\SystemMaintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * End-of-term maintenance, Main Admin only.
 *
 * Both actions here are irreversible and affect every student at once, so each
 * one shows its own row and file counts on the page and refuses to run unless
 * the admin types the exact confirmation phrase for that action. The phrases
 * differ deliberately — muscle memory from one should not fire the other.
 */
class SystemSettingsController extends Controller
{
    private const PROMOTE_PHRASE = 'PROMOTE STUDENTS';

    private const RESET_PHRASE = 'RESET CLEARANCE';

    public function index()
    {
        return view('mainAdmin.settings.index', [
            'promotion' => SystemMaintenance::previewPromotion(),
            'reset' => SystemMaintenance::previewClearanceReset(),
            'statusColumnAvailable' => SystemMaintenance::statusColumnAvailable(),
            'promotePhrase' => self::PROMOTE_PHRASE,
            'resetPhrase' => self::RESET_PHRASE,
        ]);
    }

    public function promote(Request $request)
    {
        if ($failure = $this->confirmationFailure($request, self::PROMOTE_PHRASE)) {
            return $failure;
        }

        if (! SystemMaintenance::statusColumnAvailable()) {
            return back()->with('flash', [
                'type' => 'error',
                'title' => 'Promotion unavailable',
                'message' => 'The student_account table has no status column yet, so graduating students cannot be deactivated. Run the migration or database/sql/student_account_status.sql first.',
            ]);
        }

        $result = SystemMaintenance::promoteStudents();

        return back()->with('flash', [
            'type' => 'success',
            'title' => 'Promotion complete',
            'message' => "{$result['promoted']} student(s) moved up a year. {$result['graduated']} finished 4th year and were deactivated.",
        ]);
    }

    /** Read-only archive, safe to download at any time. */
    public function archive(): StreamedResponse
    {
        return $this->csvResponse(
            SystemMaintenance::clearanceArchiveCsv(),
            'clearance-archive-'.now()->format('Y-m-d-His').'.csv',
        );
    }

    /**
     * Clear the term and hand back the archive of what was removed. The CSV is
     * built before anything is deleted, and the download is the admin's only
     * copy — so the reset always produces one, whether or not they remembered
     * to download the archive separately first.
     */
    public function reset(Request $request)
    {
        if ($failure = $this->confirmationFailure($request, self::RESET_PHRASE)) {
            return $failure;
        }

        $csv = SystemMaintenance::clearanceArchiveCsv();
        SystemMaintenance::resetClearanceRecords();

        return $this->csvResponse(
            $csv,
            'clearance-archive-before-reset-'.now()->format('Y-m-d-His').'.csv',
        );
    }

    private function confirmationFailure(Request $request, string $phrase)
    {
        $request->validate(['confirmation' => ['required', 'string', 'max:60']]);

        if (trim((string) $request->input('confirmation')) !== $phrase) {
            return back()->with('flash', [
                'type' => 'error',
                'title' => 'Confirmation did not match',
                'message' => "Nothing was changed. Type {$phrase} exactly to run this action.",
            ]);
        }

        return null;
    }

    private function csvResponse(string $csv, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
