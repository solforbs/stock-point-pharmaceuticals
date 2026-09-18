import { Plus, Trash2, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { Button, Kbd } from '../../components/ui/primitives'
import { dCmp, dEq, dIsPos, dSub, dSum, isValidDecimal } from '../../lib/decimal'
import { formatMoney } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { PAYMENT_METHODS, type PaymentMethod, type TenderLine } from '../../lib/types'
import { useCartStore } from './cartStore'

/**
 * Part 24.2 — Payment: disabled until every line has a fresh quote;
 * payments must sum exactly to the total. Part 24.3 fast path: type the cash
 * tendered, Enter, change displayed.
 */
export function PaymentPanel({
  open,
  onClose,
  onPost,
  isPosting,
  quoteFresh,
}: {
  open: boolean
  onClose: () => void
  onPost: (approve: boolean) => void
  isPosting: boolean
  quoteFresh: boolean
}) {
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
  const needsApproval = !!quote?.approval_required || status === 'AWAITING_APPROVAL'

  useEffect(() => {
    if (open) {
      window.setTimeout(() => cashRef.current?.focus(), 0)
      if (needsApproval && canApprove) setApprove(true)
    }
  }, [open, needsApproval, canApprove])

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
    <div className="w-[380px] shrink-0 border-l border-[var(--border)] bg-[var(--card)] flex flex-col min-h-0" data-payment-panel>
      <header className="flex items-center gap-2 px-4 py-3 border-b border-[var(--border)]">
        <div className="flex-1">
          <div className="ui-label !mb-0">Payment</div>
          <div className="text-[22px] font-extrabold tabular">{formatMoney(total)}</div>
        </div>
        <button type="button" onClick={onClose} aria-label="Close payment" className="p-1 rounded text-[var(--text-muted)] hover:text-[var(--text)]">
          <X size={16} />
        </button>
      </header>

      <div className="flex gap-1 p-2 border-b border-[var(--border)]">
        {(['cash', 'split', 'credit'] as const).map((m) => {
          const disabledTab = m === 'credit' && saleMode !== 'WHOLESALE'
          return (
            <button
              key={m}
              type="button"
              disabled={disabledTab}
              onClick={() => setMode(m)}
              className={`flex-1 h-8 rounded text-[11.5px] font-bold uppercase ${mode === m ? 'bg-[var(--color-navy)] text-white' : 'bg-[var(--surface-2)] text-[var(--text-secondary)]'} disabled:opacity-40`}
              title={disabledTab ? 'Credit terms are a wholesale feature' : undefined}
            >
              {m === 'cash' ? 'Cash' : m === 'split' ? 'Split / other' : 'On credit'}
            </button>
          )
        })}
      </div>

      <div className="flex-1 overflow-y-auto p-4 space-y-3">
        {!quoteFresh && (
          <div className="text-[11.5px] rounded-md px-3 py-2 border border-[var(--status-amber)] bg-[color-mix(in_srgb,var(--status-amber)_14%,transparent)]">
            Waiting for a fresh server quote — payment is disabled until every line is priced.
          </div>
        )}

        {mode === 'cash' && (
          <>
            <label className="ui-label">Cash tendered</label>
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
              className="ui-input h-12 text-[22px] tabular text-right font-bold"
              placeholder={formatMoney(total)}
            />
            <div className="flex gap-1.5 flex-wrap">
              <Button size="sm" onClick={() => setCashTendered(total.replace(/\.?0+$/, ''))}>
                Exact
              </Button>
              {[100, 200, 500, 1000, 5000].map((n) => (
                <Button key={n} size="sm" onClick={() => setCashTendered(String(n))}>
                  {n.toLocaleString()}
                </Button>
              ))}
            </div>
            <div className="grid grid-cols-[1fr_auto] gap-y-1 text-[13px] tabular pt-2">
              <span className="text-[var(--text-muted)]">Total due</span>
              <MoneyCell value={total} />
              <span className="text-[var(--text-muted)]">Tendered</span>
              <MoneyCell value={tendered} />
              <span className="font-bold text-[15px]">Change</span>
              <MoneyCell value={cashOk ? change : '0'} className={`font-extrabold text-[18px] ${cashOk ? 'text-[var(--status-green)]' : ''}`} />
              {!cashOk && dIsPos(tendered) && <span className="col-span-2 text-[11px] text-[var(--status-red)]">Short by {formatMoney(dSub(total, tendered))}</span>}
            </div>
          </>
        )}

        {mode === 'split' && (
          <>
            <div className="space-y-2">
              {payments.map((p, i) => (
                <div key={i} className="grid grid-cols-[92px_1fr_1fr_auto] gap-1.5 items-center">
                  <select value={p.method} onChange={(e) => updateTender(i, { method: e.target.value as PaymentMethod })} className="ui-input h-8">
                    {PAYMENT_METHODS.map((m) => (
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
                    className="ui-input h-8 tabular text-right"
                    placeholder="Amount"
                  />
                  <input
                    type="text"
                    value={p.reference ?? ''}
                    onChange={(e) => updateTender(i, { reference: e.target.value })}
                    className="ui-input h-8"
                    placeholder={p.method === 'MPESA' ? 'M-PESA ref' : 'Reference'}
                  />
                  <button type="button" aria-label="Remove tender" onClick={() => setPayments(payments.filter((_, j) => j !== i))} className="p-1 text-[var(--text-muted)] hover:text-[var(--status-red)]">
                    <Trash2 size={14} />
                  </button>
                </div>
              ))}
            </div>
            <Button size="sm" onClick={() => setPayments([...payments, { method: 'MPESA', amount: dIsPos(remaining) ? remaining.replace(/\.?0+$/, '') : '', reference: '' }])}>
              <Plus size={12} /> Add tender
            </Button>
            <div className="grid grid-cols-[1fr_auto] gap-y-1 text-[13px] tabular pt-2">
              <span className="text-[var(--text-muted)]">Total due</span>
              <MoneyCell value={total} />
              <span className="text-[var(--text-muted)]">Tendered</span>
              <MoneyCell value={splitSum} />
              <span className="font-bold">Remaining</span>
              <MoneyCell value={remaining} className={`font-bold ${dEq(remaining, '0') ? 'text-[var(--status-green)]' : ''}`} />
              {!dEq(remaining, '0') && <span className="col-span-2 text-[11px] text-[var(--status-red)]">Payments must sum exactly to the total (the server refuses a mismatch).</span>}
            </div>
          </>
        )}

        {mode === 'credit' && (
          <div className="text-[12.5px] space-y-2">
            {customer ? (
              <>
                <p>
                  Invoice <b>{customer.name}</b> on credit. The server runs the credit check at posting: limit {formatMoney(customer.credit?.credit_limit ?? '0')}, available{' '}
                  <b>{formatMoney(customer.available_credit ?? '0')}</b>.
                </p>
                {customer.credit?.on_hold && <p className="text-[var(--status-red)] font-bold">This customer is on credit hold: {customer.credit.hold_reason ?? 'no reason recorded'}.</p>}
                {dCmp(customer.available_credit ?? '0', total) < 0 && <p className="text-[#b45309]">This sale exceeds the available credit; the server will refuse it with CREDIT_LIMIT_EXCEEDED.</p>}
              </>
            ) : (
              <p className="text-[var(--status-red)]">Select a customer first (F5).</p>
            )}
          </div>
        )}

        {needsApproval && (
          <label className={`flex items-start gap-2 text-[12px] p-2 rounded border ${canApprove ? 'border-[var(--status-amber)]' : 'border-[var(--border)] opacity-70'}`}>
            <input type="checkbox" checked={approve && canApprove} disabled={!canApprove} onChange={(e) => setApprove(e.target.checked)} className="mt-0.5" />
            <span>
              {canApprove ? 'Approve the flagged discounts as the signed-in approver and post.' : 'Discounts need approval. Posting will hold the sale for an approver (sale.discount.approve).'}
            </span>
          </label>
        )}

        {checkoutError && (
          <div role="alert" className="text-[11.5px] rounded-md px-3 py-2 border border-[var(--status-red)] bg-[color-mix(in_srgb,var(--status-red)_8%,transparent)]">
            <div className="font-bold">{checkoutError.code}</div>
            <div>{checkoutError.message}</div>
            {checkoutError.code === 'INSUFFICIENT_STOCK' && (
              <div className="tabular mt-1 text-[var(--text-secondary)]">
                Requested {String(checkoutError.details.requested ?? '')} · available {String(checkoutError.details.available ?? '')} · short {String(checkoutError.details.shortfall ?? '')}
              </div>
            )}
            {checkoutError.code === 'PAYMENT_MISMATCH' && (
              <div className="tabular mt-1 text-[var(--text-secondary)]">
                Total {formatMoney(String(checkoutError.details.grand_total ?? ''))} · tendered {formatMoney(String(checkoutError.details.tendered ?? ''))}
              </div>
            )}
            {checkoutError.code === 'CREDIT_LIMIT_EXCEEDED' && (
              <div className="tabular mt-1 text-[var(--text-secondary)]">
                Limit {formatMoney(String(checkoutError.details.limit ?? ''))} · exposure {formatMoney(String(checkoutError.details.exposure ?? ''))} · short {formatMoney(String(checkoutError.details.shortfall ?? ''))}
              </div>
            )}
          </div>
        )}
      </div>

      <footer className="p-3 border-t border-[var(--border)]">
        <Button variant="success" size="lg" className="w-full" disabled={!canPost} onClick={post}>
          {isPosting ? 'Posting…' : mode === 'credit' ? 'Post invoice on credit' : 'Post sale'} <Kbd>Enter</Kbd>
        </Button>
        <p className="text-[10.5px] text-[var(--text-muted)] text-center mt-1.5">Posting is atomic and idempotent: a retry never creates a second sale.</p>
      </footer>
    </div>
  )
}
