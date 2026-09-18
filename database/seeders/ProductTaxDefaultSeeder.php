<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\Product;
use App\Models\TaxCode;
use Illuminate\Database\Seeder;

/**
 * Go-live default chosen by the business on 2026-09-18: every product that
 * has no VAT treatment yet is standard-rated (16%) until it is reviewed
 * line by line. Standard-rating by default never under-declares output VAT;
 * exempt and zero-rated lines are corrected afterwards through the product
 * form. Products that already carry a tax code are never touched, so the
 * seeder is safe to re-run after that review has started.
 */
class ProductTaxDefaultSeeder extends Seeder
{
    public const DEFAULT_CODE = 'VAT_STD';

    public function run(): void
    {
        foreach (Organisation::all() as $organisation) {
            $taxCodeId = TaxCode::where('organisation_id', $organisation->id)->where('code', self::DEFAULT_CODE)->value('id');
            if (! $taxCodeId) {
                continue;
            }

            Product::where('organisation_id', $organisation->id)->whereNull('tax_code_id')->update(['tax_code_id' => $taxCodeId]);
        }
    }
}
