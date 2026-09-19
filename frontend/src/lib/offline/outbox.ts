import axios from 'axios'
import { useLiveQuery } from 'dexie-react-hooks'
import { api, getApiError } from '../api'
import { isOfflineNow, isUnreachableError, useConnectivityStore } from './connectivity'
import { offlineDb, type OutboxSale, type SyncedSale } from './db'
import { refreshPack } from './pack'

/**
 * Part 16.7 — the till's outbox. A sale made offline is written here in one
 * IndexedDB transaction and handed to POST /api/pos/offline-sales, oldest
 * first, as soon as the server answers again. Once the server holds a sale
 * (posted, or kept as a conflict for a supervisor) it leaves the outbox.
 *
 * A sale is only replayed under the person who made it: the server records
 * the signed-in user as the seller, so another cashier's session must not
 * carry it. Those wait until their seller signs in again, or until someone
 * deliberately takes them over from the Sync Centre.
 */

type ServerOfflineSale = {
  id: string
  status: 'POSTED' | 'CONFLICT' | 'DISMISSED'
  error_code: string | null
  sale: { id: string; doc_number: string } | null
}

export async function enqueueSale(sale: Omit<OutboxSale, 'status' | 'attempts' | 'lastError'>): Promise<void> {
  await offlineDb.outbox.put({ ...sale, status: 'PENDING', attempts: 0, lastError: null })
}

let running: Promise<number> | null = null

/**
 * Replays this user's pending sales. Single-flight: a second call while one
 * is running waits for it. Returns how many the server took.
 */
export function replayOutbox(userId: number | null | undefined): Promise<number> {
  if (!userId) return Promise.resolve(0)
  running ??= drain(userId).finally(() => {
    running = null
  })
  return running
}

async function drain(userId: number): Promise<number> {
  const pending = (await offlineDb.outbox.where('userId').equals(userId).toArray())
    .filter((s) => s.status === 'PENDING')
    .sort((a, b) => a.soldAt.localeCompare(b.soldAt))

  let taken = 0
  const stores = new Set<string>()
  for (const sale of pending) {
    if (isOfflineNow()) break
    const outcome = await send(sale)
    if (outcome === 'stop') break
    if (outcome === 'taken') {
      taken++
      stores.add(sale.storeId)
    }
  }
  // Those sales no longer count against the old snapshot, so take a new one
  // that includes them; otherwise the till would think the stock came back.
  await Promise.all([...stores].map((storeId) => refreshPack(storeId)))
  return taken
}

async function send(sale: OutboxSale): Promise<'taken' | 'rejected' | 'stop'> {
  try {
    const { data } = await api.post<ServerOfflineSale>(
      '/api/pos/offline-sales',
      {
        id: sale.id,
        store_id: sale.storeId,
        terminal_id: sale.terminalId,
        sold_at: sale.soldAt,
        sold_by: sale.userName,
        total: sale.total,
        lines: sale.lines.map((l) => ({ product_id: l.product_id, uom_id: l.uom_id, qty: l.qty, unit_price: l.unit_price, tax_rate: l.tax_rate })),
        payments: sale.payments,
      },
      { headers: { 'X-Branch-Id': sale.branchId } },
    )

    const record: SyncedSale = {
      id: sale.id,
      soldAt: sale.soldAt,
      syncedAt: new Date().toISOString(),
      total: sale.total,
      status: data.status === 'POSTED' ? 'POSTED' : 'CONFLICT',
      docNumber: data.sale?.doc_number ?? null,
      errorCode: data.error_code,
    }
    await offlineDb.transaction('rw', offlineDb.outbox, offlineDb.synced, async () => {
      await offlineDb.synced.put(record)
      await offlineDb.outbox.delete(sale.id)
    })
    return 'taken'
  } catch (error) {
    if (isUnreachableError(error)) {
      useConnectivityStore.getState().markUnreachable()
      return 'stop'
    }
    const status = axios.isAxiosError(error) ? error.response?.status : undefined
    // Signed out, session expired, or slowed down: nothing else will get
    // through either, so try again later.
    if (status === 401 || status === 419 || status === 429) return 'stop'
    // Refused outright (malformed, not allowed, store not in this branch):
    // keep it on the device, visible in the Sync Centre, and carry on with
    // the rest rather than retrying this one forever.
    if (status !== undefined && status >= 400 && status < 500) {
      await offlineDb.outbox.update(sale.id, { status: 'REJECTED', attempts: sale.attempts + 1, lastError: getApiError(error).message })
      return 'rejected'
    }
    await offlineDb.outbox.update(sale.id, { attempts: sale.attempts + 1, lastError: getApiError(error).message })
    return 'stop'
  }
}

/** Hands another cashier's waiting sales to the signed-in user, who then replays them. */
export async function takeOver(ids: string[], userId: number, userName: string): Promise<void> {
  await offlineDb.outbox.where('id').anyOf(ids).modify((s) => {
    s.userId = userId
    s.userName = `${userName} (for ${s.userName})`
    s.status = 'PENDING'
    s.lastError = null
  })
}

export async function discardRejected(id: string): Promise<void> {
  const sale = await offlineDb.outbox.get(id)
  if (sale?.status === 'REJECTED') await offlineDb.outbox.delete(id)
}

export function useOutbox(): OutboxSale[] | undefined {
  return useLiveQuery(() => offlineDb.outbox.orderBy('soldAt').toArray(), [])
}

export function usePendingCount(): number {
  return useLiveQuery(() => offlineDb.outbox.where('status').equals('PENDING').count(), []) ?? 0
}

export function useRecentlySynced(limit = 20): SyncedSale[] | undefined {
  return useLiveQuery(() => offlineDb.synced.orderBy('syncedAt').reverse().limit(limit).toArray(), [limit])
}

/** Keeps a week of the till's own record; the server holds the real one. */
export async function pruneSynced(days = 7): Promise<void> {
  const cutoff = new Date(Date.now() - days * 86_400_000).toISOString()
  await offlineDb.synced.where('syncedAt').below(cutoff).delete()
}
