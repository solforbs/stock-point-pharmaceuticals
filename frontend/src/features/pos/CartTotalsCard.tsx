import { Clock } from 'lucide-react'
import { useState } from 'react'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { formatMoney, formatPct } from '../../lib/money'
import type { Quote } from '../../lib/types'

export interface CartTotalsCardProps {
  quote: Quote | null
  estimateTotal: string
  expired: boolean
  isQuoting: boolean
  secondsLeft: number | null
  needsCustomer: boolean
  linesCount: number
  canDiscount: boolean
  showCost: boolean
  disabled: boolean
  paymentEnabled: boolean
  headerDiscount: string
  headerDiscountReason: string
  onSetHeaderDiscount: (amt: string, reason: string) => void
  onOpenPayment: () => void
  onHold: () => void
}

export function CartTotalsCard({
  quote,
  estimateTotal,
  expired,
  isQuoting,
  secondsLeft,
  needsCustomer,
  linesCount,
  canDiscount,
  showCost,
  disabled,
  paymentEnabled,
  headerDiscount,
  headerDiscountReason,
  onSetHeaderDiscount,
  onOpenPayment,
  onHold,
}: CartTotalsCardProps) {
  const [headerOpen, setHeaderOpen] = useState(!!headerDiscount)

  const activeQuote = quote && !expired
  const displayTotal = activeQuote ? quote.totals.grand_total : estimateTotal
  const subtotal = activeQuote ? quote.totals.subtotal : estimateTotal
  const discount = activeQuote ? quote.totals.discount : '0'
  const tax = activeQuote ? quote.totals.tax : '0'

  return (
    <div className="bg-white border-t border-slate-200/90 p-4 space-y-3 shrink-0 shadow-[0_-2px_10px_rgba(0,0,0,0.02)]">
      {/* Financial breakdown */}
      <div className="space-y-1.5 text-xs text-slate-600">
        <div className="flex justify-between items-center">
          <span className="text-slate-500 font-medium">Subtotal</span>
          <MoneyCell value={subtotal} muted={!activeQuote} className="font-semibold text-slate-800" />
        </div>

        <div className="flex justify-between items-center">
          <div className="flex items-center gap-2">
            <span className="text-slate-500 font-medium">Discount</span>
            {canDiscount && !headerOpen && linesCount > 0 && (
              <button
                type="button"
                className="text-[11px] font-semibold text-blue-600 hover:text-blue-700 hover:underline inline-flex items-center gap-0.5 cursor-pointer"
                onClick={() => setHeaderOpen(true)}
              >
                + Add Discount
              </button>
            )}
          </div>
          <MoneyCell value={activeQuote ? `-${discount}` : '0'} className={Number(discount) > 0 ? 'text-emerald-600 font-semibold' : 'text-slate-400'} />
        </div>

        {headerOpen && canDiscount && (
          <div className="bg-slate-50 border border-slate-200/80 rounded-xl p-2.5 space-y-2 mt-1">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-semibold text-slate-700">Cart-Level Header Discount</span>
              <button
                type="button"
                onClick={() => setHeaderOpen(false)}
                className="text-[10.5px] text-slate-400 hover:text-slate-600"
              >
                Close
              </button>
            </div>
            <div className="flex gap-2">
              <div className="relative w-28">
                <span className="absolute left-2.5 top-1.5 text-[11px] text-slate-400 font-semibold">KES</span>
                <input
                  type="text"
                  inputMode="decimal"
                  placeholder="0.00"
                  value={headerDiscount}
                  disabled={disabled}
                  onChange={(e) => onSetHeaderDiscount(e.target.value.replace(/[^\d.]/g, ''), headerDiscountReason)}
                  className="w-full h-8 pl-10 pr-2 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-800 focus:outline-none focus:border-blue-500 text-right"
                />
              </div>
              <input
                type="text"
                placeholder="Reason for discount…"
                value={headerDiscountReason}
                disabled={disabled}
                onChange={(e) => onSetHeaderDiscount(headerDiscount, e.target.value)}
                className="flex-1 h-8 px-2.5 rounded-lg bg-white border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-blue-500"
              />
            </div>
          </div>
        )}

        <div className="flex justify-between items-center">
          <span className="text-slate-500 font-medium">VAT (16%)</span>
          <MoneyCell value={tax} muted={!activeQuote} className="text-slate-600" />
        </div>

        {showCost && activeQuote && quote.totals.margin_pct !== null && (
          <div className="flex justify-between items-center text-[11px] text-slate-400 pt-0.5 border-t border-dashed border-slate-100">
            <span>Estimated Profit</span>
            <span>
              {formatMoney(quote.totals.gross_profit)} ({formatPct(quote.totals.margin_pct)} margin)
            </span>
          </div>
        )}
      </div>

      <div className="border-t border-slate-200/80 pt-2.5">
        <div className="flex items-baseline justify-between">
          <div>
            <span className="text-xs font-bold text-slate-500 uppercase tracking-wider block">Total Payable</span>
            <div className="flex items-center gap-1.5 mt-0.5">
              {isQuoting ? (
                <span className="inline-flex items-center gap-1 text-[11px] text-blue-600 font-medium animate-pulse">
                  <Clock size={11} /> Calculating quote…
                </span>
              ) : activeQuote && secondsLeft !== null ? (
                <span className="inline-flex items-center gap-1 text-[11px] text-emerald-600 font-medium">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block" />
                  Locked for {Math.floor(secondsLeft / 60)}:{String(secondsLeft % 60).padStart(2, '0')}
                </span>
              ) : expired ? (
                <span className="text-[11px] text-amber-600 font-medium">Quote expired</span>
              ) : (
                <span className="text-[11px] text-slate-400">
                  {linesCount ? (needsCustomer ? 'Customer required for wholesale' : 'Estimated price') : 'Empty cart'}
                </span>
              )}
            </div>
          </div>
          <div className="text-right">
            <span className="text-xs font-semibold text-slate-400 mr-1.5">KES</span>
            <MoneyCell
              value={displayTotal}
              className={`text-2xl font-black tracking-tight text-slate-900 ${activeQuote ? '' : 'opacity-60 italic'}`}
            />
          </div>
        </div>
      </div>

      {/* Primary Action Buttons */}
      <div className="flex flex-col gap-2 pt-1">
        <button
          type="button"
          onClick={onOpenPayment}
          disabled={!paymentEnabled}
          title={paymentEnabled ? undefined : 'Complete cart lines and ensure fresh quote to proceed'}
          className="w-full h-12 rounded-xl bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 hover:from-blue-700 hover:to-indigo-800 text-white font-bold text-sm shadow-md shadow-blue-500/20 active:scale-[0.99] disabled:opacity-40 disabled:cursor-not-allowed disabled:shadow-none disabled:active:scale-100 flex items-center justify-center gap-2.5 transition-all cursor-pointer"
        >
          <span className="px-1.5 py-0.5 rounded bg-white/20 text-white text-[11px] font-mono font-bold tracking-wider">
            F10
          </span>
          <span>PROCEED TO PAYMENT</span>
        </button>

        <button
          type="button"
          onClick={onHold}
          disabled={linesCount === 0 || disabled}
          className="w-full h-9 rounded-xl border border-slate-200/90 bg-slate-50 hover:bg-slate-100 active:bg-slate-200 text-slate-700 font-semibold text-xs flex items-center justify-center gap-2 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
        >
          <span className="px-1.5 py-0.5 rounded bg-slate-200 text-slate-600 text-[10px] font-mono font-bold">
            F8
          </span>
          <span>Hold Cart for Later</span>
        </button>
      </div>
    </div>
  )
}
