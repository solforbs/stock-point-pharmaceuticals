import { ChevronDown, ChevronRight, Minus, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { PriceBreakdownPopover } from '../../components/PriceBreakdownPopover'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { QuantityInput } from '../../components/ui/QuantityInput'
import { dAdd, dCmp, dIsPos, dSub } from '../../lib/decimal'
import { formatDate } from '../../lib/format'
import { useProductStock, useStores } from '../../lib/hooks'
import { formatMoney, formatQty } from '../../lib/money'
import { previewFefo } from './fefo'
import { useCartStore, type CartLine } from './cartStore'

export interface CartLineRowProps {
  line: CartLine
  selected: boolean
  quoteFresh: boolean
  canDiscount: boolean
  showCost: boolean
  disabled: boolean
  onSelect: () => void
  onRemove: () => void
  onQtyEnter: () => void
}

export function CartLineRow({
  line,
  selected,
  quoteFresh,
  canDiscount,
  showCost,
  disabled,
  onSelect,
  onRemove,
  onQtyEnter,
}: CartLineRowProps) {
  const setQty = useCartStore((s) => s.setQty)
  const setUom = useCartStore((s) => s.setUom)
  const setLineDiscount = useCartStore((s) => s.setLineDiscount)
  const storeId = useCartStore((s) => s.storeId)
  const [fefoOpen, setFefoOpen] = useState(false)
  const [discountOpen, setDiscountOpen] = useState(!!line.requestedDiscountPct)

  const { data: stock } = useProductStock(line.productId)
  const { data: stores } = useStores()
  const storeRow = stock?.stores.find((row) => row.store_id === storeId)
  const freeToSell = storeRow?.free_to_sell ?? null
  const fefo = previewFefo(storeRow, line.qtyBase)

  // A released batch sitting in the warehouse looks to the cashier exactly
  // like a batch that was never released, so name the store that holds it.
  const sellingStoreCode = storeRow?.store_code ?? stores?.find((s) => s.id === storeId)?.code ?? 'this store'
  const heldElsewhere = (stock?.stores ?? [])
    .filter((row) => row.store_id !== storeId && dIsPos(row.free_to_sell))
    .sort((a, b) => dCmp(b.free_to_sell, a.free_to_sell))

  const quoted = line.quoted
  const showQuoted = !!quoted && quoteFresh
  const lineTotal = showQuoted ? quoted.line_total : line.localEstimate
  const hasBonus = !!quoted && dIsPos(quoted.bonus_qty)

  function stepQty(delta: 1 | -1) {
    if (disabled) return
    const current = Number(line.qty) || 1
    const next = delta === 1 ? dAdd(String(current), '1') : dSub(String(current), '1')
    if (Number(next) <= 0) {
      onRemove()
    } else {
      setQty(line.lineRef, next)
    }
  }

  return (
    <div
      onClick={onSelect}
      data-line-ref={line.lineRef}
      className={`rounded-2xl border transition-all p-3.5 cursor-default text-xs ${
        selected
          ? 'bg-blue-50/30 border-blue-400 shadow-xs ring-1 ring-blue-400/20'
          : 'bg-white border-slate-200/70 hover:border-slate-300/80 shadow-2xs'
      }`}
    >
      {/* Product Title & Code Header */}
      <div className="flex items-start justify-between gap-2 pb-2 border-b border-slate-100">
        <div className="flex-1 min-w-0">
          <div className="flex items-baseline gap-2 flex-wrap">
            <h4 className="font-bold text-slate-900 text-sm leading-snug break-words line-clamp-2">
              {line.productName}
            </h4>
            {line.strength && (
              <span className="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 font-bold text-xs shrink-0">
                {line.strength}
              </span>
            )}
            <span className="text-slate-500 font-mono text-xs font-medium shrink-0">#{line.productCode}</span>
          </div>
        </div>

        <div className="flex items-center gap-1.5 shrink-0">
          {quoted?.approval_required && showQuoted && (
            <span className="text-xs font-bold uppercase text-amber-800 px-2.5 py-0.5 rounded-full bg-amber-50 border border-amber-200">
              Needs approval
            </span>
          )}
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation()
              onRemove()
            }}
            disabled={disabled}
            aria-label="Remove item"
            title="Remove item (Ctrl+Del)"
            className="p-1.5 rounded-full text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors disabled:opacity-40 cursor-pointer"
          >
            <Trash2 size={14} />
          </button>
        </div>
      </div>

      {/* Stepper, UOM, Unit Price & Line Total */}
      <div className="flex items-center justify-between gap-2 pt-2.5">
        <div className="flex items-center gap-2">
          {/* Quantity Stepper */}
          <div className="flex items-center bg-slate-100/90 border border-slate-200/60 rounded-full p-0.5 shadow-2xs">
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation()
                stepQty(-1)
              }}
              disabled={disabled}
              className="w-6 h-6 rounded-full flex items-center justify-center text-slate-600 hover:bg-white hover:text-slate-900 shadow-2xs disabled:opacity-40 transition-all cursor-pointer"
              title="Decrease (or remove if 1)"
            >
              <Minus size={11} />
            </button>

            <QuantityInput
              value={line.qty}
              onChange={(qty) => setQty(line.lineRef, qty)}
              uomCode=""
              factorToBase={line.factorToBase}
              baseUomCode={line.baseUomCode}
              isDiscrete={line.isDiscrete}
              max={freeToSell}
              compact
              disabled={disabled}
              onEnter={onQtyEnter}
              inputRef={(el) => {
                if (el) el.dataset.qtyFor = line.lineRef
              }}
              className="!text-center font-bold text-xs !border-0 !bg-transparent"
            />

            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation()
                stepQty(1)
              }}
              disabled={disabled}
              className="w-6 h-6 rounded-full flex items-center justify-center text-slate-600 hover:bg-white hover:text-slate-900 shadow-2xs disabled:opacity-40 transition-all cursor-pointer"
              title="Increase quantity"
            >
              <Plus size={11} />
            </button>
          </div>

          {/* Unit of measure */}
          <select
            value={line.uomId}
            disabled={disabled || line.uoms.length <= 1}
            onChange={(e) => setUom(line.lineRef, e.target.value)}
            data-uom-for={line.lineRef}
            className="h-7 px-2.5 rounded-xl bg-slate-100/80 border border-slate-200/60 text-slate-700 text-xs font-bold focus:outline-none focus:border-blue-500 cursor-pointer shadow-2xs"
            aria-label="Unit of measure"
          >
            {line.uoms.map((u) => (
              <option key={u.uom_id} value={u.uom_id}>
                {u.uom?.code ?? u.uom_id} {u.factor_to_base !== 1 ? `(×${u.factor_to_base})` : ''}
              </option>
            ))}
          </select>

          {/* Unit price */}
          <div className="text-xs tabular text-slate-600 pl-0.5 font-medium">
            <span>@</span>{' '}
            {showQuoted ? (
              <>
                <span className="font-bold text-slate-900">{formatMoney(quoted.unit_price)}</span>
                {hasBonus && <span className="ml-1 text-emerald-600 font-bold">+{formatQty(quoted.bonus_qty)} FREE</span>}
                <span className="ml-1 inline-block align-middle">
                  <PriceBreakdownPopover line={quoted} showCost={showCost} />
                </span>
              </>
            ) : (
              <span className="italic text-slate-500">{line.estimateUnitPrice ? `${formatMoney(line.estimateUnitPrice)} est.` : 'pricing…'}</span>
            )}
          </div>
        </div>

        {/* Line Total */}
        <div className="text-right shrink-0">
          <MoneyCell
            value={lineTotal}
            className={`text-base font-bold tracking-tight text-slate-900 ${showQuoted ? '' : 'opacity-60 italic'}`}
          />
        </div>
      </div>

      {/* Batch & Expiry strip & Line discount trigger */}
      <div className="flex items-center justify-between mt-2 pt-1.5 text-xs text-slate-600 font-medium border-t border-slate-100/80">
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation()
            setFefoOpen((v) => !v)
          }}
          className="hover:text-slate-900 inline-flex items-center gap-1 cursor-pointer font-semibold"
        >
          {fefoOpen ? <ChevronDown size={12} /> : <ChevronRight size={12} />}
          <span>Batch: </span>
          {fefo.allocations.length === 0 ? (
            <span className="text-amber-800 font-bold bg-amber-50 px-2.5 py-0.5 rounded-full border border-amber-200/80">
              No released stock in {sellingStoreCode}
              {heldElsewhere.length > 0 && (
                <span className="font-medium">
                  {' '}· {heldElsewhere.map((row) => `${formatQty(row.free_to_sell)} in ${row.store_code}`).join(', ')} — transfer it first
                </span>
              )}
            </span>
          ) : (
            <span className="text-slate-700 font-mono">
              {fefo.allocations.map((a) => `${a.batch_number} (×${formatQty(a.qty_base)})`).join(', ')}
            </span>
          )}
          {dIsPos(fefo.shortfall) && (
            <span className="text-rose-700 font-bold bg-rose-50 px-2.5 py-0.5 rounded-full border border-rose-200/80 ml-1">
              Short by {formatQty(fefo.shortfall)}
            </span>
          )}
        </button>

        {canDiscount && !discountOpen && (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation()
              setDiscountOpen(true)
            }}
            data-discount-toggle={line.lineRef}
            className="text-blue-600 hover:text-blue-700 font-bold hover:underline cursor-pointer"
          >
            + Discount (F6)
          </button>
        )}
      </div>

      {/* Expanded FEFO info */}
      {fefoOpen && fefo.allocations.length > 0 && (
        <ul className="mt-2 p-2.5 bg-slate-50/80 rounded-xl text-xs space-y-1 text-slate-600 border border-slate-200/50">
          {fefo.allocations.map((a) => (
            <li key={a.batch_id} className="flex justify-between font-mono">
              <span>{a.batch_number} · exp {formatDate(a.expiry_date)}</span>
              <span>{formatQty(a.qty_base)} {line.baseUomCode}</span>
            </li>
          ))}
        </ul>
      )}

      {/* Expanded Line Discount Inputs */}
      {discountOpen && canDiscount && (
        <div className="flex items-center gap-2 mt-2 pt-2 border-t border-slate-100">
          <span className="text-xs text-slate-500 font-bold">Disc %:</span>
          <input
            type="text"
            inputMode="decimal"
            placeholder="0"
            value={line.requestedDiscountPct ?? ''}
            disabled={disabled}
            onChange={(e) => setLineDiscount(line.lineRef, e.target.value.replace(/[^\d.]/g, ''), line.discountReason ?? '')}
            data-discount-for={line.lineRef}
            className="w-14 h-7 px-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-800 text-right focus:outline-none focus:border-blue-500 shadow-2xs"
          />
          <input
            type="text"
            placeholder="Reason (required)…"
            value={line.discountReason ?? ''}
            disabled={disabled}
            onChange={(e) => setLineDiscount(line.lineRef, line.requestedDiscountPct ?? '', e.target.value)}
            className="flex-1 h-7 px-2.5 rounded-xl bg-white border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-blue-500 shadow-2xs"
          />
          <button
            type="button"
            onClick={() => {
              setLineDiscount(line.lineRef, '', '')
              setDiscountOpen(false)
            }}
            className="text-xs text-slate-400 hover:text-slate-600 cursor-pointer"
          >
            Clear
          </button>
        </div>
      )}
    </div>
  )
}
