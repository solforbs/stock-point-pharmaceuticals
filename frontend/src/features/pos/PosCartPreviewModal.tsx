import { FileText, Printer } from 'lucide-react'
import { Modal } from '../../components/ui/Modal'
import { Button } from '../../components/ui/primitives'
import { formatMoney, formatQty } from '../../lib/money'
import type { Customer, Quote, SaleMode } from '../../lib/types'
import type { CartLine } from './cartStore'

export interface PosCartPreviewModalProps {
  open: boolean
  onClose: () => void
  customer: Customer | null
  lines: CartLine[]
  quote: Quote | null
  estimateTotal: string
  saleMode: SaleMode
  branchName?: string
}

export function PosCartPreviewModal({
  open,
  onClose,
  customer,
  lines,
  quote,
  estimateTotal,
  saleMode,
  branchName,
}: PosCartPreviewModalProps) {
  if (!open) return null

  const totalQty = lines.reduce((acc, l) => acc + (Number(l.qty) || 0), 0)
  const displayTotal = quote ? quote.totals.grand_total : estimateTotal
  const subtotal = quote ? quote.totals.subtotal : estimateTotal
  const tax = quote ? quote.totals.tax : '0'
  const discount = quote ? quote.totals.discount : '0'

  function handlePrint() {
    window.print()
  }

  const modalTitle = (
    <div className="flex items-center gap-2">
      <div className="p-1.5 rounded-lg bg-blue-50 text-blue-600">
        <FileText size={16} />
      </div>
      <div>
        <div className="font-bold text-slate-900 leading-tight">
          Cart Summary &amp; Proforma Preview
        </div>
        <div className="text-xs font-normal text-slate-500 mt-0.5">
          {saleMode} · {lines.length} {lines.length === 1 ? 'line' : 'lines'} · {formatQty(String(totalQty))} units
          {customer && ` · Customer: ${customer.name}`}
        </div>
      </div>
    </div>
  )

  const modalFooter = (
    <div className="flex items-center justify-between w-full">
      <span className="text-xs text-slate-500">
        Wholesale proforma preview
      </span>
      <div className="flex items-center gap-2">
        <Button variant="secondary" size="sm" onClick={onClose}>
          Back to Cart
        </Button>
        <Button variant="primary" size="sm" onClick={handlePrint} className="gap-1.5">
          <Printer size={14} />
          <span>Print Preview</span>
        </Button>
      </div>
    </div>
  )

  return (
    <Modal open={open} onClose={onClose} width={720} title={modalTitle} footer={modalFooter}>
      <div className="space-y-4 max-h-[60vh] overflow-y-auto pos-scroll pr-1">
        {branchName && (
          <div className="text-xs text-slate-600 font-medium">
            Branch: <span className="font-semibold text-slate-900">{branchName}</span>
          </div>
        )}

        <table className="ui-table">
          <thead>
            <tr>
              <th className="w-10">#</th>
              <th>Item &amp; Formulation</th>
              <th className="text-right">Qty</th>
              <th className="text-right">Unit Price</th>
              <th className="text-right">Total</th>
            </tr>
          </thead>
          <tbody>
            {lines.map((l, i) => {
              const lineTotal = l.quoted ? l.quoted.line_total : l.localEstimate
              const unitPrice = l.quoted ? l.quoted.unit_price : (l.estimateUnitPrice ?? '0')
              return (
                <tr key={l.lineRef}>
                  <td className="tabular text-slate-400 font-mono text-xs">{i + 1}</td>
                  <td>
                    <div className="font-semibold text-slate-900">{l.productName}</div>
                    <div className="text-xs text-slate-500 font-mono">
                      #{l.productCode} {l.strength ? `· ${l.strength}` : ''}
                    </div>
                  </td>
                  <td className="text-right tabular font-semibold text-slate-800">
                    {l.qty} <span className="text-slate-500 font-normal text-xs">{l.uomCode}</span>
                  </td>
                  <td className="text-right tabular text-slate-700">
                    {formatMoney(unitPrice)}
                  </td>
                  <td className="text-right tabular font-bold text-slate-900">
                    {formatMoney(lineTotal)}
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>

        {/* Financial Summary Box */}
        <div className="rounded-xl bg-slate-50 border border-slate-200 p-3.5 space-y-1.5 text-xs">
          <div className="flex justify-between text-slate-600">
            <span>Subtotal ({lines.length} items):</span>
            <span className="tabular font-semibold text-slate-800">{formatMoney(subtotal)}</span>
          </div>
          {Number(discount) > 0 && (
            <div className="flex justify-between text-emerald-700">
              <span>Total Discounts:</span>
              <span className="tabular font-bold">-{formatMoney(discount)}</span>
            </div>
          )}
          {Number(tax) > 0 && (
            <div className="flex justify-between text-slate-600">
              <span>VAT (16%):</span>
              <span className="tabular font-semibold text-slate-800">{formatMoney(tax)}</span>
            </div>
          )}
          <div className="flex justify-between items-baseline pt-2 border-t border-slate-200 text-sm font-bold text-slate-900">
            <span>Total Payable:</span>
            <span className="text-base font-bold tabular text-blue-700">{formatMoney(displayTotal)}</span>
          </div>
        </div>
      </div>
    </Modal>
  )
}
