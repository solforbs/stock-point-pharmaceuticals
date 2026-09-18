<?php

namespace App\Services\Inventory;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Part 5 — catalogue maintenance in bulk. Staff export the product list to
 * CSV, correct categories, tax codes and replenishment parameters in Excel,
 * and import the file back. Only existing products are updated (a product
 * is never created from a spreadsheet: it needs its units of measure set up
 * deliberately), a blank cell leaves the value unchanged, and the file is
 * all-or-nothing exactly like the opening-stock import: one bad row writes
 * nothing.
 */
class ProductImportService
{
    /** Columns the import reads; anything else in the file (name, default_price…) is ignored. */
    public const COLUMNS = ['code', 'category_code', 'tax_code', 'reorder_point', 'safety_stock', 'lead_time_days', 'generic_name', 'strength', 'is_active'];

    private const TRUE_WORDS = ['1', 'true', 'yes', 'y', 'active'];

    private const FALSE_WORDS = ['0', 'false', 'no', 'n', 'inactive'];

    /** @var array<string, string> category id => code, for the change preview */
    private array $categoryCodes = [];

    /** @var array<string, string> tax code id => code, for the change preview */
    private array $taxCodeCodes = [];

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{rows: int, updated: int, unchanged: int, categories_created: list<string>, dry_run: bool, changes: list<array{code: string, fields: array<string, array{from: mixed, to: mixed}>}>}
     *
     * @throws ProductImportValidationException
     */
    public function import(string $organisationId, array $rows, bool $dryRun, bool $createMissingCategories, ?int $userId): array
    {
        [$plans, $newCategories, $errors] = $this->validate($organisationId, $rows, $createMissingCategories);
        if ($errors !== []) {
            throw new ProductImportValidationException($errors);
        }

        $changed = array_values(array_filter($plans, fn (array $plan) => $plan['changes'] !== []));
        $summary = [
            'rows' => count($rows),
            'updated' => count($changed),
            'unchanged' => count($plans) - count($changed),
            'categories_created' => array_values($newCategories),
            'dry_run' => $dryRun,
            'changes' => array_map(fn (array $plan) => ['code' => $plan['product']->code, 'fields' => $plan['changes']], $changed),
        ];

        if ($dryRun) {
            return $summary;
        }

        DB::transaction(function () use ($organisationId, $changed, $newCategories, $userId, $summary) {
            $createdIds = [];
            foreach ($newCategories as $key => $code) {
                $createdIds[$key] = ProductCategory::create(['code' => $code, 'name' => $code, 'is_active' => true])->id;
            }

            foreach ($changed as $plan) {
                $values = $plan['values'];
                if (array_key_exists('category_key', $values)) {
                    $values['category_id'] = $createdIds[$values['category_key']];
                    unset($values['category_key']);
                }
                $plan['product']->update($values + ['updated_by' => $userId]);
            }

            AuditLog::record('PRODUCTS_IMPORTED', 'organisation', $organisationId, [
                'user_id' => $userId,
                'reference' => 'PRODUCT-IMPORT-'.now()->format('YmdHis'),
                'after_json' => [
                    'rows' => $summary['rows'],
                    'updated' => $summary['updated'],
                    'unchanged' => $summary['unchanged'],
                    'categories_created' => $summary['categories_created'],
                    'updated_codes' => array_slice(array_map(fn (array $c) => $c['code'], $summary['changes']), 0, 500),
                ],
            ]);
        });

        return $summary;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<array{product: Product, values: array<string, mixed>, changes: array<string, array{from: mixed, to: mixed}>}>, 1: array<string, string>, 2: array<int, list<string>>}
     */
    private function validate(string $organisationId, array $rows, bool $createMissingCategories): array
    {
        $codes = collect($rows)->map(fn ($row) => trim((string) ($row['code'] ?? '')))->filter()->unique()->values()->all();
        /** @var Collection<string, Product> $products */
        $products = Product::where('organisation_id', $organisationId)->whereIn('code', $codes)->get()
            ->keyBy(fn (Product $p) => Str::upper($p->code));
        $categories = ProductCategory::all()->keyBy(fn (ProductCategory $c) => Str::upper($c->code));
        $taxCodes = TaxCode::where('organisation_id', $organisationId)->get()->keyBy(fn (TaxCode $t) => Str::upper($t->code));
        $this->categoryCodes = $categories->mapWithKeys(fn (ProductCategory $c) => [$c->id => $c->code])->all();
        $this->taxCodeCodes = $taxCodes->mapWithKeys(fn (TaxCode $t) => [$t->id => $t->code])->all();

        $plans = [];
        $errors = [];
        $newCategories = [];
        $seen = [];

        foreach ($rows as $i => $raw) {
            $line = $i + 1;
            $messages = [];
            $values = [];
            $labels = [];
            $cell = fn (string $key): string => trim((string) ($raw[$key] ?? ''));

            $code = $cell('code');
            $product = $products->get(Str::upper($code));
            if ($code === '') {
                $messages[] = 'code is required';
            } elseif (! $product) {
                $messages[] = "unknown product code {$code} (the import only updates existing products)";
            } elseif (isset($seen[Str::upper($code)])) {
                $messages[] = "product {$code} appears twice in this file (row {$seen[Str::upper($code)]})";
            }
            if ($code !== '') {
                $seen[Str::upper($code)] ??= $line;
            }

            if (($categoryCode = $cell('category_code')) !== '') {
                $category = $categories->get(Str::upper($categoryCode));
                if ($category && ! $category->is_active) {
                    $messages[] = "category {$categoryCode} is inactive";
                } elseif ($category) {
                    $values['category_id'] = $category->id;
                    $labels['category_id'] = $category->code;
                } elseif ($createMissingCategories) {
                    if (mb_strlen($categoryCode) > 255) {
                        $messages[] = 'category_code may not exceed 255 characters';
                    } else {
                        $newCategories[Str::upper($categoryCode)] ??= $categoryCode;
                        $values['category_key'] = Str::upper($categoryCode);
                    }
                } else {
                    $messages[] = "unknown category code {$categoryCode} (tick 'create missing categories' to add it)";
                }
            }

            if (($taxCode = $cell('tax_code')) !== '') {
                $tax = $taxCodes->get(Str::upper($taxCode));
                if (! $tax) {
                    $messages[] = "unknown tax code {$taxCode}";
                } elseif (! $tax->is_active) {
                    $messages[] = "tax code {$taxCode} is inactive";
                } else {
                    $values['tax_code_id'] = $tax->id;
                    $labels['tax_code_id'] = $tax->code;
                }
            }

            foreach (['reorder_point', 'safety_stock'] as $field) {
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

            foreach (['generic_name', 'strength'] as $field) {
                if (($text = $cell($field)) !== '') {
                    if (mb_strlen($text) > 255) {
                        $messages[] = "{$field} may not exceed 255 characters";
                    } else {
                        $values[$field] = $text;
                    }
                }
            }

            if (($active = Str::lower($cell('is_active'))) !== '') {
                if (in_array($active, self::TRUE_WORDS, true)) {
                    $values['is_active'] = true;
                } elseif (in_array($active, self::FALSE_WORDS, true)) {
                    $values['is_active'] = false;
                } else {
                    $messages[] = 'is_active must be 1/0, yes/no or true/false';
                }
            }

            if ($messages !== []) {
                $errors[$line] = $messages;

                continue;
            }
            if (! $product) {
                continue;
            }

            [$values, $changes] = $this->diff($product, $values, $labels + $newCategories);
            $plans[] = ['product' => $product, 'values' => $values, 'changes' => $changes];
        }

        if ($rows === []) {
            $errors[0] = ['the file has no rows'];
        }

        // Only categories some valid row actually needs are created.
        $needed = collect($plans)->pluck('values.category_key')->filter()->unique()->all();
        $newCategories = array_intersect_key($newCategories, array_flip($needed));

        return [$plans, $newCategories, $errors];
    }

    /**
     * Keeps only the values that differ from the product as stored.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $labels  display codes for category/tax ids and new category keys
     * @return array{0: array<string, mixed>, 1: array<string, array{from: mixed, to: mixed}>}
     */
    private function diff(Product $product, array $values, array $labels): array
    {
        $kept = [];
        $changes = [];
        foreach ($values as $field => $to) {
            $from = match ($field) {
                'category_key' => $this->categoryCodes[$product->category_id] ?? null,
                'category_id' => $product->category_id,
                default => $product->{$field},
            };
            $same = match ($field) {
                'reorder_point', 'safety_stock' => bccomp((string) $from, (string) $to, 4) === 0,
                'lead_time_days' => (int) $from === $to,
                'is_active' => (bool) $from === $to,
                'category_key' => false,
                default => (string) $from === (string) $to,
            };
            if ($same) {
                continue;
            }

            $kept[$field] = $to;
            $label = match ($field) {
                'category_id', 'category_key' => 'category_code',
                'tax_code_id' => 'tax_code',
                default => $field,
            };
            $changes[$label] = match ($field) {
                'category_id' => ['from' => $this->categoryCodes[$product->category_id] ?? null, 'to' => $labels['category_id'] ?? $to],
                'category_key' => ['from' => $from, 'to' => $labels[$to] ?? $to],
                'tax_code_id' => ['from' => $this->taxCodeCodes[$product->tax_code_id] ?? null, 'to' => $labels['tax_code_id'] ?? $to],
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
}
