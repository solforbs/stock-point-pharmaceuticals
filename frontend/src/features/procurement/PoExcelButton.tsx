import { FileSpreadsheet } from 'lucide-react'
import { useState } from 'react'
import { Button } from '../../components/ui/primitives'
import { apiGet } from '../../lib/api'
import { downloadBlob } from '../../lib/csv'
import { formatDate } from '../../lib/format'
import { toastApiError } from '../../lib/toast'
import type { PurchaseOrder } from '../../lib/types'
import { buildXlsx } from '../../lib/xlsxWriter'

const money = (v: string | number | null | undefined) => (v === null || v === undefined || v === '' ? null : Math.round(Number(v) * 100) / 100)

/** The purchase order as an Excel workbook: the same lines, transport and totals as its PDF, built here from the order. */
export function PoExcelButton({ id, docNumber, size = 'sm' }: { id: string; docNumber: string; size?: 'sm' | 'md' }) {
  const [busy, setBusy] = useState(false)

  const download = async () => {
    setBusy(true)
    try {
      const po = await apiGet<PurchaseOrder>(`/api/purchase-orders/${id}`)
      const lines = po.lines ?? []
      const goods = lines.reduce((s, l) => s + Number(l.qty_ordered) * Number(l.unit_price ?? 0), 0)
      const transport = Number(po.transport_cost ?? 0)
      const details = [
        po.supplier?.name,
        po.created_at ? `raised ${formatDate(po.created_at)}` : null,
        po.expected_date ? `expected ${formatDate(po.expected_date)}` : null,
        po.status,
      ].filter(Boolean).join(' · ')

      const blob = buildXlsx({
        sheetName: docNumber,
        title: `Purchase order ${docNumber} · ${details}`,
        header: ['No.', 'Code', 'Product', 'Unit', 'Qty', 'Unit price', 'Transport per unit', 'Landed price', 'Line total'],
        rows: [
          ...lines.map((l, i) => [
            i + 1,
            l.product?.code ?? '',
            l.product?.name ?? l.product_id,
            l.uom?.code ?? '',
            Number(l.qty_ordered),
            money(l.unit_price),
            money(l.transport_per_unit ?? 0),
            money(l.landed_unit_price ?? l.unit_price),
            money(Number(l.qty_ordered) * Number(l.unit_price ?? 0)),
          ]),
          [],
          [null, null, 'Goods', null, null, null, null, null, money(goods)],
          [null, null, 'Transport', null, null, null, null, null, money(transport)],
          [null, null, 'Total incl. transport', null, null, null, null, null, money(goods + transport)],
        ],
        widths: [6, 14, 44, 10, 10, 14, 16, 14, 16],
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
