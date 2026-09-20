import { Drawer } from '../../components/ui/Drawer'
import { EmptyState } from '../../components/ui/States'
import { Button } from '../../components/ui/primitives'
import { formatDateTime } from '../../lib/format'
import type { HeldCart } from './cartStore'

export interface PosHeldCartsDrawerProps {
  open: boolean
  onClose: () => void
  heldCarts: HeldCart[]
  onResume: (id: string) => void
  onDiscard: (id: string) => void
}

export function PosHeldCartsDrawer({
  open,
  onClose,
  heldCarts,
  onResume,
  onDiscard,
}: PosHeldCartsDrawerProps) {
  return (
    <Drawer
      open={open}
      onClose={onClose}
      title="Held Carts"
      subtitle="Resuming replaces the current active counter cart."
    >
      {heldCarts.length === 0 ? (
        <EmptyState title="No carts held on this terminal" hint="Press F8 to park an active customer cart." />
      ) : (
        <div className="space-y-2.5">
          {heldCarts.map((h) => (
            <div key={h.id} className="ui-card p-3.5 flex items-center gap-3">
              <div className="flex-1 min-w-0">
                <div className="text-sm font-bold text-slate-800 truncate">
                  {h.name || 'Unnamed Held Cart'}
                </div>
                <div className="text-xs text-slate-500 mt-0.5">
                  <span className="font-semibold text-blue-600">
                    {h.saleMode}
                  </span>{' '}
                  · {h.lines.length} item{h.lines.length === 1 ? '' : 's'} ·{' '}
                  {h.customer?.name ?? 'Walk-in Cash'} · {formatDateTime(h.heldAt)}
                </div>
              </div>
              <Button size="sm" variant="ghost" onClick={() => onDiscard(h.id)}>
                Discard
              </Button>
              <Button size="sm" variant="primary" onClick={() => onResume(h.id)}>
                Resume
              </Button>
            </div>
          ))}
        </div>
      )}
    </Drawer>
  )
}
