import { Monitor, Store as StoreIcon } from 'lucide-react'
import { useState } from 'react'
import { SyncStatusChip } from '../../components/SyncStatusChip'
import { ConfirmDialog } from '../../components/ui/Modal'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { usePermission } from '../../lib/permissions'
import type { SaleMode, Store } from '../../lib/types'
import { useCartStore } from './cartStore'

/**
 * Modern single-line POS Terminal & Mode Ribbon (Height: 44px fixed)
 */
export function ModeBanner({ stores }: { stores: Store[] }) {
  const { data: user } = useCurrentUser()
  const canSwitch = usePermission('sale.mode.switch')
  const saleMode = useCartStore((s) => s.saleMode)
  const storeId = useCartStore((s) => s.storeId)
  const terminalId = useCartStore((s) => s.terminalId)
  const lineCount = useCartStore((s) => s.lines.length)
  const status = useCartStore((s) => s.status)
  const setSaleMode = useCartStore((s) => s.setSaleMode)
  const setStore = useCartStore((s) => s.setStore)
  const setTerminal = useCartStore((s) => s.setTerminal)
  const [pendingMode, setPendingMode] = useState<SaleMode | null>(null)

  const modes = user?.sale_modes ?? []
  const switchable = modes.length > 1 && canSwitch && status !== 'POSTED' && status !== 'POSTING'

  function requestSwitch(mode: SaleMode) {
    if (mode === saleMode) return
    if (lineCount > 0) setPendingMode(mode)
    else setSaleMode(mode)
  }

  return (
    <div id="tour-pos-mode-banner" className="h-11 px-3 sm:px-4 flex items-center justify-between gap-2 bg-white border-b border-slate-200/80 shrink-0 select-none overflow-hidden">
      {/* Left: Mode Toggle Pills */}
      <div className="flex items-center gap-2 shrink-0">
        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider hidden xl:inline">
          Sale Mode:
        </span>
        <div className="flex items-center gap-1 p-1 bg-slate-100 rounded-full">
          {modes.map((mode) => {
            const isActive = mode === saleMode
            return (
              <button
                key={mode}
                type="button"
                onClick={() => requestSwitch(mode)}
                disabled={!switchable && !isActive}
                className={`px-3 py-1 rounded-full text-xs font-bold transition-all cursor-pointer ${
                  isActive
                    ? 'bg-blue-600 text-white shadow-xs'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-white/80'
                }`}
              >
                {mode === 'RETAIL' ? 'Retail' : mode === 'WHOLESALE' ? 'Wholesale' : 'Dispensing'}
              </button>
            )
          })}
        </div>
      </div>

      {/* Right: Store, Terminal, Cashier & Live Status */}
      <div className="flex items-center gap-2 shrink-0 text-xs">
        {/* Stock Room Location Selector */}
        <div className="flex items-center gap-1.5 text-slate-600">
          <StoreIcon size={14} className="text-blue-600 shrink-0" />
          <span className="font-bold text-slate-500 text-xs inline">Stock Room:</span>
          <select
            value={storeId ?? ''}
            onChange={(e) => setStore(e.target.value)}
            aria-label="Stock Room Location"
            title="Physical room or dispensary shelf where medicine stock is deducted from"
            className="h-7 rounded-xl bg-slate-100/90 hover:bg-slate-200/70 border border-slate-200/60 text-slate-800 text-xs font-bold px-2.5 outline-none transition-colors cursor-pointer shadow-2xs"
          >
            {/* Only stores the till may sell from: the server refuses the rest. */}
            {stores.filter((store) => store.is_sellable).map((store) => (
              <option key={store.id} value={store.id}>
                {store.code}{store.name && store.name.toLowerCase() !== store.code.toLowerCase() ? ` · ${store.name}` : ''}
              </option>
            ))}
          </select>
        </div>

        <div className="h-3.5 w-px bg-slate-200" />

        {/* Terminal Indicator */}
        <div className="flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-slate-100 border border-slate-200/50 text-xs text-slate-600 font-medium">
          <Monitor size={12} className="text-slate-400" />
          <input
            value={terminalId}
            onChange={(e) => setTerminal(e.target.value.slice(0, 20))}
            className="w-8 bg-transparent text-slate-800 font-bold outline-none text-center"
            aria-label="Terminal id"
          />
        </div>

        {/* Cashier Name - only on wider viewports to prevent overflow */}
        <span className="hidden xl:inline text-slate-500 text-xs font-normal">
          Cashier: <strong className="text-slate-800 font-bold">{user?.name ? user.name.split(' ')[0] : '—'}</strong>
        </span>

        <div className="h-3.5 w-px bg-slate-200 hidden xl:block" />

        {/* Live Sync Status */}
        <SyncStatusChip />
      </div>

      <ConfirmDialog
        open={pendingMode !== null}
        title={`Switch to ${pendingMode}?`}
        message={`The active ${lineCount}-line cart will be re-quoted under ${pendingMode} pricing rules.`}
        confirmLabel="Switch and Re-quote"
        onCancel={() => setPendingMode(null)}
        onConfirm={() => {
          if (pendingMode) setSaleMode(pendingMode)
          setPendingMode(null)
        }}
      />
    </div>
  )
}
