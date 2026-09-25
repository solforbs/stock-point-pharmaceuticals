import { Minus, Plus, Trash2 } from 'lucide-react'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { QuantityInput } from '../../components/ui/QuantityInput'
import { dAdd, dSub } from '../../lib/decimal'
import { useProductStock } from '../../lib/hooks'
import { formatMoney } from '../../lib/money'
import { useCartStore, type CartLine } from './cartStore'

export interface CartLineRowProps {
  line: CartLine
  selected: boolean
  quoteFresh: boolean
  disabled: boolean
  onSelect: () => void
  onRemove: () => void
  onQtyEnter: () => void
}

/**
 * One line of the cart, kept to a single row — name, quantity, final price
 * and remove — so ten products fit on screen at once. Everything else about
 * the line (stock, batches, the price decision) lives in the add-product
 * dialog; adding the product again reopens it for the same line.
 */
export function CartLineRow({ line, selected, quoteFresh, disabled, onSelect, onRemove, onQtyEnter }: CartLineRowProps) {
  const setQty = useCartStore((s) => s.setQty)
  const storeId = useCartStore((s) => s.storeId)

  const { data: stock } = useProductStock(line.productId)
  const freeToSell = stock?.stores.find((row) => row.store_id === storeId)?.free_to_sell ?? null

  const quoted = line.quoted
  const showQuoted = !!quoted && quoteFresh
  const lineTotal = showQuoted ? quoted.line_total : line.localEstimate
  const unitPrice = showQuoted ? quoted.unit_price : (line.sellingPrice ?? line.estimateUnitPrice)

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
      className={`flex items-center gap-2 rounded-xl border px-3 py-1.5 cursor-default transition-all ${
        selected
          ? 'bg-blue-50/30 border-blue-400 ring-1 ring-blue-400/20'
          : 'bg-white border-slate-200/70 hover:border-slate-300/80 shadow-2xs'
      }`}
    >
      <span
        className="flex-1 min-w-0 truncate text-xs font-bold text-slate-900"
        title={`${line.productName}${line.strength ? ` ${line.strength}` : ''} · #${line.productCode}`}
      >
        {line.productName}
        {line.strength && <span className="ml-1.5 font-semibold text-slate-500">{line.strength}</span>}
      </span>

      <div className="flex items-center gap-1 shrink-0">
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation()
            stepQty(-1)
          }}
          disabled={disabled}
          title="Decrease (or remove if 1)"
          className="w-6 h-6 rounded-full flex items-center justify-center bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-900 disabled:opacity-40 transition-all cursor-pointer"
        >
          <Minus size={11} />
        </button>
        <QuantityInput
          value={line.qty}
          onChange={(qty) => setQty(line.lineRef, qty)}
          uomCode={line.uoms.find((u) => u.uom_id === line.uomId)?.uom?.code ?? ''}
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
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation()
            stepQty(1)
          }}
          disabled={disabled}
          title="Increase quantity"
          className="w-6 h-6 rounded-full flex items-center justify-center bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-900 disabled:opacity-40 transition-all cursor-pointer"
        >
          <Plus size={11} />
        </button>
      </div>

      <MoneyCell
        value={lineTotal}
        title={unitPrice ? `@ ${formatMoney(unitPrice)}${line.sellingPrice ? ' · till price for this sale' : ''}` : undefined}
        className={`w-24 shrink-0 text-right text-sm font-bold tracking-tight text-slate-900 ${showQuoted ? '' : 'opacity-60 italic'}`}
      />

      <button
        type="button"
        onClick={(e) => {
          e.stopPropagation()
          onRemove()
        }}
        disabled={disabled}
        aria-label="Remove item"
        title="Remove item (Ctrl+Del)"
        className="shrink-0 p-1.5 rounded-full text-slate-400 hover:text-rose-600 hover:bg-rose-50 active:bg-rose-100 transition-all disabled:opacity-40 cursor-pointer"
      >
        <Trash2 size={14} />
      </button>
    </div>
  )
}
