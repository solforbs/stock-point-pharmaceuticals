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
    <div className="h-11 px-3 sm:px-4 flex items-center justify-between gap-2 bg-white border-b border-slate-200/80 shrink-0 select-none overflow-hidden">
      {/* Left: Mode Toggle Pills */}
      <div className="flex items-center gap-2 shrink-0">
        <span className="text-[12px] font-bold text-slate-500 uppercase tracking-wider hidden xl:inline">
          Sale Mode:
        </span>
        <div className="flex items-center gap-0.5 p-0.5 bg-slate-100/90 rounded-xl border border-slate-200/80">
          {modes.map((mode) => {
            const isActive = mode === saleMode
            return (
              <button
                key={mode}
                type="button"
                onClick={() => requestSwitch(mode)}
                disabled={!switchable && !isActive}
                className={`px-3 py-1 rounded-lg text-[12px] font-bold transition-all cursor-pointer ${
                  isActive
                    ? 'bg-blue-600 text-white shadow-xs'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-white/70'
                }`}
              >
                {mode === 'RETAIL' ? '🛒 Retail' : mode === 'WHOLESALE' ? '🏢 Wholesale' : '💊 Dispensing'}
              </button>
            )
          })}
        </div>
      </div>

      {/* Right: Store, Terminal, Cashier & Live Status */}
      <div className="flex items-center gap-2 shrink-0 text-xs">
        {/* Store Selector */}
        <div className="flex items-center gap-1.5 text-slate-600">
          <StoreIcon size={14} className="text-blue-600 shrink-0" />
          <span className="text-[11.5px] font-bold text-slate-500 hidden 2xl:inline">Store:</span>
          <select
            value={storeId ?? ''}
            onChange={(e) => setStore(e.target.value)}
            aria-label="Inventory store"
            className="h-7 rounded-lg bg-slate-50 border border-slate-200 text-slate-800 text-[12px] font-bold px-2 outline-none hover:bg-slate-100 transition-colors cursor-pointer max-w-[130px] sm:max-w-none"
          >
            {/* Only stores the till may sell from: the server refuses the rest. */}
            {stores.filter((store) => store.is_sellable).map((store) => (
              <option key={store.id} value={store.id}>
                {store.code}
              </option>
            ))}
          </select>
        </div>

        <div className="h-3.5 w-px bg-slate-200" />

        {/* Terminal Indicator */}
        <div className="flex items-center gap-1 px-1.5 py-0.5 rounded-lg bg-slate-50 border border-slate-200/80 text-[11.5px] text-slate-600 font-semibold">
          <Monitor size={11} className="text-slate-400" />
          <input
            value={terminalId}
            onChange={(e) => setTerminal(e.target.value.slice(0, 20))}
            className="w-8 bg-transparent text-slate-800 font-bold outline-none text-center"
            aria-label="Terminal id"
          />
        </div>

        {/* Cashier Name - only on wider viewports to prevent overflow */}
        <span className="hidden xl:inline text-[11.5px] text-slate-500">
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
