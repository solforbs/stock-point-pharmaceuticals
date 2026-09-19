import { CloudOff, RefreshCw } from 'lucide-react'
import { Link } from 'react-router-dom'
import type { PricePack } from '../../lib/offline/db'
import { usePendingCount } from '../../lib/offline/outbox'
import { isPackStale } from '../../lib/offline/pack'

function time(iso: string): string {
  return new Date(iso).toLocaleString(undefined, { weekday: 'short', hour: '2-digit', minute: '2-digit' })
}

/**
 * Part 16.7 — tells the cashier the till is selling offline, from which
 * price list, under which rules, and how many sales are waiting to sync.
 */
export function PosOfflineStrip({ offline, pack }: { offline: boolean; pack: PricePack | null | undefined }) {
  const pending = usePendingCount()

  if (!offline && pending === 0) return null

  if (!offline) {
    return (
      <div className="px-4 py-1.5 flex items-center gap-2 bg-blue-50 border-b border-blue-200 text-[12px] text-blue-900 shrink-0">
        <RefreshCw size={13} className="shrink-0" />
        <span>
          <strong>{pending}</strong> offline {pending === 1 ? 'sale is' : 'sales are'} waiting to sync. They are sent automatically while their cashier is signed in.
        </span>
        <Link to="/admin/sync-centre" className="ml-auto underline font-semibold">Sync Centre</Link>
      </div>
    )
  }

  const stale = pack ? isPackStale(pack) : false
  const tone = !pack || stale ? 'bg-rose-50 border-rose-200 text-rose-900' : 'bg-amber-50 border-amber-200 text-amber-900'

  return (
    <div role="status" className={`px-4 py-1.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 border-b text-[12px] shrink-0 ${tone}`}>
      <span className="flex items-center gap-1.5 font-extrabold uppercase tracking-wide">
        <CloudOff size={13} /> Offline
      </span>
      {!pack ? (
        <span>This till has no offline price list, so it cannot sell until the server answers.</span>
      ) : stale ? (
        <span>The offline price list from {time(pack.generated_at)} has expired. Sales must wait for the connection.</span>
      ) : (
        <span>
          Selling walk-in retail, paid in full, from the price list of <strong>{time(pack.generated_at)}</strong> ({pack.products.length} products).
        </span>
      )}
      {pending > 0 && (
        <span className="ml-auto font-bold">
          {pending} {pending === 1 ? 'sale' : 'sales'} waiting to sync
        </span>
      )}
    </div>
  )
}
