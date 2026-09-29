<?php

namespace Tests\Feature;

use App\Models\MainAdmin;
use App\Models\ProgramSection;
use App\Models\Treasurer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasurerSectionSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(MainAdmin::create([
            'name' => 'Test Admin',
            'email' => 'treasurer-sections-admin@example.test',
            'password' => 'AdminPassword1!',
        ]), 'admin');
    }

    public function test_add_and_edit_treasurer_forms_use_dependent_section_dropdowns(): void
    {
        ProgramSection::create([
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'EAST',
        ]);

        $content = $this->get(route('treasurers.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<select name="section" id="t_section">', $content);
        $this->assertStringContainsString('<select name="section" id="e_section">', $content);
        $this->assertStringNotContainsString('<input type="text" name="section" id="t_section"', $content);
        $this->assertStringNotContainsString('<input type="text" name="section" id="e_section"', $content);
        $this->assertStringContainsString('updateTreasurerSectionOptions', $content);
        $this->assertStringContainsString('EAST', $content);
    }

    public function test_section_treasurer_can_be_created_for_a_configured_section(): void
    {
        ProgramSection::create([
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'EAST',
        ]);

        $this->post(route('treasurers.store'), $this->sectionTreasurerPayload())
            ->assertRedirect(route('treasurers.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('treasurers', [
            'email' => 'section-treasurer@example.test',
            'treasurer_type' => 'section',
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'EAST',
        ]);
    }

    public function test_section_treasurer_cannot_be_created_for_an_unconfigured_section(): void
    {
        ProgramSection::create([
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'EAST',
        ]);

        $this->from(route('treasurers.index'))
            ->post(route('treasurers.store'), [
                ...$this->sectionTreasurerPayload(),
                'section' => 'WEST',
            ])
            ->assertRedirect(route('treasurers.index'))
            ->assertSessionHasErrors('section');

        $this->assertDatabaseMissing('treasurers', [
            'email' => 'section-treasurer@example.test',
        ]);
    }

    public function test_section_treasurer_cannot_be_updated_to_an_unconfigured_section(): void
    {
        ProgramSection::create([
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'EAST',
        ]);
        $treasurer = Treasurer::create([
            'treasurer_id' => 'TR-10001',
            ...$this->sectionTreasurerPayload(),
            'password' => 'ExistingPassword1!',
        ]);

        $this->from(route('treasurers.index'))
            ->put(route('treasurers.update', $treasurer->id), [
                ...$this->sectionTreasurerPayload(),
                'section' => 'WEST',
            ])
            ->assertRedirect(route('treasurers.index'))
            ->assertSessionHasErrors('section');

        $this->assertSame('EAST', $treasurer->fresh()->section);
    }

    private function sectionTreasurerPayload(): array
    {
        return [
            'firstname' => 'Section',
            'lastname' => 'Treasurer',
            'email' => 'section-treasurer@example.test',
            'treasurer_type' => 'section',
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'EAST',
        ];
    }
}
