<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Organisation;
use App\Services\Documents\PaymentDetails;
use App\Services\Documents\PdfRenderer;
use App\Services\Pricing\MasterPriceList;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The master price list, set from Stock on Hand: one price per product in
 * stock that the POS charges in every mode, and a letterheaded PDF of it.
 */
class MasterPriceListController extends ApiController
{
    /** GET /api/price-lists/master — the products in stock with their cost and price now. */
    public function show(Request $request, MasterPriceList $master): JsonResponse
    {
        $this->requirePermission($request, 'product.view');
        $organisationId = $this->organisationId($request);
        $list = $master->find($organisationId);

        return response()->json([
            'price_list' => $list?->only(['id', 'code', 'name', 'updated_at']),
            'rows' => $master->sheet($organisationId, $request->user()->can('product.cost.view')),
        ]);
    }

    /** PUT /api/price-lists/master — saves the prices; the POS charges them from now on. */
    public function store(Request $request, MasterPriceList $master): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:20000'],
            'items.*.product_id' => ['required', 'uuid', 'distinct', TenantRules::exists('products')],
            'items.*.price' => ['required', 'numeric', 'gt:0', 'max:99999999'],
        ]);
        set_time_limit(300);

        return response()->json($master->save($this->organisationId($request), $request->user()->id, $data['items']));
    }

    /** GET /api/price-lists/master/pdf — the saved list on the institution's letterhead. */
    public function pdf(Request $request, MasterPriceList $master, PaymentDetails $payment, PdfRenderer $pdf): Response
    {
        $this->requirePermission($request, 'product.view');
        $organisationId = $this->organisationId($request);
        $rows = $master->printable($organisationId);
        if ($rows === []) {
            return $this->error('MASTER_PRICE_LIST_EMPTY', 'No master price list has been saved yet.', 422);
        }
        set_time_limit(300);

        $name = Organisation::whereKey($organisationId)->value('name') ?? 'Master';
        AuditLog::record('PRICE_LIST_EXPORTED', 'organisation', $organisationId, ['reference' => MasterPriceList::CODE, 'after_json' => ['format' => 'PDF', 'rows' => count($rows)]]);

        return $pdf->render('pdf.price-list', [
            'title' => 'Master Price List',
            'date' => now()->format('j M Y'),
            'columns' => ['code', 'name', 'brand_name', 'unit', 'price'],
            'labels' => ['code' => 'Code', 'name' => 'Product', 'brand_name' => 'Brand', 'unit' => 'Unit', 'price' => 'Price (KES)'],
            'rows' => $rows,
            'payment' => $payment->for($organisationId),
        ], $name.' - Master Price List '.now()->format('Y-m-d'), $this->branchId($request));
    }
}
