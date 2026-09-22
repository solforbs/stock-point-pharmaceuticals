import { Printer } from 'lucide-react'
import { Link } from 'react-router-dom'
import { PdfDownloadButton } from '../../components/PdfDownloadButton'
import { Modal } from '../../components/ui/Modal'
import { Button, Kbd } from '../../components/ui/primitives'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { dCmp, dIsPos, dSub, dSum, isValidDecimal } from '../../lib/decimal'
import { formatDateTime } from '../../lib/format'
import { formatMoney, formatQty } from '../../lib/money'
import { useCartStore } from './cartStore'

/**
 * The till slip shown after posting. The document for the customer's file is
 * the server-rendered cash sale invoice PDF (Part 16.6), downloadable here
 * once the sale has reached the server.
 */
export function ReceiptView({ onNewSale }: { onNewSale: () => void }) {
  const sale = useCartStore((s) => s.postedSale)
  const quote = useCartStore((s) => s.quote)
  const lines = useCartStore((s) => s.lines)
  const payments = useCartStore((s) => s.postedPayments)
  const cashTendered = useCartStore((s) => s.cashTendered)
  const customer = useCartStore((s) => s.customer)
  const terminalId = useCartStore((s) => s.terminalId)
  const offline = useCartStore((s) => s.postedOffline)

  if (!sale) return null

  const paid = dSum(payments.map((p) => p.amount))
  const cashLine = payments.find((p) => p.method === 'CASH')
  const change = cashLine && isValidDecimal(cashTendered) && dIsPos(dSub(cashTendered, sale.grand_total)) ? dSub(cashTendered, sale.grand_total) : null
  const nameByProduct = new Map(lines.map((l) => [l.productId, l]))

  return (
    <Modal open onClose={onNewSale} title={<span className="flex items-center gap-2">Receipt {sale.doc_number} {offline ? <StatusBadge status="PENDING_SYNC" tone="amber" label="Waiting to sync" /> : <StatusBadge status={sale.status} />}</span>} width={520}>
      <div className="font-mono text-[12px] tabular" id="pos-receipt">
        <div className="text-center mb-3">
          <div className="font-bold text-[14px]">STOCKPOINT PHARMA</div>
          <div>{sale.sale_mode} · Terminal {terminalId}</div>
          <div>{formatDateTime(sale.posted_at)}</div>
          {customer && <div>Customer: {customer.name}</div>}
        </div>
        <table className="w-full">
          <tbody>
            {(sale.lines ?? []).map((line) => {
              const cart = nameByProduct.get(line.product_id)
              const quoted = quote?.lines.find((q) => q.product_id === line.product_id && q.uom_id === line.uom_id)
              return (
                <tr key={line.id} className="align-top">
                  <td className="py-0.5 pr-2">
                    {cart?.productName ?? quoted?.product_name ?? line.product?.name ?? line.product_id.slice(0, 8)}
                    {line.is_bonus && <span className="ml-1 font-bold">FREE</span>}
                    <div className="text-[var(--text-muted)]">
                      {formatQty(line.qty)} {cart?.uomCode ?? quoted?.uom_code ?? ''} @ {formatMoney(line.unit_price)}
                    </div>
                  </td>
                  <td className="py-0.5 text-right whitespace-nowrap">{formatMoney(line.line_total)}</td>
                </tr>
              )
            })}
          </tbody>
        </table>
        <div className="border-t border-dashed border-[var(--border-strong)] my-2" />
        <div className="grid grid-cols-[1fr_auto] gap-y-0.5">
          <span>Subtotal</span>
          <span>{formatMoney(sale.subtotal)}</span>
          {dIsPos(sale.discount_total) && (
            <>
              <span>Discount</span>
              <span>-{formatMoney(sale.discount_total)}</span>
            </>
          )}
          <span>VAT</span>
          <span>{formatMoney(sale.tax_total)}</span>
          <span className="font-bold text-[14px]">TOTAL</span>
          <span className="font-bold text-[14px]">{formatMoney(sale.grand_total)}</span>
          {payments.map((p, i) => (
            <span key={i} className="contents">
              <span>
                {p.method}
                {p.reference ? ` ${p.reference}` : ''}
              </span>
              <span>{formatMoney(p.amount)}</span>
            </span>
          ))}
          {payments.length === 0 && (
            <>
              <span>On credit</span>
              <span>{formatMoney(sale.grand_total)}</span>
            </>
          )}
          {change && (
            <>
              <span>Cash tendered</span>
              <span>{formatMoney(cashTendered)}</span>
              <span className="font-bold">CHANGE</span>
              <span className="font-bold">{formatMoney(change)}</span>
            </>
          )}
          {!change && payments.length > 0 && (
            <>
              <span>Paid</span>
              <span>{formatMoney(paid)}</span>
            </>
          )}
        </div>
        {offline && (
          <div className="text-center mt-3 font-bold">
            OFFLINE SALE: NOT A TAX INVOICE.
            <div className="font-normal">The eTIMS invoice is issued when this sale reaches the server.</div>
          </div>
        )}
        <div className="text-center mt-3 text-[var(--text-muted)]">Thank you. Goods sold in good condition; keep this receipt.</div>
      </div>
      <div className="flex items-center gap-2 mt-4">
        <Button variant="primary" size="lg" className="flex-1" onClick={onNewSale} autoFocus>
          New sale <Kbd>Enter</Kbd>
        </Button>
        <Button size="lg" onClick={() => window.print()}>
          <Printer size={14} /> Print
        </Button>
        {!offline && <PdfDownloadButton size="md" url={`/api/sales/${sale.id}/pdf`} filename={sale.doc_number} label={payments.length > 0 && dCmp(paid, sale.grand_total) >= 0 ? 'Cash sale invoice' : 'Invoice PDF'} />}
        {!offline && (
          <Link to="/sell/invoices" className="text-[11.5px] text-[var(--color-navy)] underline ml-2">
            Open in Invoices
          </Link>
        )}
      </div>
    </Modal>
  )
}
