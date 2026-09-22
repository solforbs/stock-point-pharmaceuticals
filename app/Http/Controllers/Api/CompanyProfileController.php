<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Organisation;
use App\Services\Documents\CompanyProfileDocument;
use App\Services\Documents\PdfRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The company profile: the words and marks that present the business — the
 * tagline and contacts on every letterhead, the mission, vision and values in
 * the company profile document, and the logo, official stamp and authorised
 * signature printed on issued documents.
 *
 * The images are kept on the private disk and reach the web app only through
 * this controller, so a stamp and signature can never be fetched by a link.
 */
class CompanyProfileController extends ApiController
{
    /** Largest image accepted, in kilobytes. */
    public const MAX_IMAGE_KB = 2048;

    /** Most entries in the core values or services list. */
    public const MAX_LIST_ITEMS = 20;

    /** @var list<string> */
    private const TEXT_FIELDS = [
        'tagline', 'about', 'mission', 'vision', 'core_values', 'services', 'website',
        'contact_email', 'contact_phone', 'physical_address', 'postal_address', 'signatory_name', 'signatory_title',
    ];

    /** GET /api/admin/company-profile */
    public function show(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');

        return response()->json($this->present($this->organisation($request)));
    }

    /** PATCH /api/admin/company-profile */
    public function update(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $organisation = $this->organisation($request);

        // Blank lines in a list are dropped rather than refused.
        foreach (['core_values', 'services'] as $list) {
            if (is_array($request->input($list))) {
                $request->merge([$list => array_values(array_filter(
                    array_map(fn ($item) => is_string($item) ? trim($item) : $item, $request->input($list)),
                    fn ($item) => $item !== '' && $item !== null,
                ))]);
            }
        }

        $data = $request->validate([
            'tagline' => ['sometimes', 'nullable', 'string', 'max:200'],
            'about' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'mission' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'vision' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'core_values' => ['sometimes', 'nullable', 'array', 'max:'.self::MAX_LIST_ITEMS],
            'core_values.*' => ['string', 'max:300'],
            'services' => ['sometimes', 'nullable', 'array', 'max:'.self::MAX_LIST_ITEMS],
            'services.*' => ['string', 'max:300'],
            'website' => ['sometimes', 'nullable', 'string', 'max:200'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'physical_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'postal_address' => ['sometimes', 'nullable', 'string', 'max:200'],
            'signatory_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'signatory_title' => ['sometimes', 'nullable', 'string', 'max:150'],
        ]);
        $data = array_intersect_key($data, array_flip(self::TEXT_FIELDS));

        $before = $organisation->only(array_keys($data));
        $organisation->update($data + ['updated_by' => $request->user()->id]);

        AuditLog::record('COMPANY_PROFILE_UPDATED', 'organisation', $organisation->id, [
            'reference' => $organisation->name,
            'before_json' => $before,
            'after_json' => $organisation->only(array_keys($data)),
            'changed_fields' => array_keys($data),
        ]);

        return response()->json($this->present($organisation->fresh()));
    }

    /** POST /api/admin/company-profile/images/{kind} — the logo, stamp or signature. */
    public function uploadImage(Request $request, string $kind): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $organisation = $this->organisation($request);
        $column = Organisation::PROFILE_IMAGES[$kind];

        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg', 'max:'.self::MAX_IMAGE_KB],
        ], [
            'image.mimes' => 'Upload a PNG or JPG image.',
            'image.image' => 'Upload a PNG or JPG image.',
            'image.max' => 'The image must be 2 MB or smaller.',
        ]);

        $previous = $organisation->{$column};
        $path = $request->file('image')->store('company-profile/'.$organisation->id, 'local');
        $organisation->forceFill([$column => $path ?: null, 'updated_by' => $request->user()->id])->save();

        if ($previous && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }

        AuditLog::record('COMPANY_PROFILE_IMAGE_UPLOADED', 'organisation', $organisation->id, [
            'reference' => $organisation->name,
            'changed_fields' => [$column],
        ]);

        return response()->json($this->present($organisation->fresh()));
    }

    /** DELETE /api/admin/company-profile/images/{kind} */
    public function deleteImage(Request $request, string $kind): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $organisation = $this->organisation($request);
        $column = Organisation::PROFILE_IMAGES[$kind];

        if ($organisation->{$column}) {
            Storage::disk('local')->delete($organisation->{$column});
            $organisation->forceFill([$column => null, 'updated_by' => $request->user()->id])->save();

            AuditLog::record('COMPANY_PROFILE_IMAGE_REMOVED', 'organisation', $organisation->id, [
                'reference' => $organisation->name,
                'changed_fields' => [$column],
            ]);
        }

        return response()->json($this->present($organisation->fresh()));
    }

    /** GET /api/admin/company-profile/images/{kind} — the stored image, for the preview. */
    public function image(Request $request, string $kind): StreamedResponse|JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $path = $this->organisation($request)->{Organisation::PROFILE_IMAGES[$kind]};

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return $this->error('NOT_FOUND', 'No image has been uploaded.', 404);
        }

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=0, no-store']);
    }

    /** GET /api/admin/company-profile/pdf — the company profile document, gaps and all. */
    public function pdf(Request $request, PdfRenderer $pdf, CompanyProfileDocument $document): Response
    {
        $this->requirePermission($request, 'admin.settings');
        $organisation = $this->organisation($request);

        AuditLog::record('COMPANY_PROFILE_PRINTED', 'organisation', $organisation->id, ['reference' => $organisation->name]);

        return $pdf->render('pdf.company-profile', $document->data($organisation), 'Company-Profile-'.$organisation->name);
    }

    private function organisation(Request $request): Organisation
    {
        return Organisation::findOrFail($this->organisationId($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Organisation $organisation): array
    {
        $images = [];
        foreach (Organisation::PROFILE_IMAGES as $kind => $column) {
            $images[$kind] = $organisation->{$column} !== null;
        }

        return [
            'core_values' => $organisation->core_values ?? [],
            'services' => $organisation->services ?? [],
            'images' => $images,
            'images_version' => $organisation->updated_at?->timestamp,
        ] + $organisation->only(['id', 'name', 'legal_name', ...self::TEXT_FIELDS]);
    }
}
