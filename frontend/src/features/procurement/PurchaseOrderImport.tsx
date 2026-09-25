import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Download, Upload } from 'lucide-react'
import { useMemo, useState } from 'react'
import { InlineError } from '../../components/ui/States'
import { Drawer } from '../../components/ui/Drawer'
import { Button, Card, Field, FileDropzone } from '../../components/ui/primitives'
import { apiPost, getApiError } from '../../lib/api'
import { csvToObjects, downloadBlob } from '../../lib/csv'
import { formatMoney } from '../../lib/money'
import { toast } from '../../lib/toast'

type ImportedPo = { id?: string; doc_number?: string; supplier_code: string; supplier_name: string; expected_date: string | null; lines: number; total: string }
type ImportSummary = { rows: number; dry_run: boolean; purchase_orders: ImportedPo[] }

/** The columns the import reads; any others in the file are ignored. */
const IMPORT_COLUMNS = ['supplier_code', 'product_code', 'qty', 'unit_price', 'trade_price', 'discount_pct', 'uom_code', 'expected_date'] as const

const TEMPLATE =
  IMPORT_COLUMNS.join(',') +
  '\nPDL,AMOX500,10,420,,,BOX,2026-10-01\nPDL,PARA500,5,,150,12.5,,\n'

/**
 * Purchase orders from a spreadsheet: rows grouped by supplier_code, one
 * draft PO per supplier, checked with per-row errors before anything is
 * created. The normal approve → send flow still applies afterwards.
 */
export function PurchaseOrderImportDrawer({ open, onClose }: { open: boolean; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [fileName, setFileName] = useState('')
  const [text, setText] = useState('')
  const [checked, setChecked] = useState<ImportSummary | null>(null)
  const [applied, setApplied] = useState<ImportSummary | null>(null)

  const parsed = useMemo(() => (text ? csvToObjects(text, ['supplier_code', 'product_code', 'qty']) : null), [text])
  const usedColumns = useMemo(() => IMPORT_COLUMNS.filter((c) => parsed?.keys.includes(c)), [parsed])
  const rows = useMemo(() => (parsed?.rows ?? []).map((r) => Object.fromEntries(usedColumns.map((c) => [c, r[c] ?? '']))), [parsed, usedColumns])

  const check = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<ImportSummary>('/api/purchase-orders/import', { rows, dry_run: true }),
    onSuccess: (s) => { setChecked(s); toast.success(`${s.rows} row(s) are valid — ${s.purchase_orders.length} purchase order(s) would be created`) },
    onError: () => setChecked(null),
  })
  const apply = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<ImportSummary>('/api/purchase-orders/import', { rows, dry_run: false }),
    onSuccess: (s) => {
      setApplied(s)
      setChecked(null)
      setText('')
      setFileName('')
      toast.success(`${s.purchase_orders.length} draft purchase order(s) created`)
      queryClient.invalidateQueries({ queryKey: ['purchase-orders'] })
    },
  })
  const failure = check.error ?? apply.error
  const err = failure ? getApiError(failure) : null
  const rowErrors = (err?.details?.rows ?? null) as Record<string, string[]> | null

  const reset = () => { setChecked(null); check.reset(); apply.reset() }
  const onFile = async (file: File | undefined) => {
    if (!file) return
    setFileName(file.name)
    setText(await file.text())
    setApplied(null)
    reset()
  }
  const close = () => { setText(''); setFileName(''); setApplied(null); reset(); onClose() }

  return (
    <Drawer open={open} onClose={close} title="Import purchase orders" subtitle="One draft PO per supplier_code in the file. One bad row creates nothing." width={980}>
      <div className="space-y-4">
        {applied && (
          <Card title="Imported">
            <div className="p-4 text-sm space-y-1">
              {applied.purchase_orders.map((po) => (
                <div key={po.doc_number}>
                  <span className="font-mono font-semibold text-blue-600">{po.doc_number}</span>
                  {' '}— {po.supplier_name}, {po.lines} line(s), {formatMoney(po.total)} (draft — approve and send as usual)
                </div>
              ))}
            </div>
          </Card>
        )}

        <Card padded title="1. Choose the CSV file">
          <div className="space-y-4">
            <div className="text-xs text-slate-600 leading-relaxed bg-slate-50 p-3 rounded-xl border border-slate-100">
              Fill the order book in Excel and save as standard CSV format. Each row is one line of an order; rows sharing a supplier_code become one purchase order.
              <span className="block text-slate-500 mt-1">
                Columns: {IMPORT_COLUMNS.join(', ')}. Give a unit_price, or a trade_price with an optional discount_pct.
                uom_code left blank uses the product's purchase unit. Prices are per the unit ordered.
              </span>
              <button type="button" className="mt-1.5 inline-flex items-center gap-1 text-blue-600 hover:text-blue-700 hover:underline font-semibold"
                onClick={() => downloadBlob(TEMPLATE, 'purchase-orders-template.csv')}>
                <Download size={12} /> Download template
              </button>
            </div>
            <Field label="CSV file" required hint="Max size 10MB · UTF-8 CSV format">
              <FileDropzone
                fileName={fileName}
                onFileSelect={(file) => void onFile(file)}
                onClear={() => { setFileName(''); setText(''); reset() }}
                hint="Click to browse or drop your CSV file"
              />
            </Field>
            {parsed && parsed.missing.length > 0 && (
              <div className="text-xs text-rose-600 font-medium">The file is missing the {parsed.missing.join(', ')} column(s).</div>
            )}
            {parsed && parsed.missing.length === 0 && rows.length === 0 && <div className="text-xs text-rose-600 font-medium">The file has a header but no rows.</div>}
          </div>
        </Card>

        {rows.length > 0 && parsed?.missing.length === 0 && (
          <Card title={`2. Check ${rows.length} row(s) from ${fileName}`}>
            <div className="max-h-[380px] overflow-auto">
              <table className="ui-table">
                <thead>
                  <tr><th>#</th>{usedColumns.map((c) => <th key={c}>{c}</th>)}<th>Problems</th></tr>
                </thead>
                <tbody>
                  {rows.slice(0, 500).map((r, i) => {
                    const problems = rowErrors?.[String(i + 1)]
                    return (
                      <tr key={i} className={problems ? 'bg-rose-50/60' : ''}>
                        <td className="tabular">{i + 1}</td>
                        {usedColumns.map((c) => (
                          <td key={c} className={c === 'supplier_code' || c === 'product_code' ? 'tabular font-mono font-semibold' : 'tabular'}>
                            {r[c] || <span className="text-slate-400">·</span>}
                          </td>
                        ))}
                        <td className="text-xs text-rose-600 font-medium">{problems?.join('; ')}</td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
              {rows.length > 500 && <div className="p-2 text-xs text-slate-500">Showing the first 500 rows; all {rows.length} are checked and applied.</div>}
            </div>
            <div className="p-3 space-y-2">
              {rowErrors?.['0'] && <div className="text-xs text-rose-600 font-medium">{rowErrors['0'].join('; ')}</div>}
              {err && !rowErrors && <InlineError error={failure} />}
              {checked && (
                <div className="rounded-md border border-slate-200 p-3 text-xs space-y-1">
                  <div className="font-semibold">{checked.purchase_orders.length} draft purchase order(s) will be created:</div>
                  {checked.purchase_orders.map((po) => (
                    <div key={po.supplier_code}>
                      <span className="font-mono font-semibold">{po.supplier_code}</span> · {po.supplier_name} — {po.lines} line(s), {formatMoney(po.total)}
                      {po.expected_date && <span className="text-slate-500"> · expected {po.expected_date}</span>}
                    </div>
                  ))}
                </div>
              )}
              <div className="flex justify-end gap-2">
                <Button disabled={check.isPending} onClick={() => check.mutate()}>{check.isPending ? 'Checking…' : 'Check file'}</Button>
                <Button variant="primary" disabled={!checked || apply.isPending} onClick={() => apply.mutate()}>
                  {apply.isPending ? 'Importing…' : `Create ${checked?.purchase_orders.length ?? 0} draft PO(s)`}
                </Button>
              </div>
            </div>
          </Card>
        )}
      </div>
    </Drawer>
  )
}

export function ImportPurchaseOrdersButton() {
  const [open, setOpen] = useState(false)
  return (
    <>
      <Button onClick={() => setOpen(true)}><Upload size={13} /> Import CSV</Button>
      <PurchaseOrderImportDrawer open={open} onClose={() => setOpen(false)} />
    </>
  )
}
