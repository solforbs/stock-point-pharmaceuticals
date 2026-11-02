import { FileSpreadsheet } from 'lucide-react'
import { useState } from 'react'
import { Button } from '../../components/ui/primitives'
import { apiGet } from '../../lib/api'
import { downloadBlob } from '../../lib/csv'
import { formatDate } from '../../lib/format'
import { xlsxLetterhead } from '../../lib/letterhead'
import { toastApiError } from '../../lib/toast'
import type { GoodsReceipt } from '../../lib/types'
import { buildXlsx } from '../../lib/xlsxWriter'

const money = (v: string | number | null | undefined) => (v === null || v === undefined || v === '' ? null : Math.round(Number(v) * 100) / 100)

/** The goods receipt as an Excel workbook under the institution's letterhead: every batch received, with its brand, quantities and cost. */
export function GrnExcelButton({ id, docNumber, size = 'sm' }: { id: string; docNumber: string; size?: 'sm' | 'md' }) {
  const [busy, setBusy] = useState(false)

  const download = async () => {
    setBusy(true)
    try {
      const [g, letterhead] = await Promise.all([apiGet<GoodsReceipt>(`/api/goods-receipts/${id}`), xlsxLetterhead()])
      const value = (l: GoodsReceipt['lines'][number]) => Number(l.qty_accepted) * Number(l.unit_cost ?? 0)
      const total = g.lines.reduce((s, l) => s + value(l), 0)
      const details = [
        g.supplier?.name,
        g.purchase_order ? `PO ${g.purchase_order.doc_number}` : 'emergency receipt',
        g.store ? `into ${g.store.code}` : null,
        g.received_at ? `received ${formatDate(g.received_at)}` : null,
      ].filter(Boolean).join(' · ')

      const blob = buildXlsx({
        sheetName: docNumber,
        letterhead,
        title: `Goods received note ${docNumber} · ${details}`,
        header: ['No.', 'Code', 'Product', 'Unit', 'Batch', 'Brand', 'Expiry', 'Ordered', 'Delivered', 'Accepted', 'Rejected', 'Unit cost', 'Landed cost', 'Value'],
        rows: [
          ...g.lines.map((l, i) => [
            i + 1,
            l.product?.code ?? '',
            l.product?.name ?? l.product_id,
            l.uom?.code ?? '',
            l.batch_number,
            l.brand_name ?? '',
            l.expiry_date ? formatDate(l.expiry_date) : '',
            Number(l.qty_ordered),
            Number(l.qty_delivered),
            Number(l.qty_accepted),
            Number(l.qty_rejected),
            money(l.unit_cost),
            money(l.landed_unit_cost ?? l.unit_cost),
            money(value(l)),
          ]),
          [],
          [null, null, 'Total value received', null, null, null, null, null, null, null, null, null, null, money(total)],
        ],
        widths: [6, 14, 40, 8, 16, 18, 13, 10, 10, 10, 10, 12, 12, 14],
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
