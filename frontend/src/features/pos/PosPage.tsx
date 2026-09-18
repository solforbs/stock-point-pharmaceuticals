import { useEffect, useRef, useState } from 'react'
import { KeyboardHintBar } from '../../components/KeyboardHintBar'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog, Modal } from '../../components/ui/Modal'
import { EmptyState, LoadingSkeleton } from '../../components/ui/States'
import { Button, Field, Input, Kbd } from '../../components/ui/primitives'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { formatDateTime } from '../../lib/format'
import { useStores } from '../../lib/hooks'
import { formatMoney } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { CartPanel } from './CartPanel'
import { ModeBanner } from './ModeBanner'
import { PaymentPanel } from './PaymentPanel'
import { ReceiptView } from './ReceiptView'
import { SearchPanel } from './SearchPanel'
import { useCartStore } from './cartStore'
import { useCheckout } from './useCheckout'
import { useQuote } from './useQuote'

const SHORTCUTS = [
  { key: 'F2', label: 'Scan / search' },
  { key: 'F3', label: 'Quantity' },
  { key: 'F4', label: 'UOM' },
  { key: 'F5', label: 'Customer' },
  { key: 'F6', label: 'Line discount' },
  { key: 'F8', label: 'Hold' },
  { key: 'F9', label: 'Resume' },
  { key: 'F10', label: 'Payment' },
  { key: 'Enter', label: 'Confirm / add' },
  { key: 'Esc', label: 'Cancel field' },
  { key: 'Ctrl+Del', label: 'Remove line' },
  { key: '?', label: 'Help' },
]

function isTyping(target: EventTarget | null) {
  const el = target as HTMLElement | null
  return !!el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT' || el.isContentEditable)
}

/** Part 24 — the POS screen in full. Keyboard-first (Part 16.4). */
export default function PosPage() {
  const { data: user } = useCurrentUser()
  const canDiscount = usePermission('sale.discount.apply')
  const stores = useStores()
  const searchRef = useRef<HTMLInputElement>(null)
  const customerRef = useRef<HTMLInputElement>(null)

  const storeId = useCartStore((s) => s.storeId)
  const saleMode = useCartStore((s) => s.saleMode)
  const status = useCartStore((s) => s.status)
  const lines = useCartStore((s) => s.lines)
  const selectedLineRef = useCartStore((s) => s.selectedLineRef)
  const priceChange = useCartStore((s) => s.priceChange)
  const heldCarts = useCartStore((s) => s.heldCarts)
  const postedSale = useCartStore((s) => s.postedSale)
  const setStore = useCartStore((s) => s.setStore)
  const setSaleMode = useCartStore((s) => s.setSaleMode)
  const setStatus = useCartStore((s) => s.setStatus)
  const removeLine = useCartStore((s) => s.removeLine)
  const hold = useCartStore((s) => s.hold)
  const resume = useCartStore((s) => s.resume)
  const discardHeld = useCartStore((s) => s.discardHeld)
  const acceptPriceChange = useCartStore((s) => s.acceptPriceChange)
  const setPriceChange = useCartStore((s) => s.setPriceChange)
  const reset = useCartStore((s) => s.reset)

  const quoteState = useQuote()
  const checkout = useCheckout()
  const [paymentOpen, setPaymentOpen] = useState(false)
  const [helpOpen, setHelpOpen] = useState(false)
  const [holdOpen, setHoldOpen] = useState(false)
  const [holdName, setHoldName] = useState('')
  const [resumeOpen, setResumeOpen] = useState(false)
  const [removeRef, setRemoveRef] = useState<string | null>(null)

  // Default to the branch's first sellable store; the mode the terminal opens in.
  useEffect(() => {
    if (!storeId && stores.data?.length) {
      const sellable = stores.data.find((s) => s.is_sellable) ?? stores.data[0]
      setStore(sellable.id)
    }
  }, [storeId, stores.data, setStore])

  const defaultMode = user?.default_sale_mode ?? null
  const enabledModes = user?.sale_modes ?? []
  useEffect(() => {
    if (defaultMode && !enabledModes.includes(saleMode)) setSaleMode(defaultMode)
  }, [defaultMode, enabledModes, saleMode, setSaleMode])

  // Payment stays open while paying; it closes once the quote goes stale so
  // it can never post against a cart that has changed since it was priced.
  useEffect(() => {
    if (paymentOpen && status === 'BUILDING' && !quoteState.isFresh && !quoteState.isQuoting) setPaymentOpen(false)
  }, [paymentOpen, status, quoteState.isFresh, quoteState.isQuoting])

  function openPayment() {
    if (!quoteState.isFresh || lines.length === 0 || status === 'POSTED') return
    setStatus(useCartStore.getState().quote?.approval_required ? 'AWAITING_APPROVAL' : 'PAYING')
    setPaymentOpen(true)
  }

  function focusSelected(attr: 'data-qty-for' | 'data-uom-for' | 'data-discount-for' | 'data-discount-toggle') {
    const ref = selectedLineRef ?? lines[lines.length - 1]?.lineRef
    if (!ref) return
    const el = document.querySelector<HTMLElement>(`[${attr}="${ref}"]`)
    if (el) {
      el.focus()
      if (attr === 'data-discount-toggle') el.click()
      return
    }
    if (attr === 'data-discount-for') {
      document.querySelector<HTMLElement>(`[data-discount-toggle="${ref}"]`)?.click()
      window.setTimeout(() => document.querySelector<HTMLElement>(`[data-discount-for="${ref}"]`)?.focus(), 0)
    }
  }

  function newSale() {
    reset()
    setPaymentOpen(false)
    window.setTimeout(() => searchRef.current?.focus(), 0)
  }

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (postedSale && e.key === 'Enter') {
        e.preventDefault()
        newSale()
        return
      }
      switch (e.key) {
        case 'F2':
          e.preventDefault()
          searchRef.current?.focus()
          searchRef.current?.select()
          break
        case 'F3':
          e.preventDefault()
          focusSelected('data-qty-for')
          break
        case 'F4':
          e.preventDefault()
          focusSelected('data-uom-for')
          break
        case 'F5':
          e.preventDefault()
          customerRef.current?.focus()
          break
        case 'F6':
          e.preventDefault()
          if (canDiscount) focusSelected('data-discount-for')
          break
        case 'F8':
          e.preventDefault()
          if (lines.length) setHoldOpen(true)
          break
        case 'F9':
          e.preventDefault()
          setResumeOpen(true)
          break
        case 'F10':
          e.preventDefault()
          openPayment()
          break
        case 'Escape':
          if (helpOpen) setHelpOpen(false)
          else if (paymentOpen) {
            setPaymentOpen(false)
            setStatus('BUILDING')
          } else if (isTyping(e.target)) (e.target as HTMLElement).blur()
          break
        case 'Delete':
          if (e.ctrlKey && selectedLineRef) {
            e.preventDefault()
            setRemoveRef(selectedLineRef)
          }
          break
        case '?':
          if (!isTyping(e.target)) {
            e.preventDefault()
            setHelpOpen((v) => !v)
          }
          break
        default:
          break
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  })

  if (stores.isLoading) return <LoadingSkeleton rows={8} />
  if (stores.data && stores.data.length === 0) return <EmptyState title="No stores in this branch" hint="A store must exist before anything can be sold." />
  if (enabledModes.length === 0 && user) return <EmptyState title="Commerce is disabled for this branch" hint="Neither retail nor wholesale mode is enabled (V6 Part 10.1)." />

  return (
    <div className="flex flex-col h-svh">
      <ModeBanner stores={stores.data ?? []} />

      <div className="flex flex-1 min-h-0">
        <SearchPanel inputRef={searchRef} onAdded={() => searchRef.current?.focus()} />
        <CartPanel
          quoteState={quoteState}
          customerInputRef={customerRef}
          onOpenPayment={openPayment}
          onHold={() => setHoldOpen(true)}
          onApprove={() => {
            openPayment()
          }}
          approvePending={checkout.isPending}
        />
        <PaymentPanel
          open={paymentOpen && status !== 'POSTED'}
          onClose={() => {
            setPaymentOpen(false)
            setStatus('BUILDING')
          }}
          onPost={(approve) => checkout.mutate({ approve })}
          isPosting={checkout.isPending}
          quoteFresh={quoteState.isFresh}
        />
      </div>

      <KeyboardHintBar hints={SHORTCUTS.map((s) => ({ ...s, disabled: s.key === 'F6' && !canDiscount }))} />

      {postedSale && <ReceiptView onNewSale={newSale} />}

      <Modal
        open={priceChange !== null}
        onClose={() => setPriceChange(null)}
        title="Prices changed since this cart was quoted"
        footer={
          <>
            <Button onClick={() => setPriceChange(null)}>Review cart</Button>
            <Button variant="primary" onClick={acceptPriceChange}>
              Accept new prices
            </Button>
          </>
        }
      >
        {priceChange && (
          <div className="space-y-3">
            <div className="grid grid-cols-2 gap-3 tabular">
              <div className="ui-card p-3">
                <div className="ui-label">Quoted total</div>
                <div className="text-[18px] font-bold line-through text-[var(--text-muted)]">{formatMoney(priceChange.old_total)}</div>
              </div>
              <div className="ui-card p-3">
                <div className="ui-label">New total</div>
                <div className="text-[18px] font-extrabold">{formatMoney(priceChange.new_total)}</div>
              </div>
            </div>
            {priceChange.changed_lines.length > 0 && (
              <table className="ui-table">
                <thead>
                  <tr>
                    <th>Line</th>
                    <th>Field</th>
                    <th className="text-right">Was</th>
                    <th className="text-right">Now</th>
                  </tr>
                </thead>
                <tbody>
                  {priceChange.changed_lines.map((c, i) => (
                    <tr key={i}>
                      <td>{c.line_ref}</td>
                      <td>{c.field}</td>
                      <td className="text-right tabular">{c.old}</td>
                      <td className="text-right tabular">{c.new ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
            <p className="text-[var(--text-secondary)]">Accepting loads the server's fresh quote; you will re-enter the tender against the new total.</p>
          </div>
        )}
      </Modal>

      <Modal
        open={holdOpen}
        onClose={() => setHoldOpen(false)}
        title="Hold this cart"
        footer={
          <>
            <Button onClick={() => setHoldOpen(false)}>Cancel</Button>
            <Button
              variant="primary"
              onClick={() => {
                hold(holdName.trim())
                setHoldName('')
                setHoldOpen(false)
                setPaymentOpen(false)
                searchRef.current?.focus()
              }}
            >
              Hold
            </Button>
          </>
        }
      >
        <Field label="Name (optional)" hint="Held carts live on this terminal and can be resumed with F9 — by a manager too, for approvals.">
          <Input
            autoFocus
            value={holdName}
            onChange={(e) => setHoldName(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                hold(holdName.trim())
                setHoldName('')
                setHoldOpen(false)
                setPaymentOpen(false)
              }
            }}
          />
        </Field>
      </Modal>

      <Drawer open={resumeOpen} onClose={() => setResumeOpen(false)} title="Held carts" subtitle="Resume replaces the current cart; hold it first if it matters.">
        {heldCarts.length === 0 ? (
          <EmptyState title="No held carts on this terminal" />
        ) : (
          <div className="space-y-2">
            {heldCarts.map((h) => (
              <div key={h.id} className="ui-card p-3 flex items-center gap-3">
                <div className="flex-1 min-w-0">
                  <div className="text-[13px] font-semibold truncate">{h.name}</div>
                  <div className="text-[11px] text-[var(--text-muted)]">
                    {h.saleMode} · {h.lines.length} lines · {h.customer?.name ?? 'walk-in'} · {formatDateTime(h.heldAt)}
                  </div>
                </div>
                <Button size="sm" variant="ghost" onClick={() => discardHeld(h.id)}>
                  Discard
                </Button>
                <Button
                  size="sm"
                  variant="primary"
                  onClick={() => {
                    resume(h.id)
                    setResumeOpen(false)
                    setPaymentOpen(false)
                  }}
                >
                  Resume
                </Button>
              </div>
            ))}
          </div>
        )}
      </Drawer>

      <Modal open={helpOpen} onClose={() => setHelpOpen(false)} title="Keyboard shortcuts" width={420}>
        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5">
          {SHORTCUTS.map((s) => (
            <div key={s.key} className="contents">
              <dt>
                <Kbd>{s.key}</Kbd>
              </dt>
              <dd>{s.label}</dd>
            </div>
          ))}
        </dl>
        <p className="text-[11px] text-[var(--text-muted)] mt-3">Retail fast path: scan · scan · scan · F10 · type cash tendered · Enter.</p>
      </Modal>

      <ConfirmDialog
        open={removeRef !== null}
        title="Remove line?"
        message={removeRef ? lines.find((l) => l.lineRef === removeRef)?.productName : undefined}
        confirmLabel="Remove"
        danger
        onCancel={() => setRemoveRef(null)}
        onConfirm={() => {
          if (removeRef) removeLine(removeRef)
          setRemoveRef(null)
        }}
      />
    </div>
  )
}
