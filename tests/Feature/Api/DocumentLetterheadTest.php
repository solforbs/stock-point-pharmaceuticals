<?php

namespace Tests\Feature\Api;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Excel downloads are built in the browser: they take the same letterhead
 * the PDFs print from GET /api/documents/letterhead.
 */
class DocumentLetterheadTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['po.create']);
        Sanctum::actingAs($this->user);
    }

    public function test_the_letterhead_names_the_institution_branch_and_contacts_but_not_the_pin_or_stamp(): void
    {
        $this->org->update(['legal_name' => 'Lodwar Pharma Ltd', 'contact_phone' => '0700 000 111', 'contact_email' => 'info@lodwar.test', 'kra_pin' => 'P051234567Q']);

        $this->getJson('/api/documents/letterhead')->assertOk()
            ->assertJsonPath('legal_name', 'Lodwar Pharma Ltd')
            ->assertJsonPath('phone', '0700 000 111')
            ->assertJsonPath('email', 'info@lodwar.test')
            ->assertJsonMissingPath('kra_pin')
            ->assertJsonPath('branch', $this->branch->name)
            ->assertJsonPath('logo', null)
            ->assertJsonMissingPath('stamp')
            ->assertJsonMissingPath('signature');
    }

    public function test_the_logo_comes_as_a_jpeg_the_spreadsheet_can_hold(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->image('logo.png', 800, 200)->store('branding', 'local');
        $this->org->forceFill(['logo_path' => $path])->save();

        $logo = $this->getJson('/api/documents/letterhead')->assertOk()->json('logo');

        $this->assertStringStartsWith('data:image/jpeg;base64,', $logo);
    }
}
