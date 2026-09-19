import { useMutation, useQueryClient } from '@tanstack/react-query'
import { api, getApiError, newIdempotencyKey, withIdempotency } from '../../lib/api'
import type { ApiError } from '../../lib/apiError'
import { useBranchStore } from '../../lib/branch'
import { dCmp, dSum } from '../../lib/decimal'
import { isUnreachableError } from '../../lib/offline/connectivity'
import { enqueueSale } from '../../lib/offline/outbox'
import { toast, toastApiError } from '../../lib/toast'
import type { CurrentUser, Sale } from '../../lib/types'
import { cartSignature, useCartStore, type PriceChange } from './cartStore'
import { isOfflineQuote } from './offlineQuote'

type CheckoutResult = { kind: 'posted'; sale: Sale } | { kind: 'approval'; lines: string[] } | { kind: 'queued'; sale: Sale }

const INLINE_CODES = new Set(['INSUFFICIENT_STOCK', 'PAYMENT_MISMATCH', 'CREDIT_LIMIT_EXCEEDED', 'CREDIT_HOLD', 'BATCH_EXPIRED', 'BATCH_RECALLED', 'BATCH_QUARANTINED'])

const OFFLINE_METHODS = new Set(['CASH', 'MPESA', 'CARD'])

/**
 * POST /api/sales/checkout — Part 10.3. Bound to the quote_id, carries the
 * attempt's Idempotency-Key (reused on retry, e.g. approve after a 202),
 * and acts on the blueprint's error codes rather than parsing prose.
 *
 * Part 16.7 — a cart priced offline is written to the device's outbox
 * instead, and the receipt says it is waiting to sync.
 */
export function useCheckout(user: CurrentUser | null | undefined) {
  const queryClient = useQueryClient()

  return useMutation({
    meta: { silent: true },
    // Never paused while the browser is offline: an offline sale is written
    // to the device, and an online one reports its own network failure.
    networkMode: 'always',
    mutationFn: async ({ approve }: { approve: boolean }): Promise<CheckoutResult> => {
      const s = useCartStore.getState()
      if (isOfflineQuote(s.quote)) return queueOffline(user)
      if (!s.quote?.quote_id || !s.storeId || !s.checkoutKey) throw new Error('No fresh quote to post.')
      s.setStatus('POSTING')
      s.setCheckoutError(null)
      const response = await api.post<Sale | ApiError>(
        '/api/sales/checkout',
        {
          quote_id: s.quote.quote_id,
          store_id: s.storeId,
          terminal_id: s.terminalId || null,
          payments: s.payments.map((p) => ({ method: p.method, amount: p.amount, reference: p.reference || null })),
          approve: approve || undefined,
        },
        withIdempotency(s.checkoutKey),
      )
      if (response.status === 202) {
        const body = response.data as ApiError
        const details = body.error?.details as { lines?: string[] } | undefined
        return { kind: 'approval', lines: details?.lines ?? [] }
      }
      return { kind: 'posted', sale: response.data as Sale }
    },
    onSuccess: (result) => {
      const s = useCartStore.getState()
      if (result.kind === 'approval') {
        s.setApprovalLines(result.lines)
        s.setStatus('AWAITING_APPROVAL')
        return
      }
      if (result.kind === 'queued') {
        s.setPostedSale(result.sale, true)
        toast.success('Sale saved on this till', 'It will be sent to the server as soon as the connection returns.')
        return
      }
      s.setUnconfirmed(null)
      s.setPostedSale(result.sale)
      queryClient.invalidateQueries({ queryKey: ['sales'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      queryClient.invalidateQueries({ queryKey: ['products'] })
      queryClient.invalidateQueries({ queryKey: ['customers'] })
      toast.success(`Posted ${result.sale.doc_number}`)
    },
    onError: (err) => {
      const e = getApiError(err)
      const s = useCartStore.getState()
      s.setStatus(s.quote?.approval_required ? 'AWAITING_APPROVAL' : 'PAYING')
      if (err instanceof OfflineRefusal) {
        s.setCheckoutError({ status: 0, code: err.code, message: err.message, details: {}, errors: {} })
        return
      }
      // No answer: the sale may or may not have posted. Remember the key so
      // that selling this same cart offline cannot post it a second time.
      if (isUnreachableError(err) && s.checkoutKey && !isOfflineQuote(s.quote)) {
        s.setUnconfirmed({ key: s.checkoutKey, signature: cartSignature(s) })
        s.setCheckoutError({ status: 0, code: 'CONNECTION_LOST', message: 'The server did not answer. The till is switching to offline selling; take payment again and the sale will sync when the connection returns.', details: {}, errors: {} })
        return
      }
      if (e.code === 'QUOTE_EXPIRED') {
        s.expireQuote()
        return
      }
      if (e.code === 'PRICE_CHANGED') {
        const d = e.details as unknown as PriceChange
        s.setPriceChange({ old_total: d.old_total, new_total: d.new_total, changed_lines: d.changed_lines ?? [], new_quote: d.new_quote })
        return
      }
      s.setCheckoutError(e)
      if (!INLINE_CODES.has(e.code)) toastApiError(err, 'Checkout failed')
    },
  })
}

async function queueOffline(user: CurrentUser | null | undefined): Promise<CheckoutResult> {
  const s = useCartStore.getState()
  const quote = s.quote
  const branchId = useBranchStore.getState().activeBranchId
  if (!quote || !s.storeId || !branchId || !user) throw new Error('The till is not ready to sell offline.')

  const payments = s.payments.filter((p) => dCmp(p.amount || '0', '0') > 0)
  if (payments.some((p) => !OFFLINE_METHODS.has(p.method))) {
    throw new OfflineRefusal('OFFLINE_PAYMENT_METHOD', 'Offline sales can be paid by cash, M-PESA or card only.')
  }
  if (dCmp(dSum(payments.map((p) => p.amount)), quote.totals.grand_total) !== 0) {
    throw new OfflineRefusal('PAYMENT_MISMATCH', 'An offline sale must be paid in full; there is no credit without the server.')
  }

  const signature = cartSignature(s)
  const id = s.unconfirmed && s.unconfirmed.signature === signature ? s.unconfirmed.key : newIdempotencyKey()
  const soldAt = new Date().toISOString()
  const lineByRef = new Map(s.lines.map((l) => [l.lineRef, l]))

  await enqueueSale({
    id,
    userId: user.id,
    userName: user.name,
    branchId,
    storeId: s.storeId,
    terminalId: s.terminalId,
    soldAt,
    total: quote.totals.grand_total,
    lines: quote.lines.map((q) => ({
      product_id: q.product_id,
      product_name: lineByRef.get(q.line_ref)?.productName ?? q.product_name,
      uom_id: q.uom_id,
      uom_code: q.uom_code,
      qty: q.quantity,
      qty_base: q.qty_base,
      unit_price: q.unit_price,
      tax_rate: q.tax_rate,
    })),
    payments: payments.map((p) => ({ method: p.method, amount: p.amount, reference: p.reference || null })),
  })

  return {
    kind: 'queued',
    sale: {
      id,
      doc_number: `OFFLINE-${s.terminalId}-${id.slice(0, 8).toUpperCase()}`,
      sale_mode: 'RETAIL',
      sub_type: null,
      status: 'DRAFT',
      customer_id: null,
      store_id: s.storeId,
      quote_id: null,
      terminal_id: s.terminalId,
      subtotal: quote.totals.subtotal,
      discount_total: '0.0000',
      tax_total: quote.totals.tax,
      grand_total: quote.totals.grand_total,
      void_reason: null,
      voided_at: null,
      posted_at: soldAt,
      lines: quote.lines.map((q, i) => ({
        id: `${id}-${i}`,
        line_number: i + 1,
        product_id: q.product_id,
        uom_id: q.uom_id,
        qty: q.quantity,
        qty_base: q.qty_base,
        list_price: q.unit_price,
        unit_price: q.unit_price,
        discount_amount: '0.0000',
        discount_pct: '0.0000',
        discount_source: null,
        tax_rate: q.tax_rate,
        tax_amount: q.tax_amount,
        line_total: q.line_total,
        is_bonus: false,
        product: { id: q.product_id, name: q.product_name },
      })),
    },
  }
}

/** A sale the till will not queue, shown in the payment panel like a server refusal. */
class OfflineRefusal extends Error {
  readonly code: string

  constructor(code: string, message: string) {
    super(message)
    this.code = code
  }
}
