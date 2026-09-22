<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Licence;
use App\Models\Organisation;
use App\Services\Documents\CompanyProfileDocument;
use App\Services\Documents\PdfRenderer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Client item 14 — the company profile: letterhead words, mission and vision,
 * and the logo, official stamp and authorised signature on issued documents.
 */
class CompanyProfileTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        Storage::fake('local');
        $this->grantPermissions(['admin.settings', 'sale.view', 'sale.create']);
        Sanctum::actingAs($this->user);
    }

    public function test_the_profile_is_saved_with_its_lists_and_the_change_is_audited(): void
    {
        $this->patchJson('/api/admin/company-profile', [
            'tagline' => 'Quality medicines for Turkana',
            'mission' => 'To supply genuine medicines on time, every time.',
            'vision' => 'The most trusted pharmacy in northern Kenya.',
            'core_values' => ['Integrity', '  ', 'Patient safety', ''],
            'services' => ['Wholesale supply', 'Retail pharmacy'],
            'website' => 'www.stockpoint.co.ke',
            'contact_email' => 'info@stockpoint.co.ke',
            'contact_phone' => '+254 700 000 000',
            'physical_address' => 'Kanamkemer Road, Lodwar',
            'postal_address' => 'P.O. Box 12-30500 Lodwar',
            'signatory_name' => 'Jane Ekai',
            'signatory_title' => 'Managing Director',
        ])->assertOk()
            ->assertJsonPath('tagline', 'Quality medicines for Turkana')
            ->assertJsonPath('core_values', ['Integrity', 'Patient safety'])
            ->assertJsonPath('images.logo', false);

        $organisation = Organisation::findOrFail($this->org->id);
        $this->assertSame(['Wholesale supply', 'Retail pharmacy'], $organisation->services);
        $this->assertSame('Jane Ekai', $organisation->signatory_name);
        $this->assertSame(1, AuditLog::where('action', 'COMPANY_PROFILE_UPDATED')->count());

        $this->getJson('/api/admin/company-profile')->assertOk()->assertJsonPath('mission', 'To supply genuine medicines on time, every time.');

        $this->patchJson('/api/admin/company-profile', ['contact_email' => 'not-an-email'])
            ->assertStatus(422)->assertJsonValidationErrors('contact_email');
    }

    public function test_only_png_or_jpg_images_up_to_two_megabytes_are_accepted(): void
    {
        $bad = [
            UploadedFile::fake()->create('logo.pdf', 20, 'application/pdf'),
            UploadedFile::fake()->image('logo.gif'),
            UploadedFile::fake()->image('logo.png')->size(3000),
        ];
        foreach ($bad as $file) {
            $this->post('/api/admin/company-profile/images/logo', ['image' => $file], ['Accept' => 'application/json'])
                ->assertStatus(422)->assertJsonValidationErrors('image');
        }

        $this->post('/api/admin/company-profile/images/unknown', ['image' => UploadedFile::fake()->image('x.png')], ['Accept' => 'application/json'])
            ->assertNotFound();

        $this->post('/api/admin/company-profile/images/stamp', ['image' => UploadedFile::fake()->image('stamp.png', 200, 200)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('images.stamp', true);

        $first = Organisation::findOrFail($this->org->id)->stamp_path;
        Storage::disk('local')->assertExists($first);
        $this->assertStringStartsWith('company-profile/'.$this->org->id.'/', $first);

        $this->get('/api/admin/company-profile/images/stamp')->assertOk()->assertHeader('Content-Type', 'image/png');

        // A replacement removes the old file; removing clears it altogether.
        $this->post('/api/admin/company-profile/images/stamp', ['image' => UploadedFile::fake()->image('stamp2.jpg')], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('local')->assertMissing($first);

        $this->deleteJson('/api/admin/company-profile/images/stamp')->assertOk()->assertJsonPath('images.stamp', false);
        $this->assertNull(Organisation::findOrFail($this->org->id)->stamp_path);
        $this->getJson('/api/admin/company-profile/images/stamp')->assertNotFound();
    }

    public function test_the_letterhead_carries_the_logo_and_issued_documents_the_stamp_and_signature(): void
    {
        $renderer = app(PdfRenderer::class);
        $this->receive('B-LOGO', now()->addYear()->toDateString(), '100', '2.0000');
        $sale = $this->checkout([$this->saleLine('TAB', '2', '2.7500')], [['method' => 'CASH', 'amount' => '5.5000']]);

        $before = $renderer->letterhead($this->branch->id);
        $this->assertNull($before['logo']);
        $this->assertNull($before['stamp']);

        foreach (['logo', 'stamp', 'signature'] as $kind) {
            $this->post("/api/admin/company-profile/images/{$kind}", ['image' => UploadedFile::fake()->image("{$kind}.png", 120, 60)], ['Accept' => 'application/json'])->assertOk();
        }
        $this->patchJson('/api/admin/company-profile', [
            'tagline' => 'Quality medicines for Turkana',
            'contact_phone' => '+254 700 000 000',
            'signatory_name' => 'Jane Ekai',
            'signatory_title' => 'Managing Director',
        ])->assertOk();

        $letterhead = $renderer->letterhead($this->branch->id);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $letterhead['logo']);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $letterhead['stamp']);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $letterhead['signature']);
        $this->assertStringContainsString('Tel +254 700 000 000', (string) $letterhead['contact_line']);

        $html = $renderer->html('pdf.invoice', [
            'title' => 'Cash sale invoice', 'sale' => $sale->load('lines'), 'amountPaid' => '5.5', 'balanceDue' => '0',
            'vatAnalysis' => [], 'amountInWords' => null, 'cashier' => 'Test', 'payments' => collect(),
            'money' => fn ($v) => number_format((float) $v, 2), 'qty' => fn ($v) => (string) $v,
        ], $this->branch->id);
        $this->assertStringContainsString($letterhead['logo'], $html);
        $this->assertStringContainsString($letterhead['stamp'], $html);
        $this->assertStringContainsString('Quality medicines for Turkana', $html);
        $this->assertStringContainsString('Jane Ekai', $html);
        $this->assertStringContainsString('Managing Director', $html);

        // dompdf really lays the images out.
        $pdf = (string) $this->get("/api/sales/{$sale->id}/pdf")->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf);
    }

    public function test_the_company_profile_pdf_prints_what_is_filled_and_marks_what_is_not(): void
    {
        Organisation::where('id', $this->org->id)->update(['mission' => 'To supply genuine medicines on time, every time.']);
        Licence::create([
            'organisation_id' => $this->org->id, 'holder_type' => 'BRANCH', 'holder_id' => $this->branch->id,
            'licence_type' => 'PPB_PREMISES', 'licence_number' => 'PPB/PREM/2026/118',
            'issued_by' => 'Pharmacy and Poisons Board', 'expiry_date' => now()->addYear()->toDateString(),
        ]);

        $organisation = Organisation::findOrFail($this->org->id);
        $html = app(PdfRenderer::class)->html('pdf.company-profile', app(CompanyProfileDocument::class)->data($organisation));

        $this->assertStringContainsString('To supply genuine medicines on time, every time.', $html);
        $this->assertStringContainsString('To be completed', $html, 'the empty vision, values and services are marked, not left out');
        $this->assertStringContainsString('PPB/PREM/2026/118', $html);
        $this->assertStringContainsString('PPB premises licence', $html);
        $this->assertStringContainsString(e($this->branch->name), $html);

        $response = $this->get('/api/admin/company-profile/pdf')->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    public function test_only_the_system_administrator_may_see_or_change_the_profile(): void
    {
        $this->revokeAllRoles();
        $this->grantPermissions(['sale.create'], 'Cashier');

        $this->getJson('/api/admin/company-profile')->assertStatus(403);
        $this->patchJson('/api/admin/company-profile', ['tagline' => 'x'])->assertStatus(403);
        $this->post('/api/admin/company-profile/images/logo', ['image' => UploadedFile::fake()->image('logo.png')], ['Accept' => 'application/json'])->assertStatus(403);
        $this->getJson('/api/admin/company-profile/images/logo')->assertStatus(403);
        $this->getJson('/api/admin/company-profile/pdf')->assertStatus(403);
    }
}
