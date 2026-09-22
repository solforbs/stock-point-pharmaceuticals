<?php

namespace App\Services\Documents;

use App\Models\Branch;
use App\Models\Licence;
use App\Models\Organisation;

/**
 * What the company profile document prints: the profile itself, the active
 * branches, and the licences held by the business or its branches. A section
 * with nothing in it yet prints a "To be completed" note, so the document
 * can be handed out while it is still being filled in.
 */
class CompanyProfileDocument
{
    /** @var array<string, string> */
    public const LICENCE_LABELS = [
        'PPB_PREMISES' => 'PPB premises licence',
        'PPB_PHARMACIST' => 'PPB pharmacist practice licence',
        'PPB_PHARMTECH' => 'PPB pharmaceutical technologist licence',
        'BUSINESS_PERMIT' => 'Single business permit',
        'FIRE' => 'Fire safety certificate',
        'PUBLIC_HEALTH' => 'Public health certificate',
        'KRA_TCC' => 'KRA tax compliance certificate',
        'NHIF_SHIF' => 'SHIF accreditation',
        'OTHER' => 'Other certificate',
    ];

    /**
     * @return array<string, mixed>
     */
    public function data(Organisation $organisation): array
    {
        $branches = Branch::where('organisation_id', $organisation->id)->where('is_active', true)
            ->orderBy('name')->get(['id', 'code', 'name', 'address', 'county']);

        $licences = Licence::where('organisation_id', $organisation->id)
            ->where('is_active', true)
            ->whereIn('holder_type', ['ORGANISATION', 'BRANCH'])
            ->orderBy('licence_type')->orderBy('expiry_date')
            ->get()
            ->map(fn (Licence $licence) => [
                'type' => self::LICENCE_LABELS[$licence->licence_type] ?? $licence->licence_type,
                'number' => $licence->licence_number,
                'issued_by' => $licence->issued_by,
                'holder' => $licence->holder_type === 'BRANCH' ? $branches->firstWhere('id', $licence->holder_id)?->name : null,
                'expiry' => $licence->expiry_date->format('j M Y'),
                'status' => $licence->status,
            ]);

        return [
            'title' => 'Company profile',
            'profile' => $organisation,
            'branches' => $branches,
            'licences' => $licences,
        ];
    }
}
