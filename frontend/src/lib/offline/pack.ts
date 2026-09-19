import { useLiveQuery } from 'dexie-react-hooks'
import { useEffect } from 'react'
import { apiGet } from '../api'
import { useBranchStore } from '../branch'
import { dAdd, dCmp, dSub } from '../decimal'
import { isOfflineNow, useIsOffline } from './connectivity'
import { offlineDb, type PackProduct, type PricePack } from './db'

/** Refreshed this often while online, so a sudden outage finds recent prices. */
const REFRESH_MS = 20 * 60_000

type PackResponse = Omit<PricePack, 'storeId' | 'branchId'>

/** GET /api/pos/offline-pack — replaces this store's pack on the device. */
export async function refreshPack(storeId: string): Promise<PricePack | null> {
  const branchId = useBranchStore.getState().activeBranchId
  if (!branchId || isOfflineNow()) return null
  try {
    const pack = await apiGet<PackResponse>('/api/pos/offline-pack', { store_id: storeId })
    // Stored in UTC so it compares, as text, with the outbox's sale times.
    const stored: PricePack = { ...pack, generated_at: new Date(pack.generated_at).toISOString(), storeId, branchId }
    await offlineDb.packs.put(stored)
    return stored
  } catch {
    return null
  }
}

/**
 * The pack for the till's store, kept fresh while online. Returns undefined
 * while IndexedDB is being read and null when there is no pack at all.
 */
export function usePricePack(storeId: string | null): PricePack | null | undefined {
  const offline = useIsOffline()

  useEffect(() => {
    if (!storeId || offline) return
    void refreshPack(storeId)
    const timer = window.setInterval(() => void refreshPack(storeId), REFRESH_MS)
    return () => window.clearInterval(timer)
  }, [storeId, offline])

  return useLiveQuery(async () => (storeId ? ((await offlineDb.packs.get(storeId)) ?? null) : null), [storeId])
}

export function isPackStale(pack: PricePack, now = Date.now()): boolean {
  return new Date(pack.valid_until).getTime() <= now
}

/**
 * Free-to-sell per product as this device believes it now: the pack's
 * snapshot less whatever this till has sold offline since it was taken.
 */
export function useLocalStock(pack: PricePack | null | undefined): Map<string, string> | undefined {
  return useLiveQuery(async () => {
    const stock = new Map<string, string>()
    if (!pack) return stock
    for (const p of pack.products) stock.set(p.id, p.free_to_sell)
    const since = pack.generated_at
    const sold = await offlineDb.outbox.where('soldAt').aboveOrEqual(since).toArray()
    for (const sale of sold) {
      if (sale.storeId !== pack.storeId) continue
      for (const line of sale.lines) {
        const left = stock.get(line.product_id)
        if (left !== undefined) stock.set(line.product_id, dSub(left, line.qty_base))
      }
    }
    return stock
  }, [pack?.storeId, pack?.generated_at])
}

/** Name, code, generic name, SKU, GTIN or a unit's barcode, as the online search does. */
export function searchPack(pack: PricePack, query: string, limit = 20): PackProduct[] {
  const term = query.trim().toLowerCase()
  if (!term) return []
  const exact = pack.products.find((p) => p.uoms.some((u) => u.barcode === query.trim()) || p.gtin === query.trim())
  const matches = pack.products.filter((p) =>
    [p.name, p.code, p.generic_name, p.sku].some((v) => v && v.toLowerCase().includes(term)),
  )
  return (exact ? [exact, ...matches.filter((p) => p.id !== exact.id)] : matches).slice(0, limit)
}

export function findByBarcode(pack: PricePack, code: string): PackProduct | null {
  const term = code.trim()
  return pack.products.find((p) => p.gtin === term || p.code === term || p.uoms.some((u) => u.barcode === term)) ?? null
}

export function hasStockFor(stock: Map<string, string> | undefined, productId: string, qtyBase: string, alreadyInCart = '0'): boolean {
  const left = stock?.get(productId)
  if (left === undefined) return false
  return dCmp(dAdd(qtyBase, alreadyInCart), left) <= 0
}
