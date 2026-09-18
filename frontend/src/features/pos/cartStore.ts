import { create } from 'zustand'
import { newIdempotencyKey } from '../../lib/api'
import type { NormalisedApiError } from '../../lib/apiError'
import { dAdd, dMul, dMulInt, isValidDecimal } from '../../lib/decimal'
import type { Customer, Product, ProductUom, Quote, QuoteLine, Sale, SaleMode, TenderLine } from '../../lib/types'
import { defaultSalesUom } from '../../components/ProductSearch'

/**
 * Part 16.3 — the POS state model. Optimistic for display only (line added,
 * quantity changed, cached price shown); never optimistic for money that
 * posts. The server quote replaces every estimate before payment opens.
 */
export type CartLine = {
  lineRef: string
  productId: string
  productCode: string
  productName: string
  strength: string | null
  isDiscrete: boolean
  baseUomCode: string
  uoms: ProductUom[]
  uomId: string
  uomCode: string
  factorToBase: number
  qty: string
  qtyBase: string
  estimateUnitPrice: string | null
  localEstimate: string
  quoted?: QuoteLine
  requestedDiscountPct?: string
  discountReason?: string
}

export type CartStatus = 'BUILDING' | 'AWAITING_APPROVAL' | 'PAYING' | 'POSTING' | 'POSTED'

export type PriceChange = {
  old_total: string
  new_total: string
  changed_lines: { line_ref: string; field: string; old: string; new: string | null }[]
  new_quote: Quote
}

export type HeldCart = {
  id: string
  name: string
  heldAt: string
  saleMode: SaleMode
  customer: Customer | null
  headerDiscount: string
  headerDiscountReason: string
  lines: CartLine[]
}

export type ModeSwitchNote = { from: SaleMode; to: SaleMode; beforeTotal: string | null }

type CartState = {
  saleMode: SaleMode
  storeId: string | null
  terminalId: string
  customer: Customer | null
  lines: CartLine[]
  headerDiscount: string
  headerDiscountReason: string
  quote: Quote | null
  quoteSignature: string | null
  quoteError: NormalisedApiError | null
  quoteErrorSignature: string | null
  quoteExpired: boolean
  payments: TenderLine[]
  status: CartStatus
  selectedLineRef: string | null
  checkoutKey: string | null
  checkoutError: NormalisedApiError | null
  priceChange: PriceChange | null
  approvalLines: string[]
  postedSale: Sale | null
  postedPayments: TenderLine[]
  cashTendered: string
  heldCarts: HeldCart[]
  modeSwitchNote: ModeSwitchNote | null
  lineCounter: number

  setSaleMode: (mode: SaleMode) => void
  setStore: (storeId: string) => void
  setTerminal: (terminalId: string) => void
  setCustomer: (customer: Customer | null) => void
  addProduct: (product: Product, uomId?: string) => string | null
  setQty: (lineRef: string, qty: string) => void
  setUom: (lineRef: string, uomId: string) => void
  setLineDiscount: (lineRef: string, pct: string, reason: string) => void
  removeLine: (lineRef: string) => void
  selectLine: (lineRef: string | null) => void
  setHeaderDiscount: (amount: string, reason: string) => void
  setQuote: (quote: Quote, signature: string) => void
  setQuoteError: (error: NormalisedApiError, signature: string) => void
  clearQuoteError: () => void
  clearQuote: () => void
  expireQuote: () => void
  setPayments: (payments: TenderLine[]) => void
  setCashTendered: (value: string) => void
  setStatus: (status: CartStatus) => void
  setCheckoutError: (error: NormalisedApiError | null) => void
  setPriceChange: (change: PriceChange | null) => void
  acceptPriceChange: () => void
  setApprovalLines: (lines: string[]) => void
  setPostedSale: (sale: Sale) => void
  hold: (name: string) => void
  resume: (id: string) => void
  discardHeld: (id: string) => void
  dismissModeSwitchNote: () => void
  reset: () => void
}

const HELD_KEY = 'pos-held-carts'
const TERMINAL_KEY = 'pos-terminal-id'

function readHeld(): HeldCart[] {
  try {
    const raw = localStorage.getItem(HELD_KEY)
    return raw ? (JSON.parse(raw) as HeldCart[]) : []
  } catch {
    return []
  }
}

function persistHeld(carts: HeldCart[]) {
  try {
    localStorage.setItem(HELD_KEY, JSON.stringify(carts))
  } catch {
    // per-terminal convenience only
  }
}

function readTerminal(): string {
  try {
    return localStorage.getItem(TERMINAL_KEY) || 'T-01'
  } catch {
    return 'T-01'
  }
}

function estimate(qty: string, unitPrice: string | null): string {
  if (!unitPrice || !isValidDecimal(qty)) return '0.0000'
  return dMul(qty, unitPrice)
}

function recompute(line: CartLine): CartLine {
  const qtyBase = isValidDecimal(line.qty) ? dMulInt(line.qty, line.factorToBase) : '0.0000'
  return { ...line, qtyBase, localEstimate: estimate(line.qty, line.estimateUnitPrice) }
}

/** The cart facts a quote depends on; a quote is fresh only while this matches. */
export function cartSignature(s: Pick<CartState, 'saleMode' | 'storeId' | 'customer' | 'headerDiscount' | 'headerDiscountReason' | 'lines'>): string {
  return JSON.stringify({
    m: s.saleMode,
    st: s.storeId,
    c: s.customer?.id ?? null,
    hd: s.headerDiscount,
    hr: s.headerDiscountReason,
    l: s.lines.map((l) => [l.lineRef, l.productId, l.uomId, l.qty, l.requestedDiscountPct ?? '', l.discountReason ?? '']),
  })
}

export function quotePayload(s: Pick<CartState, 'saleMode' | 'storeId' | 'customer' | 'headerDiscount' | 'headerDiscountReason' | 'lines'>) {
  return {
    sale_mode: s.saleMode,
    store_id: s.storeId,
    customer_id: s.customer?.id ?? null,
    header_discount: s.headerDiscount && isValidDecimal(s.headerDiscount) && Number(s.headerDiscount) > 0 ? s.headerDiscount : null,
    header_discount_reason: s.headerDiscountReason || null,
    lines: s.lines.map((l) => ({
      line_ref: l.lineRef,
      product_id: l.productId,
      uom_id: l.uomId,
      quantity: l.qty,
      requested_discount_pct: l.requestedDiscountPct && Number(l.requestedDiscountPct) > 0 ? l.requestedDiscountPct : null,
      requested_discount_reason: l.discountReason || null,
    })),
  }
}

const emptyCart = {
  customer: null,
  lines: [] as CartLine[],
  headerDiscount: '',
  headerDiscountReason: '',
  quote: null,
  quoteSignature: null,
  quoteError: null,
  quoteErrorSignature: null,
  quoteExpired: false,
  payments: [] as TenderLine[],
  status: 'BUILDING' as CartStatus,
  selectedLineRef: null,
  checkoutKey: null,
  checkoutError: null,
  priceChange: null,
  approvalLines: [] as string[],
  postedSale: null,
  postedPayments: [] as TenderLine[],
  cashTendered: '',
  modeSwitchNote: null,
}

export const useCartStore = create<CartState>((set, get) => ({
  saleMode: 'RETAIL',
  storeId: null,
  terminalId: readTerminal(),
  heldCarts: readHeld(),
  lineCounter: 0,
  ...emptyCart,

  setSaleMode: (mode) =>
    set((s) => {
      if (s.saleMode === mode) return {}
      const note: ModeSwitchNote | null = s.lines.length > 0 ? { from: s.saleMode, to: mode, beforeTotal: s.quote?.totals.grand_total ?? null } : null
      return { saleMode: mode, modeSwitchNote: note, checkoutError: null, status: s.status === 'POSTED' ? s.status : 'BUILDING' }
    }),
  setStore: (storeId) => set({ storeId }),
  setTerminal: (terminalId) => {
    try {
      localStorage.setItem(TERMINAL_KEY, terminalId)
    } catch {
      // ignore
    }
    set({ terminalId })
  },
  setCustomer: (customer) => set({ customer, checkoutError: null }),

  addProduct: (product, uomId) => {
    const uoms = (product.uoms ?? []).filter((u) => u.is_sales || u.is_base)
    const chosen = (uomId ? uoms.find((u) => u.uom_id === uomId) : undefined) ?? defaultSalesUom(product)
    if (!chosen) return null
    const state = get()
    const existing = state.lines.find((l) => l.productId === product.id && l.uomId === chosen.uom_id)
    if (existing) {
      const qty = isValidDecimal(existing.qty) ? dAdd(existing.qty, '1') : '1'
      set({
        lines: state.lines.map((l) => (l.lineRef === existing.lineRef ? recompute({ ...l, qty: qty.replace(/\.0000$/, '') }) : l)),
        selectedLineRef: existing.lineRef,
        status: 'BUILDING',
      })
      return existing.lineRef
    }
    const counter = state.lineCounter + 1
    const lineRef = `L${counter}`
    const line = recompute({
      lineRef,
      productId: product.id,
      productCode: product.code,
      productName: product.name,
      strength: product.strength,
      isDiscrete: product.is_discrete,
      baseUomCode: product.base_uom?.code ?? uoms.find((u) => u.is_base)?.uom?.code ?? 'unit',
      uoms,
      uomId: chosen.uom_id,
      uomCode: chosen.uom?.code ?? '',
      factorToBase: chosen.factor_to_base,
      qty: '1',
      qtyBase: '0',
      estimateUnitPrice: product.default_price ? dMulInt(product.default_price, chosen.factor_to_base) : null,
      localEstimate: '0.0000',
    })
    set({ lines: [...state.lines, line], lineCounter: counter, selectedLineRef: lineRef, status: 'BUILDING', checkoutError: null })
    return lineRef
  },

  setQty: (lineRef, qty) =>
    set((s) => ({ lines: s.lines.map((l) => (l.lineRef === lineRef ? recompute({ ...l, qty }) : l)), status: s.status === 'POSTED' ? s.status : 'BUILDING' })),

  setUom: (lineRef, uomId) =>
    set((s) => ({
      lines: s.lines.map((l) => {
        if (l.lineRef !== lineRef) return l
        const uom = l.uoms.find((u) => u.uom_id === uomId)
        if (!uom) return l
        const perBase = l.estimateUnitPrice ? dMul(l.estimateUnitPrice, (1 / l.factorToBase).toFixed(6)) : null
        return recompute({
          ...l,
          uomId: uom.uom_id,
          uomCode: uom.uom?.code ?? '',
          factorToBase: uom.factor_to_base,
          estimateUnitPrice: perBase ? dMulInt(perBase, uom.factor_to_base) : null,
        })
      }),
      status: s.status === 'POSTED' ? s.status : 'BUILDING',
    })),

  setLineDiscount: (lineRef, pct, reason) =>
    set((s) => ({
      lines: s.lines.map((l) => (l.lineRef === lineRef ? { ...l, requestedDiscountPct: pct, discountReason: reason } : l)),
      status: s.status === 'POSTED' ? s.status : 'BUILDING',
    })),

  removeLine: (lineRef) =>
    set((s) => {
      const lines = s.lines.filter((l) => l.lineRef !== lineRef)
      const selected = s.selectedLineRef === lineRef ? (lines[lines.length - 1]?.lineRef ?? null) : s.selectedLineRef
      return { lines, selectedLineRef: selected, status: lines.length ? 'BUILDING' : 'BUILDING', checkoutError: null }
    }),

  selectLine: (lineRef) => set({ selectedLineRef: lineRef }),
  setHeaderDiscount: (amount, reason) => set({ headerDiscount: amount, headerDiscountReason: reason }),

  setQuote: (quote, signature) =>
    set((s) => {
      const byRef = new Map(quote.lines.map((l) => [l.line_ref, l]))
      const lines = s.lines.map((l) => {
        const quoted = byRef.get(l.lineRef)
        return quoted ? recompute({ ...l, quoted, estimateUnitPrice: quoted.unit_price }) : l
      })
      const sameQuote = s.quote?.quote_id === quote.quote_id
      const note = s.modeSwitchNote && s.modeSwitchNote.beforeTotal !== null && quote.sale_mode === s.modeSwitchNote.to ? s.modeSwitchNote : s.modeSwitchNote?.beforeTotal === null ? null : s.modeSwitchNote
      return {
        lines,
        quote,
        quoteSignature: signature,
        quoteError: null,
        quoteErrorSignature: null,
        quoteExpired: false,
        checkoutKey: sameQuote && s.checkoutKey ? s.checkoutKey : newIdempotencyKey(),
        checkoutError: null,
        status: s.status === 'AWAITING_APPROVAL' && !quote.approval_required ? 'BUILDING' : s.status === 'POSTING' ? 'BUILDING' : s.status,
        modeSwitchNote: note,
      }
    }),

  setQuoteError: (error, signature) => set({ quoteError: error, quoteErrorSignature: signature, quote: null, quoteSignature: null }),
  clearQuoteError: () => set({ quoteError: null, quoteErrorSignature: null }),
  clearQuote: () => set({ quote: null, quoteSignature: null, quoteExpired: false }),
  expireQuote: () => set({ quoteExpired: true, quoteSignature: null }),

  setPayments: (payments) => set({ payments, checkoutError: null }),
  setCashTendered: (cashTendered) => set({ cashTendered }),
  setStatus: (status) => set({ status }),
  setCheckoutError: (checkoutError) => set({ checkoutError }),
  setPriceChange: (priceChange) => set({ priceChange }),

  acceptPriceChange: () => {
    const s = get()
    if (!s.priceChange) return
    const signature = cartSignature(s)
    s.setQuote(s.priceChange.new_quote, signature)
    set({ priceChange: null, checkoutKey: newIdempotencyKey(), status: 'PAYING' })
  },

  setApprovalLines: (approvalLines) => set({ approvalLines }),

  setPostedSale: (sale) => set((s) => ({ postedSale: sale, postedPayments: s.payments, status: 'POSTED' })),

  hold: (name) => {
    const s = get()
    if (s.lines.length === 0) return
    const held: HeldCart = {
      id: newIdempotencyKey(),
      name: name || `Held ${new Date().toLocaleTimeString()}`,
      heldAt: new Date().toISOString(),
      saleMode: s.saleMode,
      customer: s.customer,
      headerDiscount: s.headerDiscount,
      headerDiscountReason: s.headerDiscountReason,
      lines: s.lines.map((l) => ({ ...l, quoted: undefined })),
    }
    const heldCarts = [held, ...s.heldCarts]
    persistHeld(heldCarts)
    set({ ...emptyCart, heldCarts })
  },

  resume: (id) => {
    const s = get()
    const held = s.heldCarts.find((h) => h.id === id)
    if (!held) return
    const heldCarts = s.heldCarts.filter((h) => h.id !== id)
    persistHeld(heldCarts)
    set({
      ...emptyCart,
      heldCarts,
      saleMode: held.saleMode,
      customer: held.customer,
      headerDiscount: held.headerDiscount,
      headerDiscountReason: held.headerDiscountReason,
      lines: held.lines.map(recompute),
      selectedLineRef: held.lines[0]?.lineRef ?? null,
      lineCounter: Math.max(s.lineCounter, ...held.lines.map((l) => Number(l.lineRef.slice(1)) || 0)),
    })
  },

  discardHeld: (id) => {
    const heldCarts = get().heldCarts.filter((h) => h.id !== id)
    persistHeld(heldCarts)
    set({ heldCarts })
  },

  dismissModeSwitchNote: () => set({ modeSwitchNote: null }),

  reset: () => set({ ...emptyCart }),
}))
