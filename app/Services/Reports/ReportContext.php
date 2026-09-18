<?php

namespace App\Services\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Everything a report query needs: the organisation and branch it is
 * scoped to, the date window, and the caller's filters.
 */
final class ReportContext
{
    /** @var list<string> */
    public readonly array $storeIds;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public readonly string $organisationId,
        public readonly string $branchId,
        public readonly string $from,
        public readonly string $to,
        public readonly array $filters = [],
    ) {
        $this->storeIds = DB::table('stores')->where('branch_id', $branchId)->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function make(string $organisationId, string $branchId, array $filters = []): self
    {
        $from = isset($filters['from']) ? Carbon::parse((string) $filters['from'])->toDateString() : now()->startOfMonth()->toDateString();
        $to = isset($filters['to']) ? Carbon::parse((string) $filters['to'])->toDateString() : now()->toDateString();

        return new self($organisationId, $branchId, $from, $to, $filters);
    }

    public function fromDateTime(): string
    {
        return $this->from.' 00:00:00';
    }

    public function toDateTime(): string
    {
        return $this->to.' 23:59:59';
    }

    public function days(): int
    {
        return max(1, Carbon::parse($this->from)->diffInDays(Carbon::parse($this->to)) + 1);
    }

    public function filter(string $key, mixed $default = null): mixed
    {
        return $this->filters[$key] ?? $default;
    }
}
