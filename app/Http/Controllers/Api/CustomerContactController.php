<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerInteraction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Customer relationship detail: the people at each customer (buyer,
 * accounts, pharmacist in charge) and a log of calls, visits and messages
 * with follow-ups that stay due until marked done.
 */
class CustomerContactController extends ApiController
{
    /** GET /api/customer-contacts?customer_id=&q= */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'sale.view');

        $term = trim((string) $request->input('q', ''));

        return response()->json(
            CustomerContact::query()
                ->whereIn('customer_id', $this->organisationCustomers($request))
                ->when($request->input('customer_id'), fn ($q, $v) => $q->where('customer_id', $v))
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")->orWhere('role', 'like', "%{$term}%")))
                ->with('customer:id,code,name')
                ->orderByDesc('is_primary')->orderBy('name')
                ->paginate($request->integer('per_page', 25))
        );
    }

    /** POST /api/customer-contacts */
    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'customer.manage');

        $data = $request->validate([
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->where('organisation_id', $this->organisationId($request))],
            ...$this->contactRules(),
        ]);

        $contact = DB::transaction(function () use ($data) {
            if (! empty($data['is_primary'])) {
                CustomerContact::where('customer_id', $data['customer_id'])->update(['is_primary' => false]);
            }

            // Default it here rather than relying on the column default, so the
            // created row comes back with the flag already set.
            return CustomerContact::create($data + ['is_primary' => false]);
        });

        AuditLog::record('CUSTOMER_CONTACT_CREATED', 'customer_contact', (string) $contact->id, ['reference' => $contact->name, 'after_json' => $contact->toArray()]);

        return response()->json($contact->load('customer:id,code,name'), 201);
    }

    /** PATCH /api/customer-contacts/{contact} */
    public function update(Request $request, string $contact): JsonResponse
    {
        $this->requirePermission($request, 'customer.manage');

        $contact = CustomerContact::whereIn('customer_id', $this->organisationCustomers($request))->findOrFail($contact);
        $data = $request->validate(array_map(fn (array $rules) => ['sometimes', ...$rules], $this->contactRules()));

        $before = $contact->toArray();
        DB::transaction(function () use ($contact, $data) {
            if (! empty($data['is_primary'])) {
                CustomerContact::where('customer_id', $contact->customer_id)->whereKeyNot($contact->id)->update(['is_primary' => false]);
            }
            $contact->update($data);
        });

        AuditLog::record('CUSTOMER_CONTACT_UPDATED', 'customer_contact', (string) $contact->id, ['reference' => $contact->name, 'before_json' => $before, 'after_json' => $contact->toArray()]);

        return response()->json($contact->load('customer:id,code,name'));
    }

    /** GET /api/customer-interactions?customer_id=&channel=&follow_ups_due=1 */
    public function interactions(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'sale.view');

        $due = $request->boolean('follow_ups_due');

        return response()->json(
            CustomerInteraction::query()
                ->whereIn('customer_id', $this->organisationCustomers($request))
                ->when($request->input('customer_id'), fn ($q, $v) => $q->where('customer_id', $v))
                ->when($request->input('channel'), fn ($q, $v) => $q->where('channel', $v))
                ->when($due, fn ($q) => $q->where('follow_up_done', false)->whereNotNull('follow_up_date')->whereDate('follow_up_date', '<=', now()->toDateString()))
                ->with(['customer:id,code,name', 'contact:id,name,role,phone', 'user:id,name'])
                ->when($due, fn ($q) => $q->orderBy('follow_up_date'), fn ($q) => $q->orderByDesc('occurred_at'))
                ->paginate($request->integer('per_page', 25))
        );
    }

    /** POST /api/customer-interactions */
    public function storeInteraction(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'customer.manage');

        $data = $request->validate([
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->where('organisation_id', $this->organisationId($request))],
            'contact_id' => ['nullable', 'uuid', Rule::exists('customer_contacts', 'id')->where('customer_id', $request->input('customer_id'))],
            'channel' => ['required', Rule::in(CustomerInteraction::CHANNELS)],
            'summary' => ['required', 'string', 'min:3', 'max:5000'],
            'follow_up_date' => ['nullable', 'date'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        $interaction = CustomerInteraction::create([
            ...$data,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'follow_up_done' => false,
            'user_id' => $request->user()->id,
        ]);

        AuditLog::record('CUSTOMER_INTERACTION_LOGGED', 'customer_interaction', (string) $interaction->id, ['reference' => $interaction->channel, 'after_json' => $interaction->toArray()]);

        return response()->json($interaction->load(['customer:id,code,name', 'contact:id,name,role,phone', 'user:id,name']), 201);
    }

    /** PATCH /api/customer-interactions/{interaction} — mark (or reopen) the follow-up, or move its date. */
    public function updateInteraction(Request $request, string $interaction): JsonResponse
    {
        $this->requirePermission($request, 'customer.manage');

        $interaction = CustomerInteraction::whereIn('customer_id', $this->organisationCustomers($request))->findOrFail($interaction);
        $data = $request->validate([
            'follow_up_done' => ['sometimes', 'boolean'],
            'follow_up_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $before = $interaction->only(['follow_up_done', 'follow_up_date']);
        $interaction->update($data);

        AuditLog::record(! empty($data['follow_up_done']) ? 'CUSTOMER_FOLLOW_UP_DONE' : 'CUSTOMER_INTERACTION_UPDATED', 'customer_interaction', (string) $interaction->id, [
            'reference' => $interaction->channel, 'before_json' => $before, 'after_json' => $interaction->only(['follow_up_done', 'follow_up_date']),
        ]);

        return response()->json($interaction->load(['customer:id,code,name', 'contact:id,name,role,phone', 'user:id,name']));
    }

    /**
     * @return array<string, list<string>>
     */
    private function contactRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_primary' => ['boolean'],
        ];
    }

    /**
     * Customer ids of the active organisation, as a subquery.
     *
     * @return Builder<Customer>
     */
    private function organisationCustomers(Request $request): Builder
    {
        return Customer::select('id')->where('organisation_id', $this->organisationId($request));
    }
}
