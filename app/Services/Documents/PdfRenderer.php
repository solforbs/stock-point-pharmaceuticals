<?php

namespace App\Services\Documents;

use App\Models\Branch;
use App\Models\Organisation;
use App\Services\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Part 16.6 — documents a customer or supplier can be handed: a real PDF
 * rather than whatever the browser's print dialogue produces.
 *
 * Every document carries the same letterhead, taken from the organisation
 * and branch on the record, so an invoice printed today and one printed next
 * year look the same and say who issued them.
 */
class PdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data, string $filename, ?string $branchId = null): Response
    {
        $pdf = Pdf::loadHTML($this->html($view, $data, $branchId))
            ->setPaper('a4')
            ->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'isFontSubsettingEnabled' => true]);

        return $pdf->download($this->safeFilename($filename));
    }

    /**
     * The document as HTML, letterhead included, before dompdf lays it out.
     *
     * @param  array<string, mixed>  $data
     */
    public function html(string $view, array $data, ?string $branchId = null): string
    {
        return view($view, $data + ['letterhead' => $this->letterhead($branchId)])->render();
    }

    /**
     * Who issued this document. A KRA PIN that is still the placeholder is
     * reported as missing rather than printed, because an invoice carrying a
     * made-up PIN is worse than one that says it has none.
     *
     * @return array<string, mixed>
     */
    public function letterhead(?string $branchId): array
    {
        $branch = $branchId ? Branch::find($branchId) : null;
        $organisation = Organisation::find($branch?->organisation_id ?? app(TenantContext::class)->organisationId());

        $pin = trim((string) $organisation?->kra_pin);
        $placeholder = $pin === '' || preg_match('/^P0{6,}/i', $pin) === 1;

        return [
            'organisation' => $organisation->name ?? config('app.name'),
            'legal_name' => $organisation?->legal_name,
            'kra_pin' => $placeholder ? null : $pin,
            'branch' => $branch?->name,
            'branch_code' => $branch?->code,
            'address' => $branch?->address ?: $organisation?->physical_address,
            'tagline' => $organisation?->tagline,
            'contact_line' => $this->contactLine($organisation),
            'postal_address' => $this->filled($organisation?->postal_address),
            'phone' => $this->filled($organisation?->contact_phone),
            'email' => $this->filled($organisation?->contact_email),
            'website' => $this->filled($organisation?->website),
            'logo' => $this->letterheadLogo($organisation?->logo_path),
            'stamp' => $this->imageDataUri($organisation?->stamp_path),
            'signature' => $this->imageDataUri($organisation?->signature_path),
            'signatory_name' => $organisation?->signatory_name,
            'signatory_title' => $organisation?->signatory_title,
            'printed_at' => now()->format('j M Y, H:i'),
        ];
    }

    /**
     * The logo at letterhead size: at most 600 px wide, flattened onto white
     * and stored as JPEG. dompdf keeps a transparent PNG uncompressed, so a
     * full-size upload made every PDF over a megabyte; this copy is a few
     * dozen KB. It is made once and remade only when the logo changes.
     */
    public function letterheadLogo(?string $path): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }
        if (! function_exists('imagecreatefromstring')) {
            return $this->imageDataUri($path);
        }

        $small = $path.'.letterhead.jpg';
        $disk = Storage::disk('local');
        if (! $disk->exists($small) || $disk->lastModified($small) < $disk->lastModified($path)) {
            $source = @imagecreatefromstring((string) $disk->get($path));
            if ($source === false) {
                return $this->imageDataUri($path);
            }
            $width = imagesx($source);
            $height = imagesy($source);
            $scale = min(1, 600 / max($width, 1));
            $w = max(1, (int) round($width * $scale));
            $h = max(1, (int) round($height * $scale));

            $canvas = imagecreatetruecolor($w, $h);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $w, $h, $width, $height);
            ob_start();
            imagejpeg($canvas, null, 88);
            $disk->put($small, (string) ob_get_clean());
            imagedestroy($canvas);
            imagedestroy($source);
        }

        return 'data:image/jpeg;base64,'.base64_encode((string) $disk->get($small));
    }

    /**
     * A stored image as a data URI. dompdf is not allowed to fetch files, so
     * the logo, stamp and signature travel inside the HTML itself.
     */
    public function imageDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) Storage::disk('local')->get($path));
    }

    /** Postal address, phone, email and website on one line, skipping any not set. */
    private function contactLine(?Organisation $organisation): ?string
    {
        $parts = array_filter([
            $organisation?->postal_address,
            $organisation?->contact_phone ? 'Tel '.$organisation->contact_phone : null,
            $organisation?->contact_email,
            $organisation?->website,
        ], fn (?string $part) => trim((string) $part) !== '');

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private function filled(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** Keeps a document number safe to use as a file name. */
    private function safeFilename(string $filename): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?? 'document';

        return trim($clean, '-').'.pdf';
    }
}
