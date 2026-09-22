<?php

namespace App\Services\Documents;

use App\Models\Branch;
use App\Models\Organisation;
use App\Services\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

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
        $pdf = Pdf::loadView($view, $data + ['letterhead' => $this->letterhead($branchId)])
            ->setPaper('a4')
            ->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);

        return $pdf->download($this->safeFilename($filename));
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
            'address' => $branch?->address,
            'printed_at' => now()->format('j M Y, H:i'),
        ];
    }

    /** Keeps a document number safe to use as a file name. */
    private function safeFilename(string $filename): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?? 'document';

        return trim($clean, '-').'.pdf';
    }
}
