<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\PayrollBand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Part 15 — the statutory rates payroll is calculated from: PAYE brackets
 * and relief, NSSF tiers, SHIF, the housing levy and the pension relief cap.
 *
 * They change by law, so the System Administrator keeps them current here
 * rather than waiting for a release. Editing a band never rewrites a payroll
 * already processed: every run keeps a snapshot of the bands it used. For a
 * rate change on a date, close the old band (set its end date) and add the
 * new one from that date, so both the old and new months calculate correctly.
 */
class PayrollBandController extends ApiController
{
    /** Band types the payroll calculator understands; anything else would be silently ignored. */
    public const BAND_TYPES = ['PAYE', 'PAYE_RELIEF', 'NSSF', 'SHIF', 'HOUSING_LEVY', 'PENSION_RELIEF_CAP'];

    /** GET /api/payroll-bands?band_type=&in_force_on= */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $filters = $request->validate([
            'band_type' => ['nullable', Rule::in(self::BAND_TYPES)],
            'in_force_on' => ['nullable', 'date'],
        ]);

        $bands = $this->visible($request)
            ->when($filters['band_type'] ?? null, fn ($q, $v) => $q->where('band_type', $v))
            ->when($filters['in_force_on'] ?? null, fn ($q, $date) => $q
                ->whereDate('effective_from', '<=', $date)
                ->where(fn ($w) => $w->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date)))
            ->orderBy('band_type')->orderByDesc('effective_from')->orderBy('sequence')
            ->get();

        return response()->json(['data' => $bands, 'band_types' => self::BAND_TYPES]);
    }

    /** POST /api/payroll-bands */
    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $data = $this->validated($request);

        // A band created here is this institution's own override; the shared
        // statutory rows are maintained by the platform.
        $band = PayrollBand::create($data);

        AuditLog::record('PAYROLL_BAND_CREATED', 'payroll_band', $band->id, [
            'reference' => $band->band_type.' #'.$band->sequence,
            'after_json' => $band->toArray(),
        ]);

        return response()->json($band, 201);
    }

    /** PATCH /api/payroll-bands/{band} */
    public function update(Request $request, string $band): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $band = $this->visible($request)->findOrFail($band);
        $data = $this->validated($request, $band);

        $before = $band->toArray();
        $band->update($data);

        AuditLog::record('PAYROLL_BAND_UPDATED', 'payroll_band', $band->id, [
            'reference' => $band->band_type.' #'.$band->sequence,
            'before_json' => $before,
            'after_json' => $band->fresh()?->toArray(),
        ]);

        return response()->json($band->fresh());
    }

    /** DELETE /api/payroll-bands/{band} */
    public function destroy(Request $request, string $band): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $band = $this->visible($request)->findOrFail($band);

        AuditLog::record('PAYROLL_BAND_DELETED', 'payroll_band', $band->id, [
            'reference' => $band->band_type.' #'.$band->sequence,
            'before_json' => $band->toArray(),
        ]);
        $band->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Bands this organisation calculates with: the statutory rows and its
     * own overrides.
     *
     * @return Builder<PayrollBand>
     */
    private function visible(Request $request): Builder
    {
        $organisationId = $this->organisationId($request);

        return PayrollBand::query()->where(fn ($q) => $q->whereNull('organisation_id')->orWhere('organisation_id', $organisationId));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PayrollBand $existing = null): array
    {
        $partial = $existing !== null;
        $sometimes = fn (array $rules) => $partial ? ['sometimes', ...$rules] : $rules;

        $validator = validator($request->all(), [
            'band_type' => $sometimes(['required', Rule::in(self::BAND_TYPES)]),
            'sequence' => $sometimes(['required', 'integer', 'min:1', 'max:99']),
            'effective_from' => $sometimes(['required', 'date']),
            'effective_to' => ['nullable', 'date'],
            'lower' => $sometimes(['required', 'numeric', 'min:0']),
            'upper' => ['nullable', 'numeric', 'min:0'],
            'rate_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'source' => ['nullable', 'string', 'max:255'],
        ]);

        // Rules that span fields, checked against the values the band will
        // actually end up with after an edit.
        $validator->after(function (Validator $v) use ($request, $existing) {
            $value = fn (string $field) => $request->has($field) ? $request->input($field) : $existing?->getAttribute($field);

            $lower = $value('lower');
            $upper = $value('upper');
            if ($upper !== null && $upper !== '' && $lower !== null && (float) $upper <= (float) $lower) {
                $v->errors()->add('upper', 'The upper limit must be above the lower limit; leave it empty for no upper limit.');
            }

            $from = $value('effective_from');
            $to = $value('effective_to');
            if ($to && $from && strtotime((string) $to) < strtotime((string) $from)) {
                $v->errors()->add('effective_to', 'A band cannot end before it starts.');
            }

            $rate = $value('rate_pct');
            $fixed = $value('fixed_amount');
            if (($rate === null || $rate === '') && ($fixed === null || $fixed === '')) {
                $v->errors()->add('rate_pct', 'A band needs a rate, a fixed amount, or both.');
            }
        });

        return $validator->validate();
    }
}
