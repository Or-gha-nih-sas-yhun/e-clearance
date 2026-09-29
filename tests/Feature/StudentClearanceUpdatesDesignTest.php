<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class StudentClearanceUpdatesDesignTest extends TestCase
{
    public function test_clearance_updates_uses_the_compact_workspace_design(): void
    {
        $viewPath = resource_path('views/student/clearance-updates.blade.php');
        $stylesheetPath = public_path('css/student_clearance_updates.css');

        $this->assertFileExists($viewPath);
        $this->assertFileExists($stylesheetPath);

        $source = (string) file_get_contents($viewPath);

        $this->assertStringContainsString("asset('css/student_clearance_updates.css')", $source);
        $this->assertStringContainsString('student-clearance-workspace', $source);
        $this->assertStringContainsString('student-clearance-statusbar', $source);
        $this->assertStringContainsString('student-status-track', $source);
        $this->assertStringContainsString('student-status-breakdown', $source);
        $this->assertStringContainsString('student-instructor-grid', $source);
        $this->assertStringContainsString('office-card-grid', $source);
        $this->assertStringContainsString('office-card-statuses', $source);
        $this->assertStringContainsString('office-card-evaluation-status', $source);

        $stylesheet = (string) file_get_contents($stylesheetPath);
        $this->assertStringContainsString('.student-clearance-section .office-card-statuses', $stylesheet);
    }

    public function test_redesign_preserves_clearance_requests_uploads_and_document_viewer(): void
    {
        $source = (string) file_get_contents(resource_path('views/student/clearance-updates.blade.php'));

        $requiredHooks = [
            "route('student.clearance.submit-instructor')",
            "route('student.clearance.submit-office')",
            "route('student.clearance.upload-office')",
            "route('student.clearance.office-submission'",
            "route('student.clearance.form'",
            "route('student.clearance.form.download')",
            'data-clearance-form-open',
            'data-office-modal-open',
            'data-office-modal-close',
        ];

        foreach ($requiredHooks as $hook) {
            $this->assertStringContainsString($hook, $source);
        }
    }

    public function test_clearance_updates_view_compiles(): void
    {
        $source = (string) file_get_contents(resource_path('views/student/clearance-updates.blade.php'));
        $compiled = Blade::compileString($source);

        $this->assertNotSame('', trim($compiled));
    }
}
