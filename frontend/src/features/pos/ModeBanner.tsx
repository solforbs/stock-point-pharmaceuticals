import { Monitor } from 'lucide-react'
import { useState } from 'react'
import { SyncStatusChip } from '../../components/SyncStatusChip'
import { ConfirmDialog } from '../../components/ui/Modal'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { formatMoney } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import type { SaleMode, Store } from '../../lib/types'
import { useCartStore } from './cartStore'

/**
 * V6 Part 10.2 — the persistent mode banner: active mode,
 * customer and tier, terminal and cashier.
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
  const switchable = modes.length > 1 && canSwitch && status !== 'POSTED' && status !== 'POSTING'

  function requestSwitch(mode: SaleMode) {
    if (mode === saleMode) return
    if (lineCount > 0) setPendingMode(mode)
    else setSaleMode(mode)
  }

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-2.5 bg-white border-b border-slate-200/80 shrink-0 shadow-2xs">
      <div className="flex items-center gap-3">
        {/* Sale Mode Toggle Pills */}
        <div className="flex items-center gap-1 p-1 bg-slate-100 rounded-xl border border-slate-200/60">
          {modes.map((mode) => (
            <button
              key={mode}
              type="button"
              onClick={() => requestSwitch(mode)}
              disabled={!switchable && mode !== saleMode}
              className={`px-3 py-1 rounded-lg text-[12px] font-bold transition-all cursor-pointer ${
                mode === saleMode
                  ? 'bg-blue-600 text-white shadow-xs shadow-blue-500/25'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-white/60'
              }`}
            >
              {mode === 'RETAIL' ? '🛒 Retail Walk-in' : mode === 'WHOLESALE' ? '🏢 Wholesale B2B' : '💊 Dispensing'}
            </button>
          ))}
        </div>

        {/* Customer / Credit Context */}
        <div className="hidden lg:flex items-center gap-2 text-[12px]">
          {saleMode === 'WHOLESALE' ? (
            customer ? (
              <div className="flex items-center gap-1.5 px-3 py-1 rounded-xl bg-blue-50 text-blue-700 border border-blue-200/70 font-semibold">
                <span className="font-bold">{customer.name}</span>
                <span className="text-blue-500">· Tier {customer.tier?.code ?? '—'}</span>
                <span className="text-blue-500 tabular">· Avail {formatMoney(customer.available_credit ?? '0')}</span>
                {customer.credit?.on_hold && (
                  <span className="ml-1 px-1.5 py-0.5 rounded bg-rose-500 text-white font-bold text-[10px]">
                    CREDIT HOLD
                  </span>
                )}
              </div>
            ) : (
              <div className="px-2.5 py-1 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 text-[11.5px] font-semibold">
                ⚠️ Account required (F5)
              </div>
            )
          ) : customer ? (
            <div className="px-2.5 py-1 rounded-xl bg-slate-100 text-slate-700 font-semibold text-[11.5px]">
              👤 {customer.name}
            </div>
          ) : (
            <div className="px-2.5 py-1 rounded-xl bg-slate-100 text-slate-500 font-medium text-[11.5px]">
              👤 Walk-in Cash Customer
            </div>
          )}
        </div>
      </div>

      <div className="flex items-center gap-3">
        {/* Store Selector */}
        <label className="flex items-center gap-1.5 text-[11.5px] text-slate-500 font-medium">
          <span>Store:</span>
          <select
            value={storeId ?? ''}
            onChange={(e) => setStore(e.target.value)}
            className="h-8 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 text-[12px] font-semibold px-2.5 outline-none hover:bg-slate-100 transition-colors cursor-pointer"
          >
            {stores.map((store) => (
              <option key={store.id} value={store.id}>
                {store.code} {store.is_sellable ? '' : '(not sellable)'}
              </option>
            ))}
          </select>
        </label>

        {/* Terminal Indicator */}
        <div className="flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-50 border border-slate-200/80 text-[11.5px] text-slate-600 font-semibold">
          <Monitor size={12} className="text-slate-400" />
          <input
            value={terminalId}
            onChange={(e) => setTerminal(e.target.value.slice(0, 20))}
            className="w-10 bg-transparent text-slate-800 font-bold outline-none text-center"
            aria-label="Terminal id"
          />
        </div>

        {/* Cashier Name */}
        <span className="hidden xl:inline text-[12px] font-medium text-slate-500">
          Cashier: <strong className="text-slate-800 font-bold">{user?.name ?? '—'}</strong>
        </span>

        {/* Live Sync Status */}
        <SyncStatusChip />
      </div>

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
    </div>
  )
}
