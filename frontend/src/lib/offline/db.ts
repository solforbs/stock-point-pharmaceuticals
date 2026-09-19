import Dexie, { type EntityTable } from 'dexie'
import type { Decimal, PaymentMethod, ProductUom, Uom } from '../types'

/**
 * Part 16.7 — what a till keeps on the device so it can sell through an
 * outage. IndexedDB, not localStorage: it survives a reload, holds a whole
 * catalogue, and is written transactionally so a sale is never half-queued.
 */

/** A product as the price pack carries it: enough to find it, add it and price it. */
export type PackProduct = {
  id: string
  code: string
  sku: string | null
  gtin: string | null
  name: string
  generic_name: string | null
  strength: string | null
  is_discrete: boolean
  pack_integrity: boolean
  base_uom_id: string
  base_uom: Uom | null
  uoms: ProductUom[]
  /** Walk-in retail price of one of each sales unit, keyed by uom_id. */
  prices: Record<string, { unit_price: Decimal; tax_rate: Decimal; tax_code: string | null }>
  /** Free-to-sell in the base unit when the pack was built. */
  free_to_sell: Decimal
}

export type PricePack = {
  storeId: string
  branchId: string
  store: { id: string; code: string; name: string }
  generated_at: string
  valid_until: string
  products: PackProduct[]
}

export type OutboxLine = {
  product_id: string
  product_name: string
  uom_id: string
  uom_code: string
  qty: Decimal
  qty_base: Decimal
  unit_price: Decimal
  tax_rate: Decimal
}

/** A sale made while the server was unreachable, waiting to be handed over. */
export type OutboxSale = {
  id: string
  userId: number
  userName: string
  branchId: string
  storeId: string
  terminalId: string
  soldAt: string
  total: Decimal
  lines: OutboxLine[]
  payments: { method: PaymentMethod; amount: Decimal; reference?: string | null }[]
  /** PENDING until the server holds it; REJECTED if it refused the payload outright. */
  status: 'PENDING' | 'REJECTED'
  attempts: number
  lastError: string | null
}

/** What happened to a sale after the server took it, for the till's own record. */
export type SyncedSale = {
  id: string
  soldAt: string
  syncedAt: string
  total: Decimal
  status: 'POSTED' | 'CONFLICT'
  docNumber: string | null
  errorCode: string | null
}

export const offlineDb = new Dexie('stockpoint-pos') as Dexie & {
  packs: EntityTable<PricePack, 'storeId'>
  outbox: EntityTable<OutboxSale, 'id'>
  synced: EntityTable<SyncedSale, 'id'>
}

offlineDb.version(1).stores({
  packs: 'storeId',
  outbox: 'id, userId, status, soldAt',
  synced: 'id, syncedAt',
})
