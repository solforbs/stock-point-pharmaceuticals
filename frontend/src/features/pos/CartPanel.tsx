import { AlertTriangle, RefreshCw, ShoppingBag, User } from 'lucide-react'
import { useState, type RefObject } from 'react'
import { ApprovalBar } from '../../components/ApprovalBar'
import { CustomerPicker } from '../../components/CustomerPicker'
import { ConfirmDialog } from '../../components/ui/Modal'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { dSum } from '../../lib/decimal'
import { formatMoney } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import type { SaleMode } from '../../lib/types'
import { CartLineRow } from './CartLineRow'
import { CartTotalsCard } from './CartTotalsCard'
import { useCartStore } from './cartStore'

export interface CartPanelProps {
  quoteState: {
    isQuoting: boolean
    isFresh: boolean
    expired: boolean
    secondsLeft: number | null
    needsCustomer: boolean
    requote: () => void
  }
  customerInputRef: RefObject<HTMLInputElement | null>
  onOpenPayment: () => void
  onHold: () => void
  onPreviewCart?: () => void
  onApprove: () => void
  approvePending: boolean
}

export function CartPanel({
  quoteState,
  customerInputRef,
  onOpenPayment,
  onHold,
  onPreviewCart,
  onApprove,
  approvePending,
}: CartPanelProps) {
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
  const fallbackMode =
    ((quoteError?.details.default_mode as SaleMode | undefined) ??
      currentUser?.default_sale_mode ??
      (quoteError?.details.enabled_modes as SaleMode[] | undefined)?.[0]) ??
    null

  const [removing, setRemoving] = useState<string | null>(null)

  const disabled = status === 'POSTING' || status === 'POSTED'
  const { isFresh, isQuoting, expired, secondsLeft, needsCustomer, requote } = quoteState
  const estimateTotal = dSum(lines.map((l) => l.localEstimate))
  const flaggedCount = quote?.lines.filter((l) => l.approval_required).length ?? 0
  const paymentEnabled = isFresh && !disabled && lines.length > 0

  return (
    <div className="flex-1 min-w-0 flex flex-col bg-slate-50/50 border-l border-slate-200/80">
      {/* Customer Header */}
      <div id="tour-pos-customer" className="px-3.5 py-2 bg-white border-b border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.02)] shrink-0">
        <div className="flex items-center justify-between mb-1">
          <div className="flex items-center gap-2">
            <span className="inline-flex items-center gap-1.5 text-xs font-bold text-slate-700 uppercase tracking-wider">
              <User size={13} className="text-blue-600" />
              Customer
            </span>
            <span className="hidden md:inline-flex px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-mono font-bold">
              F5
            </span>
            {saleMode === 'WHOLESALE' ? (
              <span className="px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200 text-xs font-bold">
                Required
              </span>
            ) : (
              <span className="text-xs text-slate-500 font-normal">Walk-in default</span>
            )}
          </div>
          <div className="flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 text-xs font-bold tabular">
            <span>{lines.length}</span>
            <span className="text-slate-400 font-medium">{lines.length === 1 ? 'item' : 'items'}</span>
          </div>
        </div>
        <CustomerPicker
          value={customer}
          onChange={setCustomer}
          inputRef={customerInputRef}
          required={needsCustomer}
          disabled={disabled}
        />
      </div>

      {/* Notifications & Warnings */}
      {modeSwitchNote && quote && modeSwitchNote.beforeTotal !== null && (
        <div className="mx-3 mt-2 px-3 py-2 rounded-2xl border border-blue-200/80 bg-blue-50 text-xs text-blue-900 flex items-center gap-3">
          <span className="flex-1 tabular">
            Re-quoted after mode switch ({modeSwitchNote.from} → {modeSwitchNote.to}): was <b>{formatMoney(modeSwitchNote.beforeTotal)}</b>, now <b>{formatMoney(quote.totals.grand_total)}</b>
          </span>
          <button
            type="button"
            onClick={dismissModeSwitchNote}
            className="px-3 py-1 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-800 font-bold text-xs cursor-pointer shadow-2xs"
          >
            OK
          </button>
        </div>
      )}

      {expired && lines.length > 0 && (
        <div className="mx-3 mt-2 px-3.5 py-2.5 rounded-2xl border border-amber-200 bg-amber-50 text-xs text-amber-900 flex items-center gap-2.5">
          <AlertTriangle size={15} className="text-amber-600 shrink-0" />
          <span className="flex-1">Quote expired. Prices must be re-confirmed before checkout.</span>
          <button
            type="button"
            onClick={requote}
            disabled={isQuoting}
            className="px-3 py-1 rounded-full bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs flex items-center gap-1 cursor-pointer shadow-2xs"
          >
            <RefreshCw size={11} className={isQuoting ? 'animate-spin' : ''} /> Re-quote
          </button>
        </div>
      )}

      {quoteError && (
        <div className="mx-3 mt-2 px-3.5 py-2.5 rounded-2xl border border-rose-200 bg-rose-50 text-xs text-rose-900 flex items-center gap-2.5">
          <span className="flex-1">
            <b className="mr-1">{quoteError.code}:</b>
            {quoteError.message}
          </span>
          {(quoteError.code === 'MODE_SWITCH_FORBIDDEN' || quoteError.code === 'MODE_DISABLED') && fallbackMode ? (
            <button
              type="button"
              onClick={() => setSaleMode(fallbackMode)}
              className="px-3 py-1 rounded-full bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs cursor-pointer shadow-2xs"
            >
              Switch to {fallbackMode}
            </button>
          ) : (
            <button
              type="button"
              onClick={requote}
              disabled={isQuoting}
              className="px-3 py-1 rounded-full bg-rose-100 hover:bg-rose-200 text-rose-800 font-bold text-xs cursor-pointer"
            >
              Retry
            </button>
          )}
        </div>
      )}

      {(flaggedCount > 0 || status === 'AWAITING_APPROVAL') && !disabled && (
        <div className="mx-3 mt-2">
          <ApprovalBar
            title={status === 'AWAITING_APPROVAL' ? 'Approval required before sale can post' : `${flaggedCount} line(s) need manager approval`}
            message={
              status === 'AWAITING_APPROVAL'
                ? `Sale held (lines ${approvalLines.join(', ') || '—'}). An authorized approver can sign, or hold the cart (F8).`
                : 'Applied discounts exceed authority threshold. Approver sign-off needed.'
            }
            canApprove={canApprove && isFresh}
            approveLabel="Approve & Post"
            onApprove={onApprove}
            isPending={approvePending}
          />
        </div>
      )}

      {/* Cart Lines Scroll Area */}
      <div id="tour-pos-cart-lines" className="flex-1 min-h-0 overflow-y-auto pos-scroll p-3 space-y-2">
        {lines.length === 0 ? (
          <div className="h-full min-h-[220px] flex flex-col items-center justify-center text-center p-6 bg-white/70 border border-dashed border-slate-200 rounded-2xl">
            <div className="w-13 h-13 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3 shadow-inner ring-4 ring-blue-50/50">
              <ShoppingBag size={24} />
            </div>
            <h4 className="text-sm font-bold text-slate-800 mb-1">Your cart is empty</h4>
            <p className="text-xs text-slate-500 max-w-[240px] mb-3">
              Scan a barcode, press <kbd className="px-2 py-0.5 rounded-lg bg-slate-100 font-mono text-xs font-bold text-slate-700">F2</kbd> to search, or tap any fast-moving medicine.
            </p>
          </div>
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

      {/* Financial Breakdown & Action Buttons */}
      <CartTotalsCard
        quote={quote}
        estimateTotal={estimateTotal}
        expired={expired}
        isQuoting={isQuoting}
        secondsLeft={secondsLeft}
        needsCustomer={needsCustomer}
        linesCount={lines.length}
        canDiscount={canDiscount}
        showCost={showCost}
        disabled={disabled}
        paymentEnabled={paymentEnabled}
        headerDiscount={headerDiscount}
        headerDiscountReason={headerDiscountReason}
        onSetHeaderDiscount={setHeaderDiscount}
        onOpenPayment={onOpenPayment}
        onHold={onHold}
        onPreviewCart={onPreviewCart}
        onFocusCustomer={() => customerInputRef.current?.focus()}
        onSwitchToRetail={() => setSaleMode('RETAIL')}
      />

      <ConfirmDialog
        open={removing !== null}
        title="Remove item from cart?"
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
