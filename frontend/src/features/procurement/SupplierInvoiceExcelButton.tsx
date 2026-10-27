import { FileSpreadsheet } from 'lucide-react'
import { useState } from 'react'
import { Button } from '../../components/ui/primitives'
import { apiGet } from '../../lib/api'
import { downloadBlob } from '../../lib/csv'
import { formatDate } from '../../lib/format'
import { xlsxLetterhead } from '../../lib/letterhead'
import { toastApiError } from '../../lib/toast'
import type { SupplierInvoice } from '../../lib/types'
import { buildXlsx } from '../../lib/xlsxWriter'

const money = (v: string | number | null | undefined) => (v === null || v === undefined || v === '' ? null : Math.round(Number(v) * 100) / 100)

const MATCH_LABEL: Record<SupplierInvoice['match_status'], string> = { UNMATCHED: 'not yet matched', MATCHED: 'matched', EXCEPTION: 'match exception' }

/** The supplier invoice as an Excel workbook under the institution's letterhead: every line against its PO, then goods, transport, VAT and total. */
export function SupplierInvoiceExcelButton({ id, docNumber, size = 'sm' }: { id: string; docNumber: string; size?: 'sm' | 'md' }) {
  const [busy, setBusy] = useState(false)

  const download = async () => {
    setBusy(true)
    try {
      const [inv, letterhead] = await Promise.all([apiGet<SupplierInvoice>(`/api/supplier-invoices/${id}`), xlsxLetterhead()])
      const lines = inv.lines ?? []
      const details = [
        inv.supplier?.name,
        `dated ${formatDate(inv.invoice_date)}`,
        inv.due_date ? `due ${formatDate(inv.due_date)}` : null,
        MATCH_LABEL[inv.match_status],
      ].filter(Boolean).join(' · ')

      const blob = buildXlsx({
        sheetName: docNumber,
        letterhead,
        title: `Supplier invoice ${inv.invoice_number} (${docNumber}) · ${details}`,
        header: ['No.', 'Code', 'Product', 'PO', 'Unit', 'Qty', 'PO price', 'Invoiced price', 'Line total'],
        rows: [
          ...lines.map((l, i) => [
            i + 1,
            l.product?.code ?? '',
            l.product?.name ?? l.product_id,
            l.purchase_order_line?.purchase_order?.doc_number ?? '',
            l.purchase_order_line?.uom?.code ?? '',
            Number(l.qty),
            money(l.purchase_order_line?.unit_price),
            money(l.unit_price),
            money(l.line_total),
          ]),
          [],
          [null, null, 'Goods', null, null, null, null, null, money(inv.subtotal)],
          [null, null, 'Transport (from the PO)', null, null, null, null, null, money(inv.transport_total ?? 0)],
          [null, null, 'VAT', null, null, null, null, null, money(inv.tax_total)],
          [null, null, 'Invoice total', null, null, null, null, null, money(inv.grand_total)],
        ],
        widths: [6, 14, 40, 16, 8, 10, 12, 14, 16],
      })
      downloadBlob(blob, `${docNumber}.xlsx`, blob.type)
    } catch (e) {
      toastApiError(e, 'Could not produce the Excel file')
    } finally {
      setBusy(false)
    }
  }

  return (
    <Button size={size} disabled={busy} onClick={(e) => { e.stopPropagation(); void download() }}>
      <FileSpreadsheet size={12} /> {busy ? 'Preparing…' : 'Excel'}
    </Button>
  )
}
