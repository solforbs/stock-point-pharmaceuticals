import { FileSpreadsheet } from 'lucide-react'
import { useState } from 'react'
import { Button } from '../../components/ui/primitives'
import { apiGet } from '../../lib/api'
import { downloadBlob } from '../../lib/csv'
import { formatDate, titleCase } from '../../lib/format'
import { xlsxLetterhead } from '../../lib/letterhead'
import { toastApiError } from '../../lib/toast'
import type { Requisition } from '../../lib/types'
import { buildXlsx } from '../../lib/xlsxWriter'

const money = (v: string | number | null | undefined) => (v === null || v === undefined || v === '' ? null : Math.round(Number(v) * 100) / 100)

/** The purchase request (requisition) as an editable Excel workbook: the same letterhead, lines and estimated costs as its PDF. */
export function PrExcelButton({ id, docNumber, size = 'sm' }: { id: string; docNumber: string; size?: 'sm' | 'md' }) {
  const [busy, setBusy] = useState(false)

  const download = async () => {
    setBusy(true)
    try {
      const [pr, letterhead] = await Promise.all([apiGet<Requisition>(`/api/requisitions/${id}`), xlsxLetterhead()])
      const lines = pr.lines ?? []
      const details = [
        pr.created_at ? `raised ${formatDate(pr.created_at)}` : null,
        pr.needed_by ? `needed by ${formatDate(pr.needed_by)}` : null,
        pr.requester?.name ? `requested by ${pr.requester.name}` : null,
        titleCase(pr.status),
      ].filter(Boolean).join(' · ')

      const blob = buildXlsx({
        sheetName: docNumber,
        letterhead,
        title: `Purchase request ${docNumber} · ${details}`,
        header: ['No.', 'Code', 'Product', 'Unit', 'Qty requested', 'Est. unit cost', 'Est. amount', 'Notes'],
        rows: [
          ...lines.map((l, i) => [
            i + 1,
            l.product?.code ?? '',
            l.product?.name ?? l.product_id,
            l.product?.base_uom?.code ?? '',
            Number(l.qty_requested),
            money(l.estimated_unit_cost),
            l.estimated_unit_cost == null ? null : money(Number(l.qty_requested) * Number(l.estimated_unit_cost)),
            l.notes ?? '',
          ]),
          [],
          [null, null, 'Estimated total', null, null, null, money(pr.estimated_total ?? 0)],
          ...(pr.notes ? [[], [`Notes: ${pr.notes}`]] : []),
        ],
        widths: [6, 14, 44, 10, 14, 14, 16, 30],
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
