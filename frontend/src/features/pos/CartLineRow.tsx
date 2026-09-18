import { ChevronDown, ChevronRight, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { PriceBreakdownPopover } from '../../components/PriceBreakdownPopover'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { QuantityInput } from '../../components/ui/QuantityInput'
import { dCmp, dIsPos } from '../../lib/decimal'
import { formatDate } from '../../lib/format'
import { useProductStock, useStores } from '../../lib/hooks'
import { formatMoney, formatPct, formatQty } from '../../lib/money'
import { previewFefo } from './fefo'
import { useCartStore, type CartLine } from './cartStore'

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
}: {
  line: CartLine
  selected: boolean
  quoteFresh: boolean
  canDiscount: boolean
  showCost: boolean
  disabled: boolean
  onSelect: () => void
  onRemove: () => void
  onQtyEnter: () => void
}) {
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

  return (
    <div
      onClick={onSelect}
      className={`px-3 py-2 border-b border-[var(--border)] cursor-default ${selected ? 'bg-[color-mix(in_srgb,var(--color-navy)_8%,var(--card))] border-l-2 border-l-[var(--color-navy)]' : 'border-l-2 border-l-transparent'}`}
      data-line-ref={line.lineRef}
    >
      <div className="flex items-start gap-2">
        <div className="flex-1 min-w-0">
          <div className="text-[13px] font-semibold text-[var(--text)] truncate">
            {line.productName}
            {line.strength && <b className="ml-1">{line.strength}</b>}
            <span className="ml-2 text-[10.5px] font-normal text-[var(--text-muted)]">{line.productCode}</span>
          </div>
        </div>
        {quoted?.approval_required && showQuoted && (
          <span className="text-[10px] font-bold uppercase text-[#b45309] px-1.5 py-0.5 rounded bg-[color-mix(in_srgb,var(--status-amber)_18%,transparent)]">Needs approval</span>
        )}
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation()
            onRemove()
          }}
          disabled={disabled}
          aria-label="Remove line"
          title="Remove line (Ctrl+Del)"
          className="p-1 rounded text-[var(--text-muted)] hover:text-[var(--status-red)] disabled:opacity-40"
        >
          <Trash2 size={14} />
        </button>
      </div>

      <div className="flex items-start gap-3 mt-1">
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
          className="ui-input h-7 w-auto text-[12px]"
          aria-label="Unit of measure"
        >
          {line.uoms.map((u) => (
            <option key={u.uom_id} value={u.uom_id}>
              {u.uom?.code ?? u.uom_id} {u.factor_to_base !== 1 ? `(×${u.factor_to_base})` : ''}
            </option>
          ))}
        </select>

        <div className="flex-1 min-w-0 text-[12px] tabular pt-1">
          <span className="text-[var(--text-muted)]">@</span>{' '}
          {showQuoted ? (
            <>
              <span className="font-semibold">{formatMoney(quoted.unit_price)}</span>
              {hasBonus && <span className="ml-2 text-[var(--status-green)] font-bold">+{formatQty(quoted.bonus_qty)} FREE</span>}
              <span className="ml-1 align-middle inline-block">
                <PriceBreakdownPopover line={quoted} showCost={showCost} />
              </span>
            </>
          ) : (
            <span className="text-[var(--text-muted)] italic">{line.estimateUnitPrice ? `${formatMoney(line.estimateUnitPrice)} est.` : 'pricing…'}</span>
          )}
        </div>

        <div className="text-right pt-1 min-w-[96px]">
          <MoneyCell value={lineTotal} className={`text-[13px] font-bold ${showQuoted ? '' : 'opacity-50 italic'}`} />
        </div>
      </div>

      {showQuoted && (
        <div className="text-[10.5px] text-[var(--text-muted)] tabular mt-0.5">
          ↳ list {formatMoney(quoted.list_price)} · break {formatMoney(quoted.break_price)}
          {Number(quoted.discount_pct) > 0 && <> · disc {formatPct(quoted.discount_pct)}{quoted.discount_capped_by ? ` (capped: ${quoted.discount_capped_by})` : ''}</>}
          {quoted.tax_code && <> · {quoted.tax_code} {formatPct(quoted.tax_rate)}</>}
          {showCost && quoted.margin_pct !== null && <> · margin {formatPct(quoted.margin_pct)}</>}
          {quoted.floor_breached && <span className="text-[var(--status-red)] font-bold"> · below floor</span>}
        </div>
      )}

      <button
        type="button"
        onClick={(e) => {
          e.stopPropagation()
          setFefoOpen((v) => !v)
        }}
        className="text-[10.5px] text-[var(--text-muted)] hover:text-[var(--text)] tabular mt-0.5 inline-flex items-center gap-1"
      >
        {fefoOpen ? <ChevronDown size={10} /> : <ChevronRight size={10} />}
        ↳ FEFO:{' '}
        {fefo.allocations.length === 0 ? (
          <span className="text-[var(--status-red)]">
            no released stock in {sellingStoreCode}
            {heldElsewhere.length > 0 && (
              <span className="text-[var(--text-secondary)]">
                {' '}· {heldElsewhere.map((row) => `${formatQty(row.free_to_sell)} in ${row.store_code}`).join(', ')} — transfer it first
              </span>
            )}
          </span>
        ) : (
          fefo.allocations.map((a) => `${a.batch_number} ×${formatQty(a.qty_base)}`).join(', ')
        )}
        {dIsPos(fefo.shortfall) && <span className="text-[var(--status-red)] font-bold"> · short {formatQty(fefo.shortfall)} {line.baseUomCode}</span>}
      </button>
      {fefoOpen && fefo.allocations.length > 0 && (
        <ul className="ml-4 mt-1 text-[10.5px] tabular text-[var(--text-secondary)] space-y-0.5">
          {fefo.allocations.map((a) => (
            <li key={a.batch_id}>
              {a.batch_number} · expires {formatDate(a.expiry_date)} · {formatQty(a.qty_base)} {line.baseUomCode}
            </li>
          ))}
          <li className="text-[var(--text-muted)]">Preview only — the server allocates at checkout.</li>
        </ul>
      )}

      {canDiscount && (
        <div className="mt-1">
          {!discountOpen ? (
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation()
                setDiscountOpen(true)
              }}
              className="text-[10.5px] text-[var(--color-navy)] hover:underline"
              data-discount-toggle={line.lineRef}
            >
              + line discount (F6)
            </button>
          ) : (
            <div className="flex items-center gap-2 mt-1">
              <label className="text-[10.5px] text-[var(--text-muted)]">Discount %</label>
              <input
                type="text"
                inputMode="decimal"
                value={line.requestedDiscountPct ?? ''}
                disabled={disabled}
                onChange={(e) => setLineDiscount(line.lineRef, e.target.value.replace(/[^\d.]/g, ''), line.discountReason ?? '')}
                data-discount-for={line.lineRef}
                className="ui-input h-7 w-16 tabular text-right text-[12px]"
              />
              <input
                type="text"
                placeholder="Reason (required)"
                value={line.discountReason ?? ''}
                disabled={disabled}
                onChange={(e) => setLineDiscount(line.lineRef, line.requestedDiscountPct ?? '', e.target.value)}
                className="ui-input h-7 flex-1 text-[12px]"
              />
              {quoted?.floor_price && showQuoted && <span className="text-[10.5px] text-[var(--text-muted)] tabular">floor {formatMoney(quoted.floor_price)}</span>}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
