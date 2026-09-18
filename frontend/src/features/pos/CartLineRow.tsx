import { ChevronDown, ChevronRight, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { PriceBreakdownPopover } from '../../components/PriceBreakdownPopover'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { QuantityInput } from '../../components/ui/QuantityInput'
import { dIsPos } from '../../lib/decimal'
import { formatDate } from '../../lib/format'
import { useProductStock } from '../../lib/hooks'
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
  const storeRow = stock?.stores.find((row) => row.store_id === storeId)
  const freeToSell = storeRow?.free_to_sell ?? null
  const fefo = previewFefo(storeRow, line.qtyBase)

  const quoted = line.quoted
  const showQuoted = !!quoted && quoteFresh
  const lineTotal = showQuoted ? quoted.line_total : line.localEstimate
  const hasBonus = !!quoted && dIsPos(quoted.bonus_qty)

  return (
    <div
      onClick={onSelect}
      data-line-ref={line.lineRef}
      className={`rounded-xl border transition-all p-3 cursor-default text-xs ${
        selected
          ? 'bg-blue-50/40 border-blue-500/80 shadow-xs ring-1 ring-blue-500/20'
          : 'bg-white border-slate-200/80 hover:border-slate-300 shadow-[0_1px_2px_rgba(0,0,0,0.02)]'
      }`}
    >
      {/* Title & Quick Actions Row */}
      <div className="flex items-start justify-between gap-2">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-1.5 flex-wrap">
            <span className="font-bold text-slate-800 text-[13px] tracking-tight">{line.productName}</span>
            {line.strength && (
              <span className="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold text-[10.5px]">
                {line.strength}
              </span>
            )}
            <span className="text-slate-400 font-mono text-[10.5px]">{line.productCode}</span>
          </div>
        </div>

        <div className="flex items-center gap-1.5 shrink-0">
          {quoted?.approval_required && showQuoted && (
            <span className="text-[10px] font-bold uppercase text-amber-700 px-1.5 py-0.5 rounded bg-amber-50 border border-amber-200">
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
            aria-label="Remove line"
            title="Remove item (Ctrl+Del)"
            className="p-1 rounded-md text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors disabled:opacity-40 cursor-pointer"
          >
            <Trash2 size={13} />
          </button>
        </div>
      </div>

      {/* Controls & Pricing Row */}
      <div className="flex items-center justify-between gap-3 mt-2.5 pt-2 border-t border-slate-100">
        <div className="flex items-center gap-2">
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
          />

          <select
            value={line.uomId}
            disabled={disabled || line.uoms.length <= 1}
            onChange={(e) => setUom(line.lineRef, e.target.value)}
            data-uom-for={line.lineRef}
            className="h-7 px-2 rounded-lg bg-slate-50 border border-slate-200 text-slate-700 text-xs font-medium focus:outline-none focus:border-blue-500"
            aria-label="Unit of measure"
          >
            {line.uoms.map((u) => (
              <option key={u.uom_id} value={u.uom_id}>
                {u.uom?.code ?? u.uom_id} {u.factor_to_base !== 1 ? `(×${u.factor_to_base})` : ''}
              </option>
            ))}
          </select>

          <div className="text-[11.5px] tabular text-slate-500 pl-1">
            <span>@</span>{' '}
            {showQuoted ? (
              <>
                <span className="font-semibold text-slate-800">{formatMoney(quoted.unit_price)}</span>
                {hasBonus && <span className="ml-1.5 text-emerald-600 font-bold">+{formatQty(quoted.bonus_qty)} FREE</span>}
                <span className="ml-1 inline-block align-middle">
                  <PriceBreakdownPopover line={quoted} showCost={showCost} />
                </span>
              </>
            ) : (
              <span className="italic text-slate-400">{line.estimateUnitPrice ? `${formatMoney(line.estimateUnitPrice)} est.` : 'pricing…'}</span>
            )}
          </div>
        </div>

        <div className="text-right">
          <MoneyCell value={lineTotal} className={`text-sm font-extrabold tracking-tight text-slate-900 ${showQuoted ? '' : 'opacity-60 italic'}`} />
        </div>
      </div>

      {/* FEFO & Batch allocation strip */}
      <div className="flex items-center justify-between mt-1.5 pt-1.5 text-[10.5px] text-slate-500">
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation()
            setFefoOpen((v) => !v)
          }}
          className="hover:text-slate-800 inline-flex items-center gap-1 cursor-pointer"
        >
          {fefoOpen ? <ChevronDown size={11} /> : <ChevronRight size={11} />}
          <span>FEFO: </span>
          {fefo.allocations.length === 0 ? (
            <span className="text-rose-600 font-medium">No stock available</span>
          ) : (
            <span className="text-slate-600 font-mono">
              {fefo.allocations.map((a) => `${a.batch_number} (×${formatQty(a.qty_base)})`).join(', ')}
            </span>
          )}
          {dIsPos(fefo.shortfall) && <span className="text-rose-600 font-bold ml-1">· Short {formatQty(fefo.shortfall)} {line.baseUomCode}</span>}
        </button>

        {canDiscount && !discountOpen && (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation()
              setDiscountOpen(true)
            }}
            data-discount-toggle={line.lineRef}
            className="text-blue-600 hover:text-blue-700 font-medium hover:underline cursor-pointer"
          >
            + Line Discount (F6)
          </button>
        )}
      </div>

      {/* Expanded FEFO details */}
      {fefoOpen && fefo.allocations.length > 0 && (
        <ul className="mt-1.5 p-2 bg-slate-50 rounded-lg text-[10.5px] space-y-1 text-slate-600 border border-slate-100">
          {fefo.allocations.map((a) => (
            <li key={a.batch_id} className="flex justify-between font-mono">
              <span>{a.batch_number} (exp: {formatDate(a.expiry_date)})</span>
              <span>{formatQty(a.qty_base)} {line.baseUomCode}</span>
            </li>
          ))}
          <li className="text-slate-400 italic text-[10px] pt-1 border-t border-slate-200/60">Server allocates batch on final checkout.</li>
        </ul>
      )}

      {/* Expanded Line Discount Inputs */}
      {discountOpen && canDiscount && (
        <div className="flex items-center gap-2 mt-2 pt-2 border-t border-slate-100">
          <span className="text-[11px] text-slate-500 font-medium">Disc %:</span>
          <input
            type="text"
            inputMode="decimal"
            placeholder="0"
            value={line.requestedDiscountPct ?? ''}
            disabled={disabled}
            onChange={(e) => setLineDiscount(line.lineRef, e.target.value.replace(/[^\d.]/g, ''), line.discountReason ?? '')}
            data-discount-for={line.lineRef}
            className="w-14 h-6 px-1.5 rounded bg-white border border-slate-200 text-xs font-bold text-slate-800 text-right focus:outline-none focus:border-blue-500"
          />
          <input
            type="text"
            placeholder="Reason (required)…"
            value={line.discountReason ?? ''}
            disabled={disabled}
            onChange={(e) => setLineDiscount(line.lineRef, line.requestedDiscountPct ?? '', e.target.value)}
            className="flex-1 h-6 px-2 rounded bg-white border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-blue-500"
          />
          <button
            type="button"
            onClick={() => {
              setLineDiscount(line.lineRef, '', '')
              setDiscountOpen(false)
            }}
            className="text-[10px] text-slate-400 hover:text-slate-600"
          >
            Clear
          </button>
        </div>
      )}
    </div>
  )
}
