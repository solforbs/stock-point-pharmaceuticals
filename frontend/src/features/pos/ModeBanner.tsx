import { ArrowLeftRight, Monitor } from 'lucide-react'
import { useState } from 'react'
import { SyncStatusChip } from '../../components/SyncStatusChip'
import { ConfirmDialog } from '../../components/ui/Modal'
import { Button } from '../../components/ui/primitives'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { formatMoney } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import type { SaleMode, Store } from '../../lib/types'
import { useCartStore } from './cartStore'

const MODE_COLOR: Record<SaleMode, string> = {
  RETAIL: 'var(--mode-retail)',
  WHOLESALE: 'var(--mode-wholesale)',
  DISPENSING: 'var(--mode-dispensing)',
}

/**
 * V6 Part 10.2 — the persistent, colour-coded mode banner: active mode,
 * customer and tier, terminal and cashier. The highest-visibility element
 * on the screen because mode confusion is the costliest user error.
 * Part 24.2 — switching modes re-quotes the cart and needs sale.mode.switch.
 */
export function ModeBanner({ stores }: { stores: Store[] }) {
  const { data: user } = useCurrentUser()
  const canSwitch = usePermission('sale.mode.switch')
  const saleMode = useCartStore((s) => s.saleMode)
  const customer = useCartStore((s) => s.customer)
  const storeId = useCartStore((s) => s.storeId)
  const terminalId = useCartStore((s) => s.terminalId)
  const lineCount = useCartStore((s) => s.lines.length)
  const status = useCartStore((s) => s.status)
  const setSaleMode = useCartStore((s) => s.setSaleMode)
  const setStore = useCartStore((s) => s.setStore)
  const setTerminal = useCartStore((s) => s.setTerminal)
  const [pendingMode, setPendingMode] = useState<SaleMode | null>(null)

  const modes = user?.sale_modes ?? []
  const defaultMode = user?.default_sale_mode ?? null
  const switchable = modes.length > 1 && canSwitch && status !== 'POSTED' && status !== 'POSTING'
  const color = MODE_COLOR[saleMode]

  function requestSwitch(mode: SaleMode) {
    if (mode === saleMode) return
    if (lineCount > 0) setPendingMode(mode)
    else setSaleMode(mode)
  }

  return (
    <div className="flex items-center gap-4 px-4 py-2 text-white shrink-0" style={{ background: color }}>
      <div className="flex items-center gap-2">
        <span className="px-2.5 py-1 rounded font-black text-[15px] tracking-wider bg-white/15 border-2 border-white/60">▌{saleMode}▐</span>
        {defaultMode && defaultMode !== saleMode && <span className="text-[10px] uppercase opacity-80">switched from {defaultMode}</span>}
      </div>

      <div className="flex-1 min-w-0 text-[12.5px] truncate">
        {saleMode === 'WHOLESALE' ? (
          customer ? (
            <>
              <span className="font-bold">{customer.name}</span>
              <span className="opacity-90"> · Tier {customer.tier?.code ?? '—'}</span>
              <span className="opacity-90 tabular"> · Credit limit {formatMoney(customer.credit?.credit_limit ?? '0')}</span>
              <span className="opacity-90 tabular"> · Available {formatMoney(customer.available_credit ?? '0')}</span>
              {customer.credit?.on_hold && <span className="ml-2 px-1.5 py-0.5 rounded bg-white text-[var(--status-red)] font-bold text-[10.5px]">CREDIT HOLD</span>}
            </>
          ) : (
            <span className="opacity-90">No customer — press F5. A wholesale sale needs one.</span>
          )
        ) : customer ? (
          <span className="font-bold">{customer.name}</span>
        ) : (
          <span className="opacity-90">Walk-in customer</span>
        )}
      </div>

      {switchable && (
        <div className="flex items-center gap-1 rounded bg-white/10 p-0.5">
          {modes.map((mode) => (
            <button
              key={mode}
              type="button"
              onClick={() => requestSwitch(mode)}
              className={`px-2 h-6 rounded text-[10.5px] font-bold ${mode === saleMode ? 'bg-white text-[var(--text)]' : 'text-white/80 hover:text-white'}`}
            >
              {mode}
            </button>
          ))}
          <ArrowLeftRight size={12} className="mx-1 opacity-70" />
        </div>
      )}

      <label className="flex items-center gap-1.5 text-[11px]">
        <span className="opacity-80">Store</span>
        <select
          value={storeId ?? ''}
          onChange={(e) => setStore(e.target.value)}
          className="h-6 rounded bg-white/15 border border-white/30 text-white text-[11px] px-1 outline-none"
        >
          {stores.map((store) => (
            <option key={store.id} value={store.id} className="text-black">
              {store.code} {store.is_sellable ? '' : '(not sellable)'}
            </option>
          ))}
        </select>
      </label>

      <label className="flex items-center gap-1.5 text-[11px]">
        <Monitor size={12} className="opacity-80" />
        <input
          value={terminalId}
          onChange={(e) => setTerminal(e.target.value.slice(0, 20))}
          className="h-6 w-14 rounded bg-white/15 border border-white/30 text-white text-[11px] px-1 outline-none"
          aria-label="Terminal id"
        />
      </label>

      <span className="text-[11px] whitespace-nowrap">Cashier: {user?.name ?? '—'}</span>
      <SyncStatusChip className="!text-white !border-white/50" />

      <ConfirmDialog
        open={pendingMode !== null}
        title={`Switch to ${pendingMode}?`}
        message={`The ${lineCount}-line cart will be re-quoted under ${pendingMode} pricing. You will see the before and after totals once the new quote lands.`}
        confirmLabel="Switch and re-quote"
        onCancel={() => setPendingMode(null)}
        onConfirm={() => {
          if (pendingMode) setSaleMode(pendingMode)
          setPendingMode(null)
        }}
      />
      {!switchable && modes.length > 1 && !canSwitch && (
        <Button size="sm" variant="ghost" className="!text-white/70" disabled title="Switching modes needs the sale.mode.switch permission">
          Switch mode
        </Button>
      )}
    </div>
  )
}
