<?php

namespace Tests\Feature\Blueprint;

use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 14.4 — gapless, sequential, per-year, per-branch document numbers
 * that roll back with the document they number.
 */
class NumberSequenceTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    public function test_numbers_are_sequential_and_scoped_per_branch_and_year(): void
    {
        $year = now()->format('Y');

        $this->assertSame("INV-{$year}-000001", NumberSequence::next($this->org->id, 'SALE', $this->branch->id, 'INV'));
        $this->assertSame("INV-{$year}-000002", NumberSequence::next($this->org->id, 'SALE', $this->branch->id, 'INV'));
        $this->assertSame("GRN-{$year}-000001", NumberSequence::next($this->org->id, 'GRN', $this->branch->id, 'GRN'));
    }

    public function test_a_rolled_back_document_does_not_burn_a_number(): void
    {
        $year = now()->format('Y');

        try {
            DB::transaction(function () {
                NumberSequence::next($this->org->id, 'SALE', $this->branch->id, 'INV');
                throw new \RuntimeException('simulated failure after numbering');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame("INV-{$year}-000001", NumberSequence::next($this->org->id, 'SALE', $this->branch->id, 'INV'));
    }
}
