<?php

namespace Tests\Feature;

use Tests\TestCase;

class ActionFeedbackModalTest extends TestCase
{
    public function test_it_renders_flash_success_messages_in_the_shared_modal(): void
    {
        session()->flash('flash', [
            'type' => 'success',
            'message' => 'Clearance submitted successfully.',
        ]);

        $html = view('partials.action-feedback-modal')->render();

        $this->assertStringContainsString('id="feedbackModalOverlay"', $html);
        $this->assertStringContainsString('window.showSuccessModal', $html);
        $this->assertStringContainsString('window.showConfirmationModal', $html);
        $this->assertStringContainsString('Clearance submitted successfully.', $html);
    }

    public function test_it_treats_status_messages_as_success_feedback(): void
    {
        session()->flash('status', 'Account updated successfully.');

        $html = view('partials.action-feedback-modal')->render();

        $this->assertStringContainsString('Account updated successfully.', $html);
        $this->assertStringContainsString('Okay, Got it!', $html);
    }

    public function test_it_renders_clearance_result_titles_in_the_success_modal(): void
    {
        session()->flash('flash', [
            'type' => 'success',
            'title' => 'Clearance Set to Pending',
            'message' => 'The student clearance request was set to pending successfully.',
        ]);

        $html = view('partials.action-feedback-modal')->render();

        $this->assertStringContainsString('Clearance Set to Pending', $html);
        $this->assertStringContainsString('set to pending successfully', $html);
    }
}
