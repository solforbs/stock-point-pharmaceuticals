<?php

namespace App\Exceptions;

use App\Services\Finance\InvalidPaymentStatusException;
use App\Services\Finance\NoOpenPeriodException;
use App\Services\Finance\PaymentPeriodClosedException;
use App\Services\Finance\UnbalancedJournalException;
use App\Services\Inventory\BatchNotSellableException;
use App\Services\Inventory\InsufficientStockException;
use App\Services\Inventory\InvalidBatchTransitionException;
use App\Services\Inventory\InvalidCountStatusException;
use App\Services\Inventory\InvalidTransferStatusException;
use App\Services\Inventory\SecondApproverRequiredException;
use App\Services\Payroll\InvalidPayrollStatusException;
use App\Services\Pricing\PriceChangedException;
use App\Services\Pricing\QuoteExpiredException;
use App\Services\Pricing\QuoteNotFoundException;
use App\Services\Procurement\InvalidRequisitionStatusException;
use App\Services\Procurement\SupplierOverpaymentException;
use App\Services\Quality\InvalidExcursionStatusException;
use App\Services\Quality\InvalidRecallStatusException;
use App\Services\Quality\InvalidWasteStatusException;
use App\Services\Sales\ApprovalRequiredException;
use App\Services\Sales\CreditHoldException;
use App\Services\Sales\CreditLimitExceededException;
use App\Services\Sales\InvalidDeliveryNoteStatusException;
use App\Services\Sales\InvalidPickingListStatusException;
use App\Services\Sales\InvalidQuotationStatusException;
use App\Services\Sales\InvalidReturnStatusException;
use App\Services\Sales\InvalidSalesOrderStatusException;
use App\Services\Sales\OrderCreditLimitExceededException;
use App\Services\Sales\OrderOnCreditHoldException;
use App\Services\Sales\PaymentMismatchException;
use App\Services\Sales\SaleAlreadyVoidedException;
use App\Services\Sales\VoidPeriodClosedException;
use Illuminate\Http\JsonResponse;

/**
 * Part 21.15 — stable, machine-readable error codes. Every domain exception
 * becomes { "error": { "code", "message", "details" } } with the HTTP
 * status the blueprint specifies, so the POS can act on the code rather
 * than parse prose.
 */
class ApiErrorMap
{
    public static function toResponse(\Throwable $e): ?JsonResponse
    {
        [$code, $status, $details] = match (true) {
            $e instanceof InsufficientStockException => ['INSUFFICIENT_STOCK', 409, ['requested' => $e->requested, 'available' => $e->available, 'shortfall' => $e->shortfall]],
            $e instanceof BatchNotSellableException => [match ($e->status) {
                'RECALLED' => 'BATCH_RECALLED',
                'EXPIRED' => 'BATCH_EXPIRED',
                default => 'BATCH_QUARANTINED',
            }, 409, ['batch_number' => $e->batchNumber, 'status' => $e->status]],
            $e instanceof CreditLimitExceededException, $e instanceof OrderCreditLimitExceededException => ['CREDIT_LIMIT_EXCEEDED', 409, [
                'limit' => $e->limit, 'exposure' => $e->exposure, 'available' => bcsub($e->limit, $e->exposure, 4), 'shortfall' => $e->shortfall,
            ]],
            $e instanceof CreditHoldException, $e instanceof OrderOnCreditHoldException => ['CREDIT_HOLD', 409, []],
            $e instanceof QuoteExpiredException => ['QUOTE_EXPIRED', 409, ['quote_id' => $e->quoteId, 'expired_at' => $e->expiredAt->toIso8601String()]],
            $e instanceof QuoteNotFoundException => ['QUOTE_NOT_FOUND', 404, []],
            $e instanceof PriceChangedException => ['PRICE_CHANGED', 409, [
                'quote_id' => $e->quoteId, 'old_total' => $e->oldTotal, 'new_total' => $e->newTotal, 'changed_lines' => $e->changedLines, 'new_quote' => $e->newQuote,
            ]],
            $e instanceof ApprovalRequiredException => ['APPROVAL_REQUIRED', 202, ['lines' => $e->lineRefs]],
            $e instanceof PaymentMismatchException => ['PAYMENT_MISMATCH', 422, ['grand_total' => $e->grandTotal, 'tendered' => $e->tendered]],
            $e instanceof NoOpenPeriodException, $e instanceof VoidPeriodClosedException, $e instanceof PaymentPeriodClosedException => ['PERIOD_CLOSED', 422, []],
            $e instanceof UnbalancedJournalException => ['JOURNAL_UNBALANCED', 422, []],
            $e instanceof SupplierOverpaymentException => ['OVERPAYMENT', 422, []],
            $e instanceof SaleAlreadyVoidedException,
            $e instanceof InvalidPaymentStatusException,
            $e instanceof InvalidQuotationStatusException,
            $e instanceof InvalidSalesOrderStatusException,
            $e instanceof InvalidPickingListStatusException,
            $e instanceof InvalidDeliveryNoteStatusException,
            $e instanceof InvalidBatchTransitionException,
            $e instanceof InvalidTransferStatusException,
            $e instanceof InvalidCountStatusException,
            $e instanceof InvalidRequisitionStatusException,
            $e instanceof InvalidRecallStatusException,
            $e instanceof InvalidExcursionStatusException,
            $e instanceof InvalidWasteStatusException,
            $e instanceof InvalidReturnStatusException,
            $e instanceof InvalidPayrollStatusException => ['INVALID_STATE', 409, []],
            $e instanceof SecondApproverRequiredException => ['SECOND_APPROVER_REQUIRED', 422, ['variance_value' => $e->varianceValue, 'threshold' => $e->threshold]],
            $e instanceof \InvalidArgumentException, $e instanceof \DomainException => ['INVALID_INPUT', 422, []],
            default => [null, 0, []],
        };

        if ($code === null) {
            return null;
        }

        return response()->json(['error' => ['code' => $code, 'message' => $e->getMessage(), 'details' => (object) $details]], $status);
    }
}
