import { useMutation } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useDebounced } from '../../components/ProductSearch'
import { apiPost, getApiError } from '../../lib/api'
import { isUnreachableError } from '../../lib/offline/connectivity'
import type { PricePack } from '../../lib/offline/db'
import type { Quote } from '../../lib/types'
import { cartSignature, quotePayload, useCartStore } from './cartStore'
import { buildOfflineQuote, isOfflineQuote } from './offlineQuote'

export type OfflineContext = {
  offline: boolean
  pack: PricePack | null | undefined
  stock: Map<string, string> | undefined
}

function useNow(intervalMs: number): number {
  const [now, setNow] = useState(() => Date.now())
  useEffect(() => {
    const t = window.setInterval(() => setNow(Date.now()), intervalMs)
    return () => window.clearInterval(t)
  }, [intervalMs])
  return now
}

/**
 * Part 16.3 — POST /api/pricing/quote on every cart change (debounced). The
 * quote carries the signature of the cart it priced; it is fresh only while
 * that signature still matches and expires_at is in the future.
 *
 * Part 16.7 — while the server is unreachable the cart is priced on the
 * device from the offline price pack instead, and on reconnect that local
 * quote is thrown away so the server prices the cart again.
 */
export function useQuote({ offline, pack, stock }: OfflineContext) {
  const signature = useCartStore(cartSignature)
  const debounced = useDebounced(signature, 350)
  const quote = useCartStore((s) => s.quote)
  const quoteSignature = useCartStore((s) => s.quoteSignature)
  const errorSignature = useCartStore((s) => s.quoteErrorSignature)
  const quoteExpired = useCartStore((s) => s.quoteExpired)
  const lineCount = useCartStore((s) => s.lines.length)
  const storeId = useCartStore((s) => s.storeId)
  const needsCustomer = useCartStore((s) => s.saleMode === 'WHOLESALE' && !s.customer)
  const status = useCartStore((s) => s.status)
  const now = useNow(1000)

  const mutation = useMutation({
    meta: { silent: true },
    mutationFn: async (sig: string) => {
      const quote = await apiPost<Quote>('/api/pricing/quote', quotePayload(useCartStore.getState()))
      return { quote, sig }
    },
    onSuccess: ({ quote, sig }) => useCartStore.getState().setQuote(quote, sig),
    onError: (err, sig) => {
      // No answer at all: the till has just gone offline and prices locally.
      if (isUnreachableError(err)) return
      useCartStore.getState().setQuoteError(getApiError(err), sig)
    },
  })

  const { isPending, mutate } = mutation
  const blocked = status === 'POSTED' || status === 'POSTING' || lineCount === 0 || !storeId || needsCustomer

  // Crossing between online and offline: neither kind of quote (nor a
  // refusal made under the other rules) carries over.
  useEffect(() => {
    const s = useCartStore.getState()
    if (s.status === 'POSTED' || s.status === 'POSTING') return
    if (offline ? s.quote && !isOfflineQuote(s.quote) : isOfflineQuote(s.quote) || s.quoteError?.code.startsWith('OFFLINE_')) {
      s.clearQuote()
      s.clearQuoteError()
    }
  }, [offline])

  useEffect(() => {
    if (!offline) return
    const s = useCartStore.getState()
    if (status === 'POSTED' || status === 'POSTING' || !storeId) return
    if (lineCount === 0) {
      if (s.quote || s.quoteError) {
        s.clearQuote()
        s.clearQuoteError()
      }
      return
    }
    const signatureNow = cartSignature(s)
    const result = buildOfflineQuote(s, pack, stock)
    if ('error' in result) {
      s.setQuoteError(result.error, signatureNow)
      return
    }
    // Unchanged: keep the quote (and the checkout key that goes with it).
    if (isOfflineQuote(s.quote) && s.quoteSignature === signatureNow && s.quote?.totals.grand_total === result.quote.totals.grand_total) return
    s.setQuote(result.quote, signatureNow)
  }, [offline, debounced, pack, stock, status, storeId, lineCount])

  useEffect(() => {
    if (offline) return
    if (blocked) {
      if (lineCount === 0 && quoteSignature) useCartStore.getState().clearQuote()
      return
    }
    if (isPending) return
    if (debounced === quoteSignature || debounced === errorSignature) return
    mutate(debounced)
  }, [offline, blocked, debounced, quoteSignature, errorSignature, isPending, mutate, lineCount])

  const expiresAt = quote?.expires_at ? new Date(quote.expires_at).getTime() : null
  const expired = quoteExpired || (expiresAt !== null && expiresAt <= now)
  const isFresh = !!quote?.quote_id && quoteSignature === signature && !expired && !isPending
  const secondsLeft = expiresAt !== null ? Math.max(0, Math.floor((expiresAt - now) / 1000)) : null

  function requote() {
    if (offline) return
    const s = useCartStore.getState()
    s.clearQuoteError()
    if (s.quoteExpired || !s.quote) s.clearQuote()
    else s.setStatus(s.status === 'POSTED' ? s.status : 'BUILDING')
    mutate(cartSignature(useCartStore.getState()))
  }

  return { isQuoting: isPending, isFresh, expired, secondsLeft, needsCustomer: needsCustomer && !offline, requote, offline }
}
