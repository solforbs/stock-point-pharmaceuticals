import { useLetterhead } from '../../hooks/useLetterhead'
import { dIsPos, dSum } from '../../lib/decimal'
import { formatDateTime } from '../../lib/format'
import { formatMoney, formatQty } from '../../lib/money'
import type { Decimal, Sale, SaleLine } from '../../lib/types'

export type ReceiptPayment = { method: string; reference?: string | null; amount: Decimal }

export interface ReceiptSlipProps {
  sale: Sale
  payments: ReceiptPayment[]
  servedBy: string | null | undefined
  terminalId?: string | null
  customerName?: string | null
  /** The product name and unit for a line, when the caller knows better than the sale (the till's cart). */
  describeLine?: (line: SaleLine) => { name?: string | null; uom?: string | null }
  cashTendered?: Decimal | null
  change?: Decimal | null
  /** The sale as last fetched, for the KRA eTIMS block. */
  etims?: Pick<Sale, 'etims_status' | 'etims_invoice_number' | 'etims_control_code'> | null
  offline?: boolean
  /** A copy printed after the sale: marked so it cannot pass as a second original. */
  reprint?: boolean
}

/**
 * The till slip. Its header is the company profile (name, KRA PIN, address,
 * phone), and it names the cashier who served. The print stylesheet prints
 * #pos-receipt and nothing else on the page.
 */
export function ReceiptSlip({ sale, payments, servedBy, terminalId, customerName, describeLine, cashTendered, change, etims, offline = false, reprint = false }: ReceiptSlipProps) {
  const letterhead = useLetterhead().data
  const paid = dSum(payments.map((p) => p.amount))
  const address = [letterhead?.address, letterhead?.postal_address].filter(Boolean).join(', ')

  return (
    <div className="font-mono text-xs tabular" id="pos-receipt">
      <div className="text-center mb-3">
        <div className="font-bold text-sm uppercase">{letterhead ? letterhead.legal_name || letterhead.organisation : 'Receipt'}</div>
        {letterhead?.branch && <div>{letterhead.branch}</div>}
        {address && <div>{address}</div>}
        {letterhead?.phone && <div>Tel {letterhead.phone}</div>}
        {letterhead && <div>KRA PIN: {letterhead.kra_pin ?? 'not set'}</div>}
        <div className="border-t border-dashed border-slate-300 my-2" />
        {reprint && <div className="font-bold">*** REPRINT — COPY ***</div>}
        <div>
          {sale.doc_number} · {sale.sale_mode}
          {terminalId ? ` · Till ${terminalId}` : ''}
        </div>
        <div>{formatDateTime(sale.posted_at)}</div>
        {servedBy && <div>Served by: {servedBy}</div>}
        {customerName && <div>Customer: {customerName}</div>}
      </div>
      <table className="w-full">
        <tbody>
          {(sale.lines ?? []).map((line) => {
            const known = describeLine?.(line)
            return (
              <tr key={line.id} className="align-top">
                <td className="py-0.5 pr-2">
                  {known?.name ?? line.product?.name ?? line.product_id.slice(0, 8)}
                  {line.is_bonus && <span className="ml-1 font-bold">FREE</span>}
                  <div className="text-slate-500">
                    {formatQty(line.qty)} {known?.uom ?? line.uom?.code ?? ''} @ {formatMoney(line.unit_price)}
                  </div>
                </td>
                <td className="py-0.5 text-right whitespace-nowrap">{formatMoney(line.line_total)}</td>
              </tr>
            )
          })}
        </tbody>
      </table>
      <div className="border-t border-dashed border-slate-300 my-2" />
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
        <span className="font-bold text-sm">TOTAL</span>
        <span className="font-bold text-sm">{formatMoney(sale.grand_total)}</span>
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
        {change && cashTendered && (
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
      {!offline && etims?.etims_status === 'SUBMITTED' && (
        <div className="mt-3 border-t border-dashed border-slate-300 pt-2 text-center">
          <div className="font-bold">KRA eTIMS</div>
          <div>CU invoice no. {etims.etims_invoice_number}</div>
          {etims.etims_control_code && <div className="break-all">Signature {etims.etims_control_code}</div>}
        </div>
      )}
      {!offline && etims?.etims_status === 'PENDING' && <div className="text-center mt-3 text-slate-500 no-print">eTIMS: waiting for KRA to sign this invoice…</div>}
      {!offline && etims?.etims_status === 'FAILED' && <div className="text-center mt-3 text-amber-700 no-print">eTIMS: not yet accepted by KRA; it is retried from Compliance.</div>}
      {offline && (
        <div className="text-center mt-3 font-bold">
          OFFLINE SALE: NOT A TAX INVOICE.
          <div className="font-normal">The eTIMS invoice is issued when this sale reaches the server.</div>
        </div>
      )}
      {sale.status === 'VOIDED' && <div className="text-center mt-3 font-bold">VOIDED: NOT A VALID RECEIPT.</div>}
      <div className="text-center mt-3 text-slate-500">Thank you. Goods sold in good condition; keep this receipt.</div>
    </div>
  )
}
