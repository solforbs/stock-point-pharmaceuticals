import { AlertCircle, Clock, FileText, Tag, UserCheck } from 'lucide-react'
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
  onPreviewCart?: () => void
  onFocusCustomer?: () => void
  onSwitchToRetail?: () => void
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
  onPreviewCart,
  onFocusCustomer,
  onSwitchToRetail,
}: CartTotalsCardProps) {
  const [headerOpen, setHeaderOpen] = useState(!!headerDiscount)

  const activeQuote = quote && !expired
  const displayTotal = activeQuote ? quote.totals.grand_total : estimateTotal
  const subtotal = activeQuote ? quote.totals.subtotal : estimateTotal
  const discount = activeQuote ? quote.totals.discount : '0'
  const tax = activeQuote ? quote.totals.tax : '0'

  return (
    <div className="bg-white border-t border-slate-200/90 p-3.5 space-y-2.5 shrink-0 shadow-[0_-2px_8px_rgba(0,0,0,0.03)]">
      {/* Financial breakdown compact row */}
      <div className="space-y-1 text-xs">
        <div className="flex items-center justify-between text-slate-500">
          <div className="flex items-center gap-3">
            <span>
              Subtotal: <strong className="text-slate-800 tabular font-semibold">{formatMoney(subtotal)}</strong>
            </span>
            <span>·</span>
            <span>
              VAT (16%): <strong className="text-slate-800 tabular font-semibold">{formatMoney(tax)}</strong>
            </span>
          </div>

          <div className="flex items-center gap-1.5">
            {canDiscount && !headerOpen && linesCount > 0 && (
              <button
                type="button"
                className="text-xs font-semibold text-blue-600 hover:text-blue-700 hover:underline inline-flex items-center gap-1 cursor-pointer"
                onClick={() => setHeaderOpen(true)}
              >
                <Tag size={11} /> + Discount
              </button>
            )}
            {Number(discount) > 0 && (
              <span className="text-emerald-700 font-bold tabular text-xs">
                -{formatMoney(discount)}
              </span>
            )}
          </div>
        </div>

        {headerOpen && canDiscount && (
          <div className="bg-slate-50 border border-slate-200/80 rounded-xl p-2.5 space-y-2 mt-1">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-slate-700">Cart-Level Header Discount</span>
              <button
                type="button"
                onClick={() => setHeaderOpen(false)}
                className="text-xs text-slate-500 hover:text-slate-800 cursor-pointer"
              >
                Done
              </button>
            </div>
            <div className="flex gap-2">
              <div className="relative w-32">
                <span className="absolute left-2.5 top-1.5 text-xs text-slate-400 font-semibold">KES</span>
                <input
                  type="text"
                  inputMode="decimal"
                  placeholder="0.00"
                  value={headerDiscount}
                  disabled={disabled}
                  onChange={(e) => onSetHeaderDiscount(e.target.value.replace(/[^\d.]/g, ''), headerDiscountReason)}
                  className="w-full h-8 pl-10 pr-2 rounded-lg bg-white border border-slate-300 text-xs font-semibold text-slate-800 focus:outline-none focus:border-blue-500 text-right"
                />
              </div>
              <input
                type="text"
                placeholder="Reason for discount…"
                value={headerDiscountReason}
                disabled={disabled}
                onChange={(e) => onSetHeaderDiscount(headerDiscount, e.target.value)}
                className="flex-1 h-8 px-2.5 rounded-lg bg-white border border-slate-300 text-xs text-slate-800 focus:outline-none focus:border-blue-500"
              />
            </div>
          </div>
        )}

        {showCost && activeQuote && quote.totals.margin_pct !== null && (
          <div className="flex justify-between items-center text-xs text-slate-500 pt-0.5 border-t border-slate-100">
            <span>Profit & Margin</span>
            <span className="font-medium text-slate-700">
              {formatMoney(quote.totals.gross_profit)} ({formatPct(quote.totals.margin_pct)} margin)
            </span>
          </div>
        )}
      </div>

      {/* Grand Total Row */}
      <div className="flex items-baseline justify-between pt-1.5 border-t border-slate-100">
        <div>
          <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Total Payable</span>
          <div className="flex items-center gap-1.5 mt-0.5">
            {isQuoting ? (
              <span className="inline-flex items-center gap-1 text-xs text-blue-600 font-medium animate-pulse">
                <Clock size={12} /> Pricing quote…
              </span>
            ) : activeQuote && secondsLeft !== null ? (
              <span className="inline-flex items-center gap-1.5 text-xs text-emerald-800 font-semibold bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block animate-pulse" />
                Price guaranteed: {Math.floor(secondsLeft / 60)}:{String(secondsLeft % 60).padStart(2, '0')}
              </span>
            ) : expired ? (
              <span className="text-xs text-amber-700 font-semibold">Quote expired — re-quote</span>
            ) : (
              <span className="text-xs font-medium text-slate-500">
                {linesCount ? (
                  needsCustomer ? (
                    <span className="text-amber-800 font-semibold">Customer required for wholesale</span>
                  ) : (
                    'Local estimate'
                  )
                ) : (
                  'Cart is empty'
                )}
              </span>
            )}
          </div>
        </div>
        <div className="text-right">
          <span className="text-xs font-bold text-slate-400 mr-1.5">KES</span>
          <MoneyCell
            value={displayTotal}
            className={`text-2xl font-bold tracking-tight text-slate-900 tabular ${activeQuote ? '' : 'opacity-60 italic'}`}
          />
        </div>
      </div>

      {/* Customer Guidance Alert when in Wholesale Mode without customer */}
      {needsCustomer && linesCount > 0 && (
        <div className="flex items-center justify-between gap-2 p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900">
          <div className="flex items-center gap-1.5 font-semibold min-w-0">
            <AlertCircle size={14} className="text-amber-600 shrink-0" />
            <span className="truncate">Wholesale requires a customer account</span>
          </div>
          {onSwitchToRetail && (
            <button
              type="button"
              onClick={onSwitchToRetail}
              className="px-2.5 py-1 rounded-lg bg-white hover:bg-amber-100 text-amber-900 font-semibold border border-amber-300 text-xs cursor-pointer transition-colors shadow-2xs shrink-0"
            >
              Switch to Retail
            </button>
          )}
        </div>
      )}

      {/* Side-by-Side Action Buttons */}
      <div className="flex items-center gap-2.5 pt-1.5">
        {onPreviewCart && (
          <button
            type="button"
            onClick={onPreviewCart}
            disabled={linesCount === 0}
            className="h-11 px-4 rounded-2xl bg-slate-100 hover:bg-slate-200/80 active:bg-slate-200 text-slate-700 font-bold text-xs flex items-center justify-center gap-1.5 transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shrink-0 border border-slate-200/50 shadow-2xs"
            title="Review items and print preview"
          >
            <FileText size={15} className="text-slate-500" />
            <span className="hidden sm:inline">Review ({linesCount})</span>
          </button>
        )}

        <button
          type="button"
          onClick={onHold}
          disabled={linesCount === 0 || disabled}
          className="h-11 px-4 rounded-2xl bg-slate-100 hover:bg-slate-200/80 active:bg-slate-200 text-slate-700 font-bold text-xs flex items-center justify-center gap-2 transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shrink-0 border border-slate-200/50 shadow-2xs"
        >
          <span className="px-1.5 py-0.5 rounded-full bg-white text-slate-600 text-[10px] font-mono font-bold shadow-2xs">
            F8
          </span>
          <span>Hold</span>
        </button>

        {needsCustomer && linesCount > 0 ? (
          <button
            type="button"
            onClick={onFocusCustomer}
            className="flex-1 h-11 rounded-2xl bg-amber-600 hover:bg-amber-700 active:scale-[0.99] text-white font-bold text-sm shadow-md shadow-amber-600/20 flex items-center justify-center gap-2 transition-all cursor-pointer"
          >
            <UserCheck size={16} />
            <span>SELECT CUSTOMER (F5)</span>
          </button>
        ) : (
          <button
            type="button"
            onClick={onOpenPayment}
            disabled={!paymentEnabled}
            title={paymentEnabled ? undefined : linesCount === 0 ? 'Add items to cart' : 'Waiting for price quote…'}
            className="flex-1 h-11 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 active:scale-[0.99] text-white font-bold text-sm shadow-md shadow-blue-500/20 disabled:opacity-40 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:shadow-none flex items-center justify-center gap-2.5 transition-all cursor-pointer"
          >
            <span className="px-2 py-0.5 rounded-full bg-white/20 text-white text-xs font-mono font-bold">
              F10
            </span>
            <span>PROCEED TO PAYMENT</span>
          </button>
        )}
      </div>
    </div>
  )
}
