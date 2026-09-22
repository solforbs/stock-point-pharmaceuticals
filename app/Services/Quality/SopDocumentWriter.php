<?php

namespace App\Services\Quality;

use App\Models\ControlledDocument;
use App\Models\DocumentVersion;
use App\Services\Documents\PdfRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Issues a version of a controlled document from text written in the app:
 * the sections are laid out as a formatted SOP (document control box,
 * numbered procedure, sign-off block), saved as the version's PDF, and the
 * text is kept on the version for the next revision.
 */
class SopDocumentWriter
{
    public function __construct(private readonly PdfRenderer $pdf) {}

    /**
     * @param  array<string, string>  $sections  keyed as SopTemplates::SECTIONS
     */
    public function issue(ControlledDocument $document, string $version, string $effectiveDate, array $sections, ?string $changeSummary, int $userId): DocumentVersion
    {
        $sections = array_intersect_key($sections, SopTemplates::SECTIONS);

        $output = Pdf::loadView('pdf.sop', [
            'title' => $document->title,
            'docNumber' => $document->code.' · version '.$version,
            'docDate' => 'Effective '.date('j M Y', strtotime($effectiveDate)),
            'document' => $document,
            'version' => $version,
            'effectiveDate' => $effectiveDate,
            'sections' => $sections,
            'labels' => SopTemplates::SECTIONS,
            'letterhead' => $this->pdf->letterhead(null),
        ])->setPaper('a4')->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans'])->output();

        $fileName = $document->code.'-v'.$version.'.pdf';
        $path = 'documents/'.$document->id.'/'.uniqid('sop-', true).'.pdf';
        Storage::disk('local')->put($path, $output);

        $issued = DocumentVersion::create([
            'controlled_document_id' => $document->id,
            'version' => $version,
            'file_path' => $path,
            'file_name' => $fileName,
            'content_json' => $sections,
            'change_summary' => $changeSummary,
            'effective_date' => $effectiveDate,
            'uploaded_by' => $userId,
        ]);
        $document->update(['current_version_id' => $issued->id]);

        return $issued;
    }
}
