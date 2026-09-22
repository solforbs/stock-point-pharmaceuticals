import { ArrowLeft, CheckCircle2, CreditCard, DollarSign, Plus, Split, Trash2, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { dCmp, dEq, dIsPos, dSub, dSum, isValidDecimal } from '../../lib/decimal'
import { formatMoney } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { PAYMENT_METHODS, type PaymentMethod, type TenderLine } from '../../lib/types'
import { useCartStore } from './cartStore'
import { isOfflineQuote } from './offlineQuote'

/** Part 16.7 — what an offline sale can be paid with: nothing that needs the server to clear. */
const OFFLINE_METHODS: PaymentMethod[] = ['CASH', 'MPESA', 'CARD']

export interface PaymentPanelProps {
  open: boolean
  onClose: () => void
  onPost: (approve: boolean) => void
  isPosting: boolean
  quoteFresh: boolean
}

export function PaymentPanel({
  open,
  onClose,
  onPost,
  isPosting,
  quoteFresh,
}: PaymentPanelProps) {
  const canApprove = usePermission('sale.discount.approve')
  const quote = useCartStore((s) => s.quote)
  const saleMode = useCartStore((s) => s.saleMode)
  const customer = useCartStore((s) => s.customer)
  const payments = useCartStore((s) => s.payments)
  const cashTendered = useCartStore((s) => s.cashTendered)
  const checkoutError = useCartStore((s) => s.checkoutError)
  const status = useCartStore((s) => s.status)
  const setPayments = useCartStore((s) => s.setPayments)
  const setCashTendered = useCartStore((s) => s.setCashTendered)
  const [mode, setMode] = useState<'cash' | 'split' | 'credit'>('cash')
  const [approve, setApprove] = useState(false)
  const cashRef = useRef<HTMLInputElement>(null)

  const total = quote?.totals.grand_total ?? '0'
  const offline = isOfflineQuote(quote)
  const needsApproval = !!quote?.approval_required || status === 'AWAITING_APPROVAL'

  useEffect(() => {
    if (open) {
      window.setTimeout(() => cashRef.current?.focus(), 0)
      if (needsApproval && canApprove) setApprove(true)
      if (dIsPos(total) && !cashTendered) {
        setCashTendered(total.replace(/\.?0+$/, ''))
      }
    }
  }, [open, needsApproval, canApprove, total, cashTendered, setCashTendered])

  useEffect(() => {
    if (mode === 'cash') setPayments(dIsPos(total) ? [{ method: 'CASH', amount: total }] : [])
    else if (mode === 'credit') setPayments([])
  }, [mode, total, setPayments])

  if (!open) return null

  const tendered = isValidDecimal(cashTendered) ? cashTendered : '0'
  const change = dSub(tendered, total)
  const cashOk = dCmp(tendered, total) >= 0
  const splitSum = dSum(payments.map((p) => (isValidDecimal(p.amount) ? p.amount : '0')))
  const remaining = dSub(total, splitSum)
  const splitOk = dEq(remaining, '0') && payments.every((p) => isValidDecimal(p.amount) && dIsPos(p.amount))
  const creditOk = saleMode === 'WHOLESALE' && !!customer && !customer.credit?.on_hold
  const canPost = quoteFresh && !isPosting && (mode === 'cash' ? cashOk : mode === 'split' ? splitOk : creditOk)

  function updateTender(index: number, patch: Partial<TenderLine>) {
    setPayments(payments.map((p, i) => (i === index ? { ...p, ...patch } : p)))
  }

  function post() {
    if (!canPost) return
    onPost(approve && canApprove)
  }

  return (
    <div className="fixed inset-0 z-50 flex justify-end" data-payment-panel>
      {/* Dimmed backdrop */}
      <div
        className="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity animate-in fade-in duration-200"
        onClick={onClose}
      />

      {/* Slide-over Drawer Panel (480px on desktop, full-width on mobile) */}
      <div className="relative z-10 w-full sm:w-[480px] h-full bg-white shadow-2xl flex flex-col border-l border-slate-200 animate-in slide-in-from-right duration-200">
        {/* Header */}
        <header className="flex items-center justify-between px-5 py-4 border-b border-slate-200/90 bg-slate-50/80">
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={onClose}
              className="p-1.5 -ml-1 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-200/70 transition-colors cursor-pointer"
              title="Back to cart (Esc)"
            >
              <ArrowLeft size={18} />
            </button>
            <div>
              <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 block">
                Checkout & Tender
              </span>
              <div className="text-2xl font-bold tracking-tight text-slate-900 tabular leading-none mt-0.5">
                KES {formatMoney(total)}
              </div>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close payment"
            className="p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-200/70 transition-colors cursor-pointer"
          >
            <X size={20} />
          </button>
        </header>

      {/* Tender Mode Selection Tabs */}
      <div className="p-3 border-b border-slate-100 bg-white">
        <div className="flex gap-1 p-1 bg-slate-100 rounded-full">
          {(['cash', 'split', 'credit'] as const).map((m) => {
            const disabledTab = m === 'credit' && (saleMode !== 'WHOLESALE' || offline)
            const isActive = mode === m
            return (
              <button
                key={m}
                type="button"
                disabled={disabledTab}
                onClick={() => setMode(m)}
                className={`flex-1 h-9 rounded-full text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer ${
                  isActive
                    ? 'bg-blue-600 text-white shadow-xs'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-white/80'
                } disabled:opacity-30 disabled:cursor-not-allowed`}
                title={disabledTab ? (offline ? 'Credit needs the server' : 'Credit terms require wholesale mode') : undefined}
              >
                {m === 'cash' ? <DollarSign size={13} /> : m === 'split' ? <Split size={13} /> : <CreditCard size={13} />}
                <span>{m === 'cash' ? 'Cash' : m === 'split' ? 'Split / M-Pesa' : 'On Credit'}</span>
              </button>
            )
          })}
        </div>
      </div>

      <div className="flex-1 overflow-y-auto p-4 space-y-4">
        {!quoteFresh && (
          <div className="text-xs font-bold rounded-2xl px-3.5 py-2.5 border border-amber-200 bg-amber-50 text-amber-900">
            Waiting for a fresh quote — payment unlocks once server verifies stock and prices.
          </div>
        )}

        {/* CASH TENDER VIEW */}
        {mode === 'cash' && (
          <div className="space-y-3">
            <div>
              <label className="text-xs font-bold text-slate-700 mb-1.5 block">
                Cash Tendered (KES)
              </label>
              <input
                ref={cashRef}
                type="text"
                inputMode="decimal"
                value={cashTendered}
                onChange={(e) => setCashTendered(e.target.value.replace(/[^\d.]/g, ''))}
                onFocus={(e) => e.target.select()}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault()
                    post()
                  }
                }}
                className="w-full h-12 px-3.5 rounded-2xl bg-slate-50/80 border border-slate-200 text-2xl tabular text-right font-black text-slate-900 focus:bg-white focus:outline-none focus:border-blue-500 shadow-inner"
                placeholder={formatMoney(total)}
              />
            </div>

            {/* Quick Denomination Chips */}
            <div className="grid grid-cols-3 gap-2">
              <button
                type="button"
                onClick={() => setCashTendered(total.replace(/\.?0+$/, ''))}
                className="h-9 rounded-2xl bg-slate-100/90 hover:bg-blue-50 hover:text-blue-700 text-slate-800 text-xs font-bold border border-slate-200/50 transition-all cursor-pointer flex items-center justify-center shadow-2xs active:scale-[0.98]"
              >
                Exact Cash
              </button>
              {[100, 200, 500, 1000, 5000].map((n) => (
                <button
                  key={n}
                  type="button"
                  onClick={() => setCashTendered(String(n))}
                  className="h-9 rounded-2xl bg-slate-100/90 hover:bg-blue-50 hover:text-blue-700 text-slate-800 text-xs font-bold border border-slate-200/50 transition-all cursor-pointer tabular flex items-center justify-center shadow-2xs active:scale-[0.98]"
                >
                  +{n.toLocaleString()}
                </button>
              ))}
            </div>

            {/* Change & Balance Due Card */}
            <div className="p-3 rounded-xl bg-slate-50 border border-slate-200/90 space-y-2 text-xs tabular">
              <div className="flex justify-between text-slate-600">
                <span>Total Payable</span>
                <MoneyCell value={total} className="font-bold text-slate-800" />
              </div>
              <div className="flex justify-between text-slate-600">
                <span>Cash Tendered</span>
                <MoneyCell value={tendered} className="font-bold text-slate-800" />
              </div>
              <div className="border-t border-slate-200 pt-2 flex items-baseline justify-between">
                <span className="font-extrabold text-sm text-slate-900 uppercase">Change Due</span>
                <MoneyCell
                  value={cashOk ? change : '0'}
                  className={`font-black text-xl tabular ${cashOk ? 'text-emerald-600' : 'text-slate-400'}`}
                />
              </div>
              {!cashOk && dIsPos(tendered) && (
                <div className="text-xs font-bold text-rose-600 pt-1 border-t border-rose-100">
                  Short by KES {formatMoney(dSub(total, tendered))}
                </div>
              )}
            </div>
          </div>
        )}

        {/* SPLIT / M-PESA VIEW */}
        {mode === 'split' && (
          <div className="space-y-3">
            <div className="space-y-2">
              {payments.map((p, i) => (
                <div key={i} className="grid grid-cols-[96px_1fr_1fr_auto] gap-1.5 items-center">
                  <select
                    value={p.method}
                    onChange={(e) => updateTender(i, { method: e.target.value as PaymentMethod })}
                    className="h-8 px-2 rounded-lg bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800"
                  >
                    {(offline ? OFFLINE_METHODS : PAYMENT_METHODS).map((m) => (
                      <option key={m} value={m}>
                        {m}
                      </option>
                    ))}
                  </select>
                  <input
                    type="text"
                    inputMode="decimal"
                    value={p.amount}
                    onChange={(e) => updateTender(i, { amount: e.target.value.replace(/[^\d.]/g, '') })}
                    className="h-8 px-2 rounded-lg bg-slate-50 border border-slate-200 text-xs font-bold tabular text-right text-slate-800"
                    placeholder="Amount"
                  />
                  <input
                    type="text"
                    value={p.reference ?? ''}
                    onChange={(e) => updateTender(i, { reference: e.target.value })}
                    className="h-8 px-2 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-800"
                    placeholder={p.method === 'MPESA' ? 'M-PESA Code' : 'Reference'}
                  />
                  <button
                    type="button"
                    onClick={() => setPayments(payments.filter((_, j) => j !== i))}
                    className="p-1 rounded text-slate-400 hover:text-rose-600 hover:bg-rose-50 cursor-pointer"
                  >
                    <Trash2 size={14} />
                  </button>
                </div>
              ))}
            </div>

            <button
              type="button"
              onClick={() =>
                setPayments([
                  ...payments,
                  { method: 'MPESA', amount: dIsPos(remaining) ? remaining.replace(/\.?0+$/, '') : '', reference: '' },
                ])
              }
              className="px-4 py-2 rounded-full bg-blue-50 text-blue-700 hover:bg-blue-100 text-xs font-bold flex items-center gap-1.5 cursor-pointer transition-colors shadow-2xs"
            >
              <Plus size={13} /> Add Payment Method
            </button>

            <div className="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2 text-xs tabular">
              <div className="flex justify-between text-slate-600">
                <span>Total Due</span>
                <MoneyCell value={total} className="font-bold text-slate-800" />
              </div>
              <div className="flex justify-between text-slate-600">
                <span>Total Tendered</span>
                <MoneyCell value={splitSum} className="font-bold text-slate-800" />
              </div>
              <div className="border-t border-slate-200 pt-1.5 flex justify-between font-extrabold text-slate-900">
                <span>Remaining</span>
                <MoneyCell
                  value={remaining}
                  className={dEq(remaining, '0') ? 'text-emerald-700' : 'text-rose-700'}
                />
              </div>
              {!dEq(remaining, '0') && (
                <p className="text-xs font-semibold text-rose-700 pt-1">
                  Payments must sum exactly to the total.
                </p>
              )}
            </div>
          </div>
        )}

        {/* WHOLESALE CREDIT VIEW */}
        {mode === 'credit' && (
          <div className="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 text-xs space-y-2">
            {customer ? (
              <>
                <p className="text-slate-700">
                  Invoice <strong className="text-slate-900">{customer.name}</strong> on 30-day credit terms.
                </p>
                <div className="pt-2 border-t border-slate-200 space-y-1 text-slate-600">
                  <div className="flex justify-between">
                    <span>Credit Limit:</span>
                    <strong className="text-slate-800">{formatMoney(customer.credit?.credit_limit ?? '0')}</strong>
                  </div>
                  <div className="flex justify-between">
                    <span>Available Credit:</span>
                    <strong className="text-emerald-700">{formatMoney(customer.available_credit ?? '0')}</strong>
                  </div>
                </div>
                {customer.credit?.on_hold && (
                  <p className="text-rose-700 font-bold pt-1">
                    Account is on credit hold: {customer.credit.hold_reason ?? 'Administrative hold'}.
                  </p>
                )}
              </>
            ) : (
              <p className="text-rose-700 font-bold">
                Please select a customer account first (F5).
              </p>
            )}
          </div>
        )}

        {needsApproval && (
          <label className={`flex items-start gap-2.5 text-xs p-3.5 rounded-2xl border ${canApprove ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-slate-200 bg-slate-50 text-slate-600'}`}>
            <input
              type="checkbox"
              checked={approve && canApprove}
              disabled={!canApprove}
              onChange={(e) => setApprove(e.target.checked)}
              className="mt-0.5 rounded border-slate-300 text-blue-600"
            />
            <span className="font-semibold">
              {canApprove
                ? 'Approve flagged discounts with manager credentials and post immediately.'
                : 'Discount requires approval. Posting will place sale in queue for supervisor sign-off.'}
            </span>
          </label>
        )}

        {checkoutError && (
          <div role="alert" className="text-xs rounded-2xl p-3.5 border border-rose-200 bg-rose-50 text-rose-900 space-y-1">
            <div className="font-bold">{checkoutError.code}</div>
            <div>{checkoutError.message}</div>
          </div>
        )}
      </div>

      {/* Footer / Complete Payment CTA */}
      <footer className="p-4 border-t border-slate-200 bg-slate-50/50">
        <button
          type="button"
          disabled={!canPost}
          onClick={post}
          className="w-full h-12 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 active:scale-[0.99] text-white font-bold text-sm shadow-md shadow-emerald-600/25 disabled:opacity-40 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none flex items-center justify-center gap-2.5 cursor-pointer transition-all"
        >
          <CheckCircle2 size={17} />
          <span>
            {isPosting ? 'POSTING SALE…' : mode === 'credit' ? 'POST INVOICE ON CREDIT' : `COMPLETE SALE (KES ${formatMoney(total)})`}
          </span>
          <span className="px-2 py-0.5 rounded-full bg-white/20 text-white text-xs font-mono font-bold">
            Enter
          </span>
        </button>
        <p className="text-xs text-slate-500 text-center mt-2 font-medium">
          {offline
            ? 'Offline: the sale is saved on this till and sent to the server when the connection returns.'
            : 'Receipt prints automatically and inventory is updated immediately.'}
        </p>
      </footer>
      </div>
    </div>
  )
}
