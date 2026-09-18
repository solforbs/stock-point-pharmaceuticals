import { useMutation, useQueryClient } from '@tanstack/react-query'
import { api, getApiError, withIdempotency } from '../../lib/api'
import type { ApiError } from '../../lib/apiError'
import { toast, toastApiError } from '../../lib/toast'
import type { Sale } from '../../lib/types'
import { useCartStore, type PriceChange } from './cartStore'

type CheckoutResult = { kind: 'posted'; sale: Sale } | { kind: 'approval'; lines: string[] }

const INLINE_CODES = new Set(['INSUFFICIENT_STOCK', 'PAYMENT_MISMATCH', 'CREDIT_LIMIT_EXCEEDED', 'CREDIT_HOLD', 'BATCH_EXPIRED', 'BATCH_RECALLED', 'BATCH_QUARANTINED'])

/**
 * POST /api/sales/checkout — Part 10.3. Bound to the quote_id, carries the
 * attempt's Idempotency-Key (reused on retry, e.g. approve after a 202),
 * and acts on the blueprint's error codes rather than parsing prose.
 */
export function useCheckout() {
  const queryClient = useQueryClient()

  return useMutation({
    meta: { silent: true },
    mutationFn: async ({ approve }: { approve: boolean }): Promise<CheckoutResult> => {
      const s = useCartStore.getState()
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
