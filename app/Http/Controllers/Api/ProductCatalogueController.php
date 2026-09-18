<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Inventory\ProductImportService;
use App\Services\Inventory\ProductImportValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Part 5 — catalogue maintenance: the product category tree and the bulk
 * attribute round trip (export to CSV, edit in Excel, import back).
 */
class ProductCatalogueController extends ApiController
{
    /** The CSV columns, in order; the import reads the ones listed in ProductImportService::COLUMNS. */
    private const EXPORT_COLUMNS = ['code', 'name', 'category_code', 'tax_code', 'default_price', 'reorder_point', 'safety_stock', 'lead_time_days', 'generic_name', 'strength', 'is_active'];

    /** GET /api/product-categories/all — every category, inactive included, with its product count. */
    public function categories(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'product.view');
        $organisationId = $this->organisationId($request);

        $counts = Product::where('organisation_id', $organisationId)->whereNotNull('category_id')
            ->groupBy('category_id')->selectRaw('category_id, COUNT(*) as n')->pluck('n', 'category_id');

        return response()->json(
            ProductCategory::with('parent:id,code,name')->orderBy('code')->get()
                ->map(fn (ProductCategory $c) => $c->only(['id', 'code', 'name', 'parent_id', 'is_active']) + [
                    'parent' => $c->parent?->only(['id', 'code', 'name']),
                    'products_count' => (int) ($counts[$c->id] ?? 0),
                ])->values()
        );
    }

    /** POST /api/product-categories */
    public function storeCategory(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'product.edit');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:product_categories,code'],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'uuid', 'exists:product_categories,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = ProductCategory::create($data + ['is_active' => $data['is_active'] ?? true]);
        AuditLog::record('PRODUCT_CATEGORY_CREATED', 'product_category', $category->id, ['reference' => $category->code, 'after_json' => $category->only(['code', 'name', 'parent_id', 'is_active'])]);

        return response()->json($category->fresh(), 201);
    }

    /** PATCH /api/product-categories/{id} — a category can never become its own ancestor. */
    public function updateCategory(Request $request, string $category): JsonResponse
    {
        $this->requirePermission($request, 'product.edit');
        $category = ProductCategory::findOrFail($category);

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('product_categories', 'code')->ignore($category->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['nullable', 'uuid', 'exists:product_categories,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (! empty($data['parent_id']) && $this->wouldCycle($category, (string) $data['parent_id'])) {
            return $this->error('CATEGORY_CYCLE', 'A category cannot sit under itself or one of its own sub-categories.', 422);
        }

        $audited = ['code', 'name', 'parent_id', 'is_active'];
        $before = $category->only($audited);
        $category->update($data);
        AuditLog::record('PRODUCT_CATEGORY_UPDATED', 'product_category', $category->id, [
            'reference' => $category->code,
            'before_json' => $before,
            'after_json' => $category->only($audited),
            'changed_fields' => array_keys($data),
        ]);

        return response()->json($category->fresh());
    }

    /**
     * POST /api/products/import — bulk attribute update from a spreadsheet.
     * All-or-nothing; errors come back per row like the opening-stock import.
     */
    public function import(Request $request, ProductImportService $importer): JsonResponse
    {
        $this->requirePermission($request, 'product.edit');

        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:10000'],
            'rows.*' => ['array'],
            'dry_run' => ['nullable', 'boolean'],
            'create_missing_categories' => ['nullable', 'boolean'],
        ]);

        try {
            $summary = $importer->import(
                $this->organisationId($request),
                array_values($data['rows']),
                $request->boolean('dry_run'),
                $request->boolean('create_missing_categories'),
                $request->user()->id,
            );
        } catch (ProductImportValidationException $e) {
            return $this->error('PRODUCT_IMPORT_INVALID', $e->getMessage(), 422, ['rows' => $e->rowErrors]);
        }

        return response()->json($summary, $summary['dry_run'] ? 200 : 201);
    }

    /** GET /api/products/export — the organisation's products as CSV, ready to edit and re-import. */
    public function export(Request $request): StreamedResponse
    {
        $this->requirePermission($request, 'product.view');
        $organisationId = $this->organisationId($request);

        return response()->streamDownload(function () use ($organisationId) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::EXPORT_COLUMNS, ',', '"', '');

            DB::table('products as p')
                ->leftJoin('product_categories as c', 'c.id', '=', 'p.category_id')
                ->leftJoin('tax_codes as t', 't.id', '=', 'p.tax_code_id')
                ->where('p.organisation_id', $organisationId)
                ->orderBy('p.code')
                ->select('p.id', 'p.code', 'p.name', 'c.code as category_code', 't.code as tax_code', 'p.default_price', 'p.reorder_point', 'p.safety_stock', 'p.lead_time_days', 'p.generic_name', 'p.strength', 'p.is_active')
                ->chunk(500, function ($products) use ($out) {
                    foreach ($products as $p) {
                        fputcsv($out, [
                            $p->code, $this->safeCell((string) $p->name), $p->category_code, $p->tax_code,
                            $p->default_price === null ? '' : $this->number((string) $p->default_price),
                            $this->number((string) $p->reorder_point), $this->number((string) $p->safety_stock),
                            $p->lead_time_days, $p->generic_name, $p->strength, $p->is_active ? '1' : '0',
                        ], ',', '"', '');
                    }
                });
            fclose($out);
        }, 'products-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function wouldCycle(ProductCategory $category, string $parentId): bool
    {
        $seen = [];
        $cursor = $parentId;
        while ($cursor !== null && ! isset($seen[$cursor])) {
            if ($cursor === $category->id) {
                return true;
            }
            $seen[$cursor] = true;
            $cursor = ProductCategory::whereKey($cursor)->value('parent_id');
        }

        return false;
    }

    /** Drops trailing zeros so Excel shows 12 rather than 12.0000. */
    private function number(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }

    /** A product name is never imported, so neutralising a leading formula character is safe. */
    private function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
