<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Part 13 — who the business is, as it appears on every invoice: the
 * registered name, and the KRA PIN and VAT number it trades under.
 *
 * For a limited company the PIN is the company's own (it starts with P). A
 * sole proprietor trading under a business name uses their personal PIN
 * (it starts with A), because a business name is not a separate person.
 */
class OrganisationController extends ApiController
{
    /** A KRA PIN: A or P, nine digits, a letter. */
    public const KRA_PIN_PATTERN = '/^[AP]\d{9}[A-Z]$/';

    /** GET /api/admin/organisation */
    public function show(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $organisation = Organisation::findOrFail($this->organisationId($request));

        return response()->json($organisation->only([
            'id', 'name', 'legal_name', 'kra_pin', 'vat_number', 'vat_registered', 'base_currency', 'country_code', 'timezone',
        ]) + [
            'kra_pin_is_placeholder' => $this->isPlaceholder((string) $organisation->kra_pin),
        ]);
    }

    /** PATCH /api/admin/organisation */
    public function update(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $organisation = Organisation::findOrFail($this->organisationId($request));

        // A PIN is written however it is typed; judge it as KRA prints it.
        if ($request->filled('kra_pin')) {
            $request->merge(['kra_pin' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('kra_pin')) ?? '')]);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'kra_pin' => ['sometimes', 'required', 'string', 'regex:'.self::KRA_PIN_PATTERN],
            'vat_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'vat_registered' => ['sometimes', 'boolean'],
        ], [
            'kra_pin.regex' => 'A KRA PIN is A or P, nine digits and a letter — for example P051234567M.',
        ]);

        if (isset($data['kra_pin'])) {
            if ($this->isPlaceholder($data['kra_pin'])) {
                return $this->error('PLACEHOLDER_PIN', 'That is the placeholder PIN, not a real one. Enter the PIN on your KRA certificate.', 422);
            }
        }

        $before = $organisation->only(array_keys($data));
        $organisation->update($data + ['updated_by' => $request->user()->id]);

        AuditLog::record('ORGANISATION_UPDATED', 'organisation', $organisation->id, [
            'reference' => $organisation->name,
            'before_json' => $before,
            'after_json' => $organisation->only(array_keys($data)),
        ]);

        return $this->show($request);
    }

    /** The PIN the installation was seeded with, which no business actually holds. */
    private function isPlaceholder(string $pin): bool
    {
        return $pin === '' || preg_match('/^P0{6,}/i', $pin) === 1;
    }
}
