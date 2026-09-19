import { Info } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { formatMoney, formatPct } from '../lib/money'
import type { QuoteLine } from '../lib/types'

/**
 * Part 1.5 / Part 5.9 — explains exactly how the price on this line was
 * reached, straight from the server quote's explain[] steps.
 */
export function PriceBreakdownPopover({ line, showCost }: { line: QuoteLine; showCost: boolean }) {
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return
    function onDown(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false)
    }
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') setOpen(false)
    }
    document.addEventListener('mousedown', onDown)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', onDown)
      document.removeEventListener('keydown', onKey)
    }
  }, [open])

  return (
    <div ref={ref} className="relative inline-block">
      <button
        type="button"
        aria-label="Explain this price"
        title="How was this price reached?"
        onClick={() => setOpen((v) => !v)}
        className={`p-1 rounded-md transition-colors ${open ? 'text-blue-600 bg-blue-50' : 'text-slate-400 hover:text-slate-600'}`}
      >
        <Info size={14} />
      </button>
      {open && (
        <div className="absolute right-0 top-6 z-30 w-[380px] ui-card shadow-xl p-3.5 text-xs text-left">
          <div className="font-bold text-sm text-slate-900 mb-1.5">{line.product_name}</div>
          <table className="w-full tabular mb-2">
            <tbody>
              <Row label="List price" value={formatMoney(line.list_price)} />
              <Row label={`Break price (${line.price_source})`} value={formatMoney(line.break_price)} />
              <Row label={`Discount ${formatPct(line.discount_pct)}${line.discount_capped_by ? ` · capped by ${line.discount_capped_by}` : ''}`} value={`−${formatMoney(line.discount_amount)}`} />
              <Row label="Unit price" value={formatMoney(line.unit_price)} strong />
              <Row label={`Tax ${line.tax_code ?? ''} ${formatPct(line.tax_rate)}`} value={formatMoney(line.tax_amount)} />
              <Row label="Line total" value={formatMoney(line.line_total)} strong />
              {line.floor_price && <Row label="Floor price" value={formatMoney(line.floor_price)} warn={line.floor_breached} />}
              {showCost && line.margin_pct !== null && <Row label="Margin" value={`${formatPct(line.margin_pct)} · profit ${formatMoney(line.gross_profit)}`} />}
            </tbody>
          </table>
          <div className="ui-label">Explain</div>
          <ol className="list-decimal pl-4 space-y-0.5 text-slate-600">
            {line.explain.map((step, i) => (
              <li key={i}>{step}</li>
            ))}
          </ol>
        </div>
      )}
    </div>
  )
}

function Row({ label, value, strong, warn }: { label: string; value: string; strong?: boolean; warn?: boolean }) {
  return (
    <tr>
      <td className={`py-0.5 pr-2 ${warn ? 'text-rose-600 font-bold' : 'text-slate-500'}`}>{label}</td>
      <td className={`py-0.5 text-right ${strong ? 'font-bold' : ''} ${warn ? 'text-rose-600' : ''}`}>{value}</td>
    </tr>
  )
}
