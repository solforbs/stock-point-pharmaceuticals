<?php

namespace App\Services\Inventory;

use App\Models\AuditLog;
use App\Models\DosageForm;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUom;
use App\Models\StorageCondition;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Part 5 — the catalogue in bulk. Staff export the product list, correct it
 * in Excel (or bring a supplier's price list into the same columns) and
 * import it back as CSV or .xlsx. A row whose code is already in the
 * catalogue updates that product; a row with a new code creates one, given
 * a name and a base unit (and `product.create`). A blank cell leaves the
 * value unchanged, and the file is all-or-nothing exactly like the
 * opening-stock import: one bad row writes nothing.
 */
class ProductImportService
{
    /** Columns the import reads; anything else in the file is ignored. */
    public const COLUMNS = [
        'code', 'name', 'brand_name', 'sku', 'gtin', 'strength', 'description',
        'category_code', 'category_name', 'parent_category_code', 'parent_category_name',
        'dosage_form_code', 'storage_condition_code', 'base_uom', 'tax_code', 'default_price',
        'reorder_point', 'safety_stock', 'lead_time_days', 'requires_batch', 'is_active',
    ];

    private const TRUE_WORDS = ['1', 'true', 'yes', 'y', 'active'];

    private const FALSE_WORDS = ['0', 'false', 'no', 'n', 'inactive'];

    /** Columns that hold a reference, and the file column that names it. */
    private const REFERENCE_LABELS = [
        'category_id' => 'category_code',
        'category_key' => 'category_code',
        'tax_code_id' => 'tax_code',
        'dosage_form_id' => 'dosage_form_code',
        'storage_condition_id' => 'storage_condition_code',
    ];

    /** @var array<string, array<string, string>> reference column => id => code, for the change preview */
    private array $codesById = [];

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{rows: int, created: int, updated: int, unchanged: int, categories_created: list<string>, dry_run: bool, created_codes: list<string>, changes: list<array{code: string, fields: array<string, array{from: mixed, to: mixed}>}>}
     *
     * @throws ProductImportValidationException
     */
    public function import(string $organisationId, array $rows, bool $dryRun, bool $createMissingCategories, ?int $userId, bool $canCreate = false): array
    {
        [$plans, $newProducts, $newCategories, $errors] = $this->validate($organisationId, $rows, $createMissingCategories, $canCreate);
        if ($errors !== []) {
            throw new ProductImportValidationException($errors);
        }

        $changed = array_values(array_filter($plans, fn (array $plan) => $plan['changes'] !== []));
        $summary = [
            'rows' => count($rows),
            'created' => count($newProducts),
            'updated' => count($changed),
            'unchanged' => count($plans) - count($changed),
            'categories_created' => array_values(array_map(fn (array $c) => $c['code'], $newCategories)),
            'dry_run' => $dryRun,
            'created_codes' => array_map(fn (array $p) => $p['code'], $newProducts),
            'changes' => array_map(fn (array $plan) => ['code' => $plan['product']->code, 'fields' => $plan['changes']], $changed),
        ];

        if ($dryRun) {
            return $summary;
        }

        DB::transaction(function () use ($organisationId, $changed, $newProducts, $newCategories, $userId, $summary) {
            // Parents are registered before the children that name them.
            $createdIds = [];
            foreach ($newCategories as $key => $category) {
                $createdIds[$key] = ProductCategory::create([
                    'code' => $category['code'],
                    'name' => $category['name'],
                    'parent_id' => $category['parent_id'] ?? ($category['parent_key'] !== null ? $createdIds[$category['parent_key']] : null),
                    'is_active' => true,
                ])->id;
            }
            $resolveCategory = function (array $values) use ($createdIds): array {
                if (array_key_exists('category_key', $values)) {
                    $values['category_id'] = $createdIds[$values['category_key']];
                    unset($values['category_key']);
                }

                return $values;
            };

            foreach ($changed as $plan) {
                $plan['product']->update($resolveCategory($plan['values']) + ['updated_by' => $userId]);
            }

            foreach ($newProducts as $values) {
                $product = Product::create($resolveCategory($values) + [
                    'organisation_id' => $organisationId,
                    'is_active' => true,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                // As in ProductController::store: without its base unit row a
                // product cannot be sold or converted.
                ProductUom::create([
                    'product_id' => $product->id,
                    'uom_id' => $product->base_uom_id,
                    'factor_to_base' => 1,
                    'is_base' => true,
                    'is_purchase' => true,
                    'is_sales' => true,
                    'is_default_sales' => true,
                ]);
            }

            AuditLog::record('PRODUCTS_IMPORTED', 'organisation', $organisationId, [
                'user_id' => $userId,
                'reference' => 'PRODUCT-IMPORT-'.now()->format('YmdHis'),
                'after_json' => [
                    'rows' => $summary['rows'],
                    'created' => $summary['created'],
                    'updated' => $summary['updated'],
                    'unchanged' => $summary['unchanged'],
                    'categories_created' => $summary['categories_created'],
                    'created_codes' => array_slice($summary['created_codes'], 0, 500),
                    'updated_codes' => array_slice(array_map(fn (array $c) => $c['code'], $summary['changes']), 0, 500),
                ],
            ]);
        });

        return $summary;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<array{product: Product, values: array<string, mixed>, changes: array<string, array{from: mixed, to: mixed}>}>, 1: list<array<string, mixed>>, 2: array<string, array{code: string, name: string, parent_id?: ?string, parent_key: ?string}>, 3: array<int, list<string>>}
     */
    private function validate(string $organisationId, array $rows, bool $createMissingCategories, bool $canCreate): array
    {
        $byCode = fn (Collection $models) => $models->keyBy(fn ($m) => Str::upper($m->code));

        $codes = collect($rows)->map(fn ($row) => trim((string) ($row['code'] ?? '')))->filter()->unique()->values()->all();
        /** @var Collection<string, Product> $products */
        $products = $byCode(Product::where('organisation_id', $organisationId)->whereIn('code', $codes)->get());
        $skus = Product::where('organisation_id', $organisationId)->whereNotNull('sku')->pluck('code', 'sku')
            ->mapWithKeys(fn ($code, $sku) => [Str::upper((string) $sku) => $code]);
        $categories = $byCode(ProductCategory::all());
        $taxCodes = $byCode(TaxCode::where('organisation_id', $organisationId)->get());
        $dosageForms = $byCode(DosageForm::all());
        $storageConditions = $byCode(StorageCondition::all());
        $uoms = $byCode(UnitOfMeasure::all());

        $this->codesById = [
            'category_id' => $categories->mapWithKeys(fn ($c) => [$c->id => $c->code])->all(),
            'tax_code_id' => $taxCodes->mapWithKeys(fn ($t) => [$t->id => $t->code])->all(),
            'dosage_form_id' => $dosageForms->mapWithKeys(fn ($d) => [$d->id => $d->code])->all(),
            'storage_condition_id' => $storageConditions->mapWithKeys(fn ($s) => [$s->id => $s->code])->all(),
        ];

        $plans = [];
        $newProducts = [];
        $errors = [];
        $newCategories = [];
        $seen = [];
        $seenSkus = [];

        foreach ($rows as $i => $raw) {
            $line = $i + 1;
            $messages = [];
            $values = [];
            $labels = [];
            $cell = fn (string $key): string => $this->unescape(trim((string) ($raw[$key] ?? '')));

            $code = $cell('code');
            $product = $products->get(Str::upper($code));
            if ($code === '') {
                $messages[] = 'code is required';
            } elseif (isset($seen[Str::upper($code)])) {
                $messages[] = "product {$code} appears twice in this file (row {$seen[Str::upper($code)]})";
            } elseif (mb_strlen($code) > 255) {
                $messages[] = 'code may not exceed 255 characters';
            } elseif (! $product && ! $canCreate) {
                $messages[] = "unknown product code {$code} (creating products needs the product.create permission)";
            } elseif (! $product && ($cell('name') === '' || $cell('base_uom') === '')) {
                $messages[] = "unknown product code {$code}: give a name and base_uom to create it";
            }
            if ($code !== '') {
                $seen[Str::upper($code)] ??= $line;
            }

            foreach (['name', 'brand_name', 'strength', 'gtin'] as $field) {
                if (($text = $cell($field)) !== '') {
                    if (mb_strlen($text) > 255) {
                        $messages[] = "{$field} may not exceed 255 characters";
                    } else {
                        $values[$field] = $text;
                    }
                }
            }

            if (($sku = $cell('sku')) !== '') {
                $holder = $skus->get(Str::upper($sku));
                if (mb_strlen($sku) > 255) {
                    $messages[] = 'sku may not exceed 255 characters';
                } elseif ($holder !== null && Str::upper($holder) !== Str::upper($code)) {
                    $messages[] = "sku {$sku} already belongs to product {$holder}";
                } elseif (isset($seenSkus[Str::upper($sku)])) {
                    $messages[] = "sku {$sku} appears twice in this file (row {$seenSkus[Str::upper($sku)]})";
                } else {
                    $seenSkus[Str::upper($sku)] = $line;
                    $values['sku'] = $sku;
                }
            }

            if (($description = $cell('description')) !== '') {
                if (mb_strlen($description) > 500) {
                    $messages[] = 'description may not exceed 500 characters';
                } else {
                    $values['description'] = $description;
                }
            }

            if (($categoryCode = $cell('category_code')) !== '') {
                $category = $categories->get(Str::upper($categoryCode));
                if ($category && ! $category->is_active) {
                    $messages[] = "category {$categoryCode} is inactive";
                } elseif ($category) {
                    $values['category_id'] = $category->id;
                    $labels['category_id'] = $category->code;
                } elseif (! $createMissingCategories) {
                    $messages[] = "unknown category code {$categoryCode} (tick 'create missing categories' to add it)";
                } else {
                    $parentKey = null;
                    $parentId = null;
                    if (($parentCode = $cell('parent_category_code')) !== '') {
                        $parent = $categories->get(Str::upper($parentCode));
                        if ($parent) {
                            $parentId = $parent->id;
                        } else {
                            $parentKey = Str::upper($parentCode);
                            $newCategories[$parentKey] ??= ['code' => $parentCode, 'name' => $cell('parent_category_name') ?: $parentCode, 'parent_id' => null, 'parent_key' => null];
                        }
                    }
                    $key = Str::upper($categoryCode);
                    $newCategories[$key] ??= ['code' => $categoryCode, 'name' => $cell('category_name') ?: $categoryCode, 'parent_id' => $parentId, 'parent_key' => $parentKey];
                    $values['category_key'] = $key;
                    $labels[$key] = $categoryCode;
                }
                foreach ([$categoryCode, $cell('parent_category_code')] as $categoryLength) {
                    if (mb_strlen($categoryLength) > 255) {
                        $messages[] = 'category codes may not exceed 255 characters';
                    }
                }
            }

            $references = [
                'tax_code' => ['tax_code_id', $taxCodes, 'tax code'],
                'dosage_form_code' => ['dosage_form_id', $dosageForms, 'dosage form'],
                'storage_condition_code' => ['storage_condition_id', $storageConditions, 'storage condition'],
            ];
            foreach ($references as $column => [$field, $known, $noun]) {
                if (($reference = $cell($column)) === '') {
                    continue;
                }
                $model = $known->get(Str::upper($reference));
                if (! $model) {
                    $messages[] = "unknown {$noun} {$reference}";
                } elseif (isset($model->is_active) && ! $model->is_active) {
                    $messages[] = "{$noun} {$reference} is inactive";
                } else {
                    $values[$field] = $model->id;
                    $labels[$field] = $model->code;
                }
            }

            if (($uomCode = $cell('base_uom')) !== '') {
                $uom = $uoms->get(Str::upper($uomCode));
                if (! $uom) {
                    $messages[] = "unknown unit of measure {$uomCode} (use one of ".$uoms->keys()->implode(', ').')';
                } elseif ($product && $product->base_uom_id !== $uom->id) {
                    $messages[] = 'base_uom cannot be changed by import; edit the product instead';
                } else {
                    $values['base_uom_id'] = $uom->id;
                }
            }

            foreach (['reorder_point', 'safety_stock', 'default_price'] as $field) {
                if (($value = $cell($field)) !== '') {
                    $decimal = $this->decimal($value);
                    if ($decimal === null || bccomp($decimal, '0', 4) < 0) {
                        $messages[] = "{$field} must be a number of zero or more";
                    } else {
                        $values[$field] = $decimal;
                    }
                }
            }

            if (($lead = $cell('lead_time_days')) !== '') {
                if (preg_match('/^\d{1,5}$/', $lead) !== 1 || (int) $lead > 65535) {
                    $messages[] = 'lead_time_days must be a whole number of days';
                } else {
                    $values['lead_time_days'] = (int) $lead;
                }
            }

            foreach (['requires_batch', 'is_active'] as $field) {
                if (($flag = Str::lower($cell($field))) === '') {
                    continue;
                }
                if (in_array($flag, self::TRUE_WORDS, true)) {
                    $values[$field] = true;
                } elseif (in_array($flag, self::FALSE_WORDS, true)) {
                    $values[$field] = false;
                } else {
                    $messages[] = "{$field} must be 1/0, yes/no or true/false";
                }
            }

            if ($messages !== []) {
                $errors[$line] = $messages;

                continue;
            }

            if (! $product) {
                $newProducts[] = ['code' => $code] + $values;

                continue;
            }

            unset($values['base_uom_id']);
            [$values, $changes] = $this->diff($product, $values, $labels);
            $plans[] = ['product' => $product, 'values' => $values, 'changes' => $changes];
        }

        if ($rows === []) {
            $errors[0] = ['the file has no rows'];
        }

        // Only categories some valid row actually needs are created, with
        // the parents they hang from.
        $needed = collect($plans)->pluck('values.category_key')->merge(array_column($newProducts, 'category_key'))->filter()->unique()->all();
        foreach ($needed as $key) {
            if (($parentKey = $newCategories[$key]['parent_key'] ?? null) !== null) {
                $needed[] = $parentKey;
            }
        }
        $newCategories = array_intersect_key($newCategories, array_flip($needed));

        return [$plans, $newProducts, $newCategories, $errors];
    }

    /**
     * Keeps only the values that differ from the product as stored.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $labels  display codes for reference ids and new category keys
     * @return array{0: array<string, mixed>, 1: array<string, array{from: mixed, to: mixed}>}
     */
    private function diff(Product $product, array $values, array $labels): array
    {
        $kept = [];
        $changes = [];
        foreach ($values as $field => $to) {
            $from = match ($field) {
                'category_key' => $this->codesById['category_id'][$product->category_id] ?? null,
                default => $product->{$field},
            };
            $same = match ($field) {
                'reorder_point', 'safety_stock', 'default_price' => $from !== null && bccomp((string) $from, (string) $to, 4) === 0,
                'lead_time_days' => (int) $from === $to,
                'is_active', 'requires_batch' => (bool) $from === $to,
                'category_key' => false,
                default => (string) $from === (string) $to,
            };
            if ($same) {
                continue;
            }

            $kept[$field] = $to;
            $changes[self::REFERENCE_LABELS[$field] ?? $field] = match ($field) {
                'category_key' => ['from' => $from, 'to' => $labels[$to] ?? $to],
                'category_id', 'tax_code_id', 'dosage_form_id', 'storage_condition_id' => ['from' => $this->codesById[$field][$from] ?? null, 'to' => $labels[$field] ?? $to],
                default => ['from' => $from, 'to' => $to],
            };
        }

        return [$kept, $changes];
    }

    private function decimal(string $value): ?string
    {
        $clean = str_replace([',', ' '], '', $value);

        return is_numeric($clean) ? bcadd($clean, '0', 4) : null;
    }

    /** Undoes the export's formula guard: '=X comes back as =X. */
    private function unescape(string $value): string
    {
        return preg_match("/^'[=+\-@]/", $value) === 1 ? substr($value, 1) : $value;
    }
}
