import { useEffect, useRef, useState } from 'react'
import { Search, ShoppingCart } from 'lucide-react'
import { KeyboardHintBar } from '../../components/KeyboardHintBar'
import { ConfirmDialog } from '../../components/ui/Modal'
import { EmptyState, LoadingSkeleton } from '../../components/ui/States'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { useStores } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { CartPanel } from './CartPanel'
import { ModeBanner } from './ModeBanner'
import { PaymentPanel } from './PaymentPanel'
import { PosHeldCartsDrawer } from './PosHeldCartsDrawer'
import { PosHoldCartModal } from './PosHoldCartModal'
import { PosPriceChangeModal } from './PosPriceChangeModal'
import { POS_SHORTCUTS, PosShortcutsModal } from './PosShortcutsModal'
import { ReceiptView } from './ReceiptView'
import { SearchPanel } from './SearchPanel'
import { useCartStore } from './cartStore'
import { useCheckout } from './useCheckout'
import { useQuote } from './useQuote'

function isTyping(target: EventTarget | null) {
  const el = target as HTMLElement | null
  return !!el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT' || el.isContentEditable)
}

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

  const [mobileTab, setMobileTab] = useState<'catalog' | 'cart'>('cart')

  const quoteState = useQuote()
  const checkout = useCheckout()
  const [paymentOpen, setPaymentOpen] = useState(false)
  const [helpOpen, setHelpOpen] = useState(false)
  const [holdOpen, setHoldOpen] = useState(false)
  const [holdName, setHoldName] = useState('')
  const [resumeOpen, setResumeOpen] = useState(false)
  const [removeRef, setRemoveRef] = useState<string | null>(null)

  // The till only ever works in a sellable store; a remembered store that has
  // since been marked not sellable is replaced rather than left to fail.
  useEffect(() => {
    if (!stores.data?.length) return
    const current = stores.data.find((s) => s.id === storeId)
    if (!current || !current.is_sellable) {
      const sellable = stores.data.find((s) => s.is_sellable)
      if (sellable && sellable.id !== storeId) setStore(sellable.id)
    }
  }, [storeId, stores.data, setStore])

  const defaultMode = user?.default_sale_mode ?? null
  const enabledModes = user?.sale_modes ?? []
  useEffect(() => {
    if (defaultMode && !enabledModes.includes(saleMode)) setSaleMode(defaultMode)
  }, [defaultMode, enabledModes, saleMode, setSaleMode])

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
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  })

  if (stores.isLoading) return <LoadingSkeleton rows={8} />
  if (stores.data && stores.data.length === 0) return <EmptyState title="No stores in this branch" hint="A store must exist before anything can be sold." />
  if (enabledModes.length === 0 && user) return <EmptyState title="Commerce is disabled for this branch" hint="Neither retail nor wholesale mode is enabled." />

  return (
    <div className="flex flex-col h-[calc(100dvh-4rem)] overflow-hidden">
      <ModeBanner stores={stores.data ?? []} />

      {/* Mobile Tab Bar (only visible on mobile/small tablets) */}
      <div className="md:hidden flex border-b border-slate-200 bg-white shrink-0">
        <button
          type="button"
          onClick={() => setMobileTab('catalog')}
          className={`flex-1 py-2.5 text-xs font-semibold border-b-2 transition-colors flex items-center justify-center gap-1.5 cursor-pointer ${
            mobileTab === 'catalog'
              ? 'border-blue-600 text-blue-600 bg-blue-50/50'
              : 'border-transparent text-slate-600 hover:text-slate-900'
          }`}
        >
          <Search size={14} />
          <span>Catalog & Search</span>
        </button>
        <button
          type="button"
          onClick={() => setMobileTab('cart')}
          className={`flex-1 py-2.5 text-xs font-semibold border-b-2 transition-colors flex items-center justify-center gap-1.5 cursor-pointer ${
            mobileTab === 'cart'
              ? 'border-blue-600 text-blue-600 bg-blue-50/50'
              : 'border-transparent text-slate-600 hover:text-slate-900'
          }`}
        >
          <ShoppingCart size={14} />
          <span>Cart</span>
          {lines.length > 0 && (
            <span className="px-1.5 py-0.5 rounded-full bg-blue-600 text-white text-xs tabular font-semibold">
              {lines.length}
            </span>
          )}
        </button>
      </div>

      {/* Main Work Area: 2-column on desktop, tabbed on mobile */}
      <div className="flex flex-1 min-h-0 overflow-hidden">
        <div className={`${mobileTab === 'catalog' ? 'flex' : 'hidden'} md:flex flex-col shrink-0 min-h-0 w-full md:w-auto`}>
          <SearchPanel
            inputRef={searchRef}
            onAdded={() => {
              searchRef.current?.focus()
              if (window.innerWidth < 768) setMobileTab('cart')
            }}
          />
        </div>
        <div className={`${mobileTab === 'cart' ? 'flex' : 'hidden'} md:flex flex-1 min-w-0 flex-col min-h-0`}>
          <CartPanel
            quoteState={quoteState}
            customerInputRef={customerRef}
            onOpenPayment={openPayment}
            onHold={() => setHoldOpen(true)}
            onApprove={openPayment}
            approvePending={checkout.isPending}
          />
        </div>
      </div>

      {/* Payment Drawer (Slide-Over Sheet with backdrop - Never squashes CartPanel) */}
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

      <KeyboardHintBar
        hints={POS_SHORTCUTS.map((s) => ({ ...s, disabled: s.key === 'F6' && !canDiscount }))}
        onOpenHelp={() => setHelpOpen(true)}
      />

      {postedSale && <ReceiptView onNewSale={newSale} />}

      <PosPriceChangeModal
        priceChange={priceChange}
        onClose={() => setPriceChange(null)}
        onAccept={acceptPriceChange}
      />

      <PosHoldCartModal
        open={holdOpen}
        onClose={() => setHoldOpen(false)}
        holdName={holdName}
        onChangeName={setHoldName}
        onConfirmHold={() => {
          hold(holdName.trim())
          setHoldName('')
          setHoldOpen(false)
          setPaymentOpen(false)
          searchRef.current?.focus()
        }}
      />

      <PosHeldCartsDrawer
        open={resumeOpen}
        onClose={() => setResumeOpen(false)}
        heldCarts={heldCarts}
        onDiscard={discardHeld}
        onResume={(id) => {
          resume(id)
          setResumeOpen(false)
          setPaymentOpen(false)
        }}
      />

      <PosShortcutsModal
        open={helpOpen}
        onClose={() => setHelpOpen(false)}
        canDiscount={canDiscount}
      />

      <ConfirmDialog
        open={removeRef !== null}
        title="Remove item from cart?"
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
