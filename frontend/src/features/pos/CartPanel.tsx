import { AlertTriangle, RefreshCw, ShoppingCart } from 'lucide-react'
import { useState, type RefObject } from 'react'
import { ApprovalBar } from '../../components/ApprovalBar'
import { CustomerPicker } from '../../components/CustomerPicker'
import { ConfirmDialog } from '../../components/ui/Modal'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { EmptyState } from '../../components/ui/States'
import { Button, Kbd } from '../../components/ui/primitives'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { dSum } from '../../lib/decimal'
import { formatMoney, formatPct } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import type { SaleMode } from '../../lib/types'
import { CartLineRow } from './CartLineRow'
import { useCartStore } from './cartStore'

export function CartPanel({
  quoteState,
  customerInputRef,
  onOpenPayment,
  onHold,
  onApprove,
  approvePending,
}: {
  quoteState: { isQuoting: boolean; isFresh: boolean; expired: boolean; secondsLeft: number | null; needsCustomer: boolean; requote: () => void }
  customerInputRef: RefObject<HTMLInputElement | null>
  onOpenPayment: () => void
  onHold: () => void
  onApprove: () => void
  approvePending: boolean
}) {
  const canDiscount = usePermission('sale.discount.apply')
  const canApprove = usePermission('sale.discount.approve')
  const showCost = usePermission('product.cost.view')

  const lines = useCartStore((s) => s.lines)
  const quote = useCartStore((s) => s.quote)
  const quoteError = useCartStore((s) => s.quoteError)
  const customer = useCartStore((s) => s.customer)
  const saleMode = useCartStore((s) => s.saleMode)
  const status = useCartStore((s) => s.status)
  const selectedLineRef = useCartStore((s) => s.selectedLineRef)
  const headerDiscount = useCartStore((s) => s.headerDiscount)
  const headerDiscountReason = useCartStore((s) => s.headerDiscountReason)
  const approvalLines = useCartStore((s) => s.approvalLines)
  const modeSwitchNote = useCartStore((s) => s.modeSwitchNote)
  const setCustomer = useCartStore((s) => s.setCustomer)
  const selectLine = useCartStore((s) => s.selectLine)
  const removeLine = useCartStore((s) => s.removeLine)
  const setHeaderDiscount = useCartStore((s) => s.setHeaderDiscount)
  const setSaleMode = useCartStore((s) => s.setSaleMode)
  const dismissModeSwitchNote = useCartStore((s) => s.dismissModeSwitchNote)
  const { data: currentUser } = useCurrentUser()
  // The server says which mode this terminal may trade in: fall back to the
  // branch default (or the mode it named in details) when a switch is refused.
  const fallbackMode = ((quoteError?.details.default_mode as SaleMode | undefined) ?? currentUser?.default_sale_mode ?? (quoteError?.details.enabled_modes as SaleMode[] | undefined)?.[0]) ?? null
  const [removing, setRemoving] = useState<string | null>(null)
  const [headerOpen, setHeaderOpen] = useState(!!headerDiscount)

  const disabled = status === 'POSTING' || status === 'POSTED'
  const { isFresh, isQuoting, expired, secondsLeft, needsCustomer, requote } = quoteState
  const estimateTotal = dSum(lines.map((l) => l.localEstimate))
  const flaggedCount = quote?.lines.filter((l) => l.approval_required).length ?? 0
  const paymentEnabled = isFresh && !disabled && lines.length > 0

  return (
    <div className="flex-1 min-w-0 flex flex-col bg-[var(--bg)]">
      <div className="px-4 pt-3 pb-2 flex items-center gap-3 border-b border-[var(--border)] bg-[var(--card)]">
        <div className="flex-1 min-w-0">
          <div className="ui-label !mb-1">
            Customer <Kbd>F5</Kbd> {saleMode === 'WHOLESALE' && <span className="text-[var(--status-red)]">required</span>}
          </div>
          <CustomerPicker value={customer} onChange={setCustomer} inputRef={customerInputRef} required={needsCustomer} disabled={disabled} />
        </div>
        <div className="text-right shrink-0">
          <div className="text-[11px] text-[var(--text-muted)] uppercase tracking-wide">Cart</div>
          <div className="text-[15px] font-extrabold tabular">{lines.length} lines</div>
        </div>
      </div>

      {modeSwitchNote && quote && modeSwitchNote.beforeTotal !== null && (
        <div className="mx-4 mt-2 px-3 py-2 rounded-md border border-[var(--status-blue)] bg-[color-mix(in_srgb,var(--status-blue)_10%,transparent)] text-[12px] flex items-center gap-3">
          <span className="flex-1 tabular">
            Re-quoted after switching {modeSwitchNote.from} → {modeSwitchNote.to}: was <b>{formatMoney(modeSwitchNote.beforeTotal)}</b>, now <b>{formatMoney(quote.totals.grand_total)}</b>
          </span>
          <Button size="sm" variant="ghost" onClick={dismissModeSwitchNote}>
            OK
          </Button>
        </div>
      )}

      {expired && lines.length > 0 && (
        <div className="mx-4 mt-2 px-3 py-2 rounded-md border border-[var(--status-amber)] bg-[color-mix(in_srgb,var(--status-amber)_14%,transparent)] text-[12px] flex items-center gap-3">
          <AlertTriangle size={15} className="text-[#b45309]" />
          <span className="flex-1">This quote has expired. Prices must be re-confirmed before payment.</span>
          <Button size="sm" variant="primary" onClick={requote} disabled={isQuoting}>
            <RefreshCw size={12} /> Re-quote
          </Button>
        </div>
      )}

      {quoteError && (
        <div className="mx-4 mt-2 px-3 py-2 rounded-md border border-[var(--status-red)] bg-[color-mix(in_srgb,var(--status-red)_8%,transparent)] text-[12px] flex items-center gap-3">
          <span className="flex-1">
            <b className="mr-1.5">{quoteError.code}</b>
            {quoteError.message}
          </span>
          {(quoteError.code === 'MODE_SWITCH_FORBIDDEN' || quoteError.code === 'MODE_DISABLED') && fallbackMode ? (
            <Button size="sm" variant="primary" onClick={() => setSaleMode(fallbackMode)}>
              Switch back to {fallbackMode}
            </Button>
          ) : (
            <Button size="sm" onClick={requote} disabled={isQuoting}>
              Retry
            </Button>
          )}
        </div>
      )}

      {(flaggedCount > 0 || status === 'AWAITING_APPROVAL') && !disabled && (
        <div className="mx-4 mt-2">
          <ApprovalBar
            title={status === 'AWAITING_APPROVAL' ? 'Approval required before this sale can post' : `${flaggedCount} line${flaggedCount === 1 ? '' : 's'} need approval`}
            message={
              status === 'AWAITING_APPROVAL'
                ? `The server held the sale (lines ${approvalLines.join(', ') || '—'}). An approver with sale.discount.approve can post it from this terminal, or hold the cart (F8) for a manager to resume.`
                : 'Discounts on these lines exceed your authority. Posting will ask an approver to sign.'
            }
            canApprove={canApprove && isFresh}
            approveLabel="Approve & post"
            onApprove={onApprove}
            isPending={approvePending}
          />
        </div>
      )}

      <div className="flex-1 min-h-0 overflow-y-auto pos-scroll mt-2 mx-4 ui-card">
        {lines.length === 0 ? (
          <EmptyState icon={<ShoppingCart size={28} strokeWidth={1.5} />} title="Cart is empty" hint="Scan a barcode or search (F2) to add the first line." />
        ) : (
          lines.map((line) => (
            <CartLineRow
              key={line.lineRef}
              line={line}
              selected={selectedLineRef === line.lineRef}
              quoteFresh={isFresh || (!!quote && !expired && !isQuoting)}
              canDiscount={canDiscount}
              showCost={showCost}
              disabled={disabled}
              onSelect={() => selectLine(line.lineRef)}
              onRemove={() => setRemoving(line.lineRef)}
              onQtyEnter={() => (paymentEnabled ? onOpenPayment() : undefined)}
            />
          ))
        )}
      </div>

      <div className="mx-4 my-2 ui-card px-4 py-3">
        <div className="grid grid-cols-[1fr_auto] gap-x-6 gap-y-1 text-[12.5px] tabular">
          <span className="text-[var(--text-muted)]">Subtotal</span>
          <MoneyCell value={quote && !expired ? quote.totals.subtotal : estimateTotal} muted={!quote} />
          <span className="text-[var(--text-muted)] flex items-center gap-2">
            Discount
            {canDiscount && !headerOpen && lines.length > 0 && (
              <button type="button" className="text-[10.5px] text-[var(--color-navy)] hover:underline" onClick={() => setHeaderOpen(true)}>
                + header discount
              </button>
            )}
          </span>
          <MoneyCell value={quote && !expired ? `-${quote.totals.discount}` : '0'} />
          {headerOpen && canDiscount && (
            <div className="col-span-2 flex items-center gap-2 py-1">
              <span className="text-[10.5px] text-[var(--text-muted)]">Header discount KES</span>
              <input
                type="text"
                inputMode="decimal"
                value={headerDiscount}
                disabled={disabled}
                onChange={(e) => setHeaderDiscount(e.target.value.replace(/[^\d.]/g, ''), headerDiscountReason)}
                className="ui-input h-7 w-24 tabular text-right"
              />
              <input
                type="text"
                placeholder="Reason"
                value={headerDiscountReason}
                disabled={disabled}
                onChange={(e) => setHeaderDiscount(headerDiscount, e.target.value)}
                className="ui-input h-7 flex-1"
              />
            </div>
          )}
          <span className="text-[var(--text-muted)]">VAT</span>
          <MoneyCell value={quote && !expired ? quote.totals.tax : '0'} muted={!quote} />
          <div className="col-span-2 border-t-2 border-double border-[var(--border-strong)] my-1" />
          <span className="text-[15px] font-extrabold">TOTAL</span>
          <MoneyCell value={quote && !expired ? quote.totals.grand_total : estimateTotal} className={`text-[18px] font-extrabold ${quote && !expired ? '' : 'opacity-50 italic'}`} />
          {showCost && quote && !expired && quote.totals.margin_pct !== null && (
            <div className="col-span-2 text-[11px] text-[var(--text-muted)]">
              Margin {formatPct(quote.totals.margin_pct)} · Profit {formatMoney(quote.totals.gross_profit)}
            </div>
          )}
          <div className="col-span-2 text-[10.5px] text-[var(--text-muted)] min-h-[14px]">
            {isQuoting ? 'Pricing with the server…' : !quote ? (lines.length ? (needsCustomer ? 'Select a customer to price a wholesale cart.' : 'Estimates only until the server quote lands.') : '') : expired ? 'Quote expired — re-quote to continue.' : isFresh ? `Server quote ${quote.quote_id?.slice(0, 8)} · valid ${secondsLeft !== null ? `${Math.floor(secondsLeft / 60)}:${String(secondsLeft % 60).padStart(2, '0')}` : ''}` : 'Cart changed — re-pricing…'}
          </div>
        </div>

        <div className="flex items-center gap-2 mt-3">
          <Button variant="primary" size="lg" className="flex-1" disabled={!paymentEnabled} onClick={onOpenPayment} title={paymentEnabled ? undefined : 'Payment opens once every line carries a fresh server quote'}>
            <Kbd>F10</Kbd> PAYMENT
          </Button>
          <Button size="lg" onClick={onHold} disabled={lines.length === 0 || disabled}>
            <Kbd>F8</Kbd> Hold
          </Button>
        </div>
      </div>

      <ConfirmDialog
        open={removing !== null}
        title="Remove line?"
        message={removing ? lines.find((l) => l.lineRef === removing)?.productName : undefined}
        confirmLabel="Remove"
        danger
        onCancel={() => setRemoving(null)}
        onConfirm={() => {
          if (removing) removeLine(removing)
          setRemoving(null)
        }}
      />
    </div>
  )
}
