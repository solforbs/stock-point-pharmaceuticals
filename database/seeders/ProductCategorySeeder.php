<?php

namespace Database\Seeders;

use App\Models\DosageForm;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StorageCondition;
use Illuminate\Database\Seeder;

/**
 * The stock headings Stock Point asked for on 2026-09-22 (Drugs, Topicals,
 * Cold chain, Vaccines, and so on), with Drugs split into therapeutic
 * classes. The headings are shared by every institution; each can add its
 * own sub-headings beneath them.
 *
 * Products with no category yet get a first guess from their storage
 * condition, dosage form and name (a 2–8 °C product is Cold chain, an
 * injection is Injections, "… CREAM 20GM" is Topicals, a tablet is Drugs).
 * Products that already have a category are never touched, so the seeder
 * is safe to re-run after staff start correcting the guesses.
 */
class ProductCategorySeeder extends Seeder
{
    /** @var array<string, array{name: string, children?: array<string, string>}> */
    public const TREE = [
        'DRUGS' => ['name' => 'Drugs', 'children' => [
            'DRUGS-ABX' => 'Antibiotics',
            'DRUGS-AMAL' => 'Antimalarials',
            'DRUGS-AFUN' => 'Antifungals',
            'DRUGS-ARV' => 'ARVs',
            'DRUGS-GIT' => 'GIT',
            'DRUGS-CARD' => 'Cardiac',
            'DRUGS-ANLG' => 'Analgesics',
            'DRUGS-RESP' => 'Respiratory',
            'DRUGS-DIAB' => 'Antidiabetics',
            'DRUGS-CNS' => 'CNS',
            'DRUGS-VIT' => 'Vitamins & supplements',
            'DRUGS-OTH' => 'Other drugs',
        ]],
        'TOPICALS' => ['name' => 'Topicals'],
        'COLDCHAIN' => ['name' => 'Cold chain'],
        'VACCINES' => ['name' => 'Vaccines'],
        'INFUSIONS' => ['name' => 'Infusions'],
        'INJECTIONS' => ['name' => 'Injections'],
        'MEDSUPPLIES' => ['name' => 'Medical supplies'],
        'MEDEQUIP' => ['name' => 'Medical equipment'],
        'NUTRITION' => ['name' => 'Nutritional products'],
        'LABREAGENTS' => ['name' => 'Lab reagents'],
    ];

    /** Dosage form → heading, for products that have no category yet. */
    private const FORM_TO_CATEGORY = [
        'INJ' => 'INJECTIONS',
        'INF' => 'INFUSIONS',
        'CRM' => 'TOPICALS',
        'OINT' => 'TOPICALS',
        'GEL' => 'TOPICALS',
        'LOT' => 'TOPICALS',
        'DEV' => 'MEDSUPPLIES',
    ];

    /**
     * Words in a product name → heading, tried in this order. The first match
     * wins, so an "infusion" is never mistaken for a plain drug.
     *
     * @var array<string, list<string>>
     */
    private const NAME_TO_CATEGORY = [
        'VACCINES' => ['VACCINE'],
        'INFUSIONS' => ['INFUSION', 'DEXTROSE', 'NORMAL SALINE', 'RINGER', 'IV FLUID', 'HARTMANN'],
        'INJECTIONS' => ['INJ', 'INJECTION', 'AMPOULE', 'AMP', 'VIAL'],
        'LABREAGENTS' => ['REAGENT', 'TEST KIT', 'RAPID TEST', 'RDT', 'TEST STRIP'],
        'MEDEQUIP' => ['THERMOMETER', 'BP MACHINE', 'GLUCOMETER', 'NEBULI[SZ]ER', 'STETHOSCOPE', 'OXIMETER', 'WHEELCHAIR', 'CRUTCH'],
        'MEDSUPPLIES' => ['DRESSING', 'GAUZE', 'SYRINGE', 'GLOVES?', 'CANNULA', 'BANDAGE', 'COTTON', 'CATHETER', 'NEEDLE', 'PLASTER', 'MASK', 'SUTURE', 'GIVING SET', 'URINE BAG', 'STRAPPING', 'CREPE'],
        'NUTRITION' => ['FORMULA', 'NUTRI', 'ENSURE', 'PEDIASURE', 'CERELAC', 'LACTOGEN', 'NAN [0-9]', 'SMA', 'BABY MILK'],
        'TOPICALS' => ['CREAM', 'OINTMENT', 'OINT', 'GEL', 'LOTION', 'BALM', 'EMULSION', 'SHAMPOO'],
        'DRUGS-OTH' => ['TABS?', 'TABLETS?', 'CAPS?', 'CAPSULES?', 'SYRUP', 'SYR', 'SUSPENSION', 'SUSP', 'DROPS', '[0-9]+ ?MG'],
    ];

    public function run(): void
    {
        $ids = [];
        foreach (self::TREE as $code => $heading) {
            $parent = $this->shared($code, $heading['name'], null);
            $ids[$code] = $parent->id;
            foreach ($heading['children'] ?? [] as $childCode => $childName) {
                $ids[$childCode] = $this->shared($childCode, $childName, $parent->id)->id;
            }
        }

        $this->assignFirstGuesses($ids);
    }

    private function shared(string $code, string $name, ?string $parentId): ProductCategory
    {
        return ProductCategory::query()->whereNull('organisation_id')->where('code', $code)->first()
            ?? ProductCategory::create(['code' => $code, 'name' => $name, 'parent_id' => $parentId, 'is_active' => true]);
    }

    /**
     * @param  array<string, string>  $ids
     */
    private function assignFirstGuesses(array $ids): void
    {
        $coldConditionIds = StorageCondition::query()->where('requires_cold_chain', true)->pluck('id');
        Product::query()->whereNull('category_id')->whereIn('storage_condition_id', $coldConditionIds)->update(['category_id' => $ids['COLDCHAIN']]);

        foreach (self::FORM_TO_CATEGORY as $formCode => $categoryCode) {
            $formIds = DosageForm::query()->where('code', $formCode)->pluck('id');
            Product::query()->whereNull('category_id')->whereIn('dosage_form_id', $formIds)->update(['category_id' => $ids[$categoryCode]]);
        }

        Product::query()->whereNull('category_id')->whereNotNull('dosage_form_id')->update(['category_id' => $ids['DRUGS-OTH']]);

        foreach (self::NAME_TO_CATEGORY as $categoryCode => $words) {
            // Whole words only: "GEL" must not match "ANGEL". Written without
            //  so MySQL 8 (ICU) and MariaDB (PCRE) read it the same way.
            $pattern = '(^|[^A-Z])('.implode('|', $words).')([^A-Z]|$)';
            Product::query()->whereNull('category_id')->whereRaw('UPPER(name) REGEXP ?', [$pattern])->update(['category_id' => $ids[$categoryCode]]);
        }
    }
}
