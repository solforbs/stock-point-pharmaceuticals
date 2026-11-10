<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Services\Documents\PdfRenderer;
use App\Services\Pricing\MasterPriceList;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The master price list, built from Stock on Hand: every product in stock
 * with one selling price per base unit (VAT exclusive) that the POS charges
 * across the whole institution. See MasterPriceList for how it is stored.
 */
class MasterPriceListController extends ApiController
{
    /** GET /api/inventory/master-price-list — the in-stock products with their current and saved prices. */
    public function show(Request $request, MasterPriceList $master): JsonResponse
    {
        $this->requirePermission($request, 'product.view');
        $organisationId = $this->organisationId($request);
        $list = $master->find($organisationId);

        return response()->json([
            'title' => $master->title($organisationId),
            'list' => $list?->only(['id', 'code', 'name', 'is_active', 'updated_at']),
            'products' => $master->rows($organisationId),
        ]);
    }

    /** PUT /api/inventory/master-price-list — saves the typed prices; unchanged prices are left alone. */
    public function save(Request $request, MasterPriceList $master): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $data = $request->validate([
            'prices' => ['required', 'array', 'min:1', 'max:20000'],
            'prices.*.product_id' => ['required', 'uuid', 'distinct', TenantRules::exists('products')],
            'prices.*.price' => ['required', 'numeric', 'decimal:0,4', 'gt:0', 'max:99999999999999'],
        ]);
        set_time_limit(300);

        $organisationId = $this->organisationId($request);
        $result = $master->save($organisationId, collect($data['prices'])->mapWithKeys(fn (array $row) => [$row['product_id'] => (string) $row['price']])->all());
        AuditLog::record('MASTER_PRICE_LIST_SAVED', 'price_list', $result['list']->id, [
            'reference' => $result['list']->code,
            'after_json' => ['changed' => $result['changed'], 'unchanged' => $result['unchanged']],
        ]);

        return response()->json([
            'list' => $result['list']->only(['id', 'code', 'name', 'is_active', 'updated_at']),
            'changed' => $result['changed'],
            'unchanged' => $result['unchanged'],
        ]);
    }

    /** GET /api/inventory/master-price-list/pdf — the saved list on the institution's letterhead. */
    public function pdf(Request $request, MasterPriceList $master, PdfRenderer $pdf): Response
    {
        $this->requirePermission($request, 'product.view');
        $organisationId = $this->organisationId($request);
        if ($master->find($organisationId) === null) {
            return $this->error('MASTER_PRICE_LIST_NOT_SAVED', 'Save the master price list before downloading it.', 422);
        }
        set_time_limit(300);

        $columns = array_keys(ProductCatalogueController::PRICE_LIST_COLUMNS);
        $rows = array_map(fn (array $p) => [
            'code' => $p['code'],
            'name' => $p['name'],
            'brand_name' => $p['brand_name'] ?? '',
            'price' => $p['master_price'],
        ], $master->rows($organisationId));
        AuditLog::record('PRICE_LIST_EXPORTED', 'organisation', $organisationId, ['after_json' => ['format' => 'PDF', 'source' => 'MASTER_PRICE_LIST', 'rows' => count($rows), 'columns' => $columns]]);

        return $pdf->render('pdf.price-list', [
            'title' => 'Master Price List',
            'date' => now()->format('j M Y'),
            'columns' => $columns,
            'labels' => ProductCatalogueController::PRICE_LIST_COLUMNS,
            'rows' => $rows,
            'payment' => $master->paymentDetails($organisationId),
        ], $master->title($organisationId).' '.now()->format('Y-m-d'), $this->branchId($request));
    }
}
