import { useMutation, useQueryClient } from '@tanstack/react-query'
import { AlertCircle, CheckCircle2, Download, ExternalLink } from 'lucide-react'
import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError, NoAccess } from '../../components/ui/States'
import { Button, Card, Field, FileDropzone, Select } from '../../components/ui/primitives'
import { apiPost, getApiError } from '../../lib/api'
import { downloadBlob, parseCsv } from '../../lib/csv'
import { useStores } from '../../lib/hooks'
import { formatKes, formatQty } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'

type Row = { product_code: string; batch_number: string; expiry_date: string; qty: string; unit_cost: string; manufacture_date?: string }
type Summary = { reference: string; lines: number; total_qty: string; total_value: string; expired_lines: number }

const REQUIRED = ['product_code', 'batch_number', 'expiry_date', 'qty', 'unit_cost'] as const
const TEMPLATE = 'product_code,batch_number,expiry_date,qty,unit_cost\nA0001,AB1234,2027-06-30,24,1250.00\n'

function toRows(text: string): { rows: Row[]; missing: string[] } {
  const [header = [], ...body] = parseCsv(text)
  const keys = header.map((h) => h.trim().toLowerCase())
  const missing = REQUIRED.filter((k) => !keys.includes(k))
  const rows = body.map((cells) => Object.fromEntries(keys.map((k, i) => [k, (cells[i] ?? '').trim()])) as Row)
  return { rows, missing }
}

export default function OpeningStockPage() {
  const canPost = usePermission('stock.count.post')
  const queryClient = useQueryClient()
  const stores = useStores()
  const [storeId, setStoreId] = useState('')
  const [fileName, setFileName] = useState('')
  const [text, setText] = useState('')
  const [validated, setValidated] = useState(false)
  const [result, setResult] = useState<Summary | null>(null)

  const parsed = useMemo(() => (text ? toRows(text) : null), [text])
  const rows = parsed?.rows ?? []

  const check = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<{ valid: boolean; lines: number }>('/api/inventory/opening-stock', { store_id: storeId, rows, dry_run: true }),
    onSuccess: (r) => { setValidated(true); toast.success(`${r.lines} row(s) are valid — ready to post`) },
    onError: () => setValidated(false),
  })
  const post = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<Summary>('/api/inventory/opening-stock', { store_id: storeId, rows }),
    onSuccess: (s) => {
      setResult(s)
      setText('')
      setFileName('')
      setValidated(false)
      toast.success(`Opening stock ${s.reference} posted`)
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      queryClient.invalidateQueries({ queryKey: ['batches'] })
    },
  })
  const failure = check.error ?? post.error
  const err = failure ? getApiError(failure) : null
  const rowErrors = (err?.details?.rows ?? null) as Record<string, string[]> | null

  if (!canPost) return <NoAccess permission="stock.count.post" />

  const onFile = async (file: File | undefined) => {
    if (!file) return
    setFileName(file.name)
    setText(await file.text())
    setValidated(false)
    setResult(null)
    check.reset()
    post.reset()
  }

  const downloadTemplate = () => downloadBlob(TEMPLATE, 'opening-stock-template.csv')

  const totalValue = rows.reduce((sum, r) => sum + (Number(r.qty.replace(/,/g, '')) || 0) * (Number(r.unit_cost.replace(/,/g, '')) || 0), 0)

  return (
    <Page>
      <PageHeader
        parent="Inventory"
        title="Opening stock"
        subtitle="Load the go-live stock take once per store. Every row is checked first; one bad row posts nothing (Part 7.1)."
        actions={
          <div id="tour-opening-template">
            <Button onClick={downloadTemplate} className="gap-2">
              <Download size={15} />
              <span>Download CSV template</span>
            </Button>
          </div>
        }
      />

      {result && (
        <div className="p-4 sm:p-5 rounded-2xl bg-emerald-50 border border-emerald-200/80 shadow-xs mb-5 flex items-start gap-3.5">
          <div className="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5">
            <CheckCircle2 size={20} />
          </div>
          <div className="flex-1 min-w-0">
            <h3 className="text-sm font-bold text-emerald-900 leading-tight">Opening stock successfully posted</h3>
            <p className="text-xs sm:text-sm text-emerald-800 mt-1 tabular leading-relaxed">
              Reference <span className="font-mono font-bold">{result.reference}</span>: {result.lines} batch(es) registered, {formatQty(result.total_qty)} units loaded, total valuation of {formatKes(result.total_value)}.
              {result.expired_lines > 0 && (
                <span className="text-rose-700 font-semibold block sm:inline sm:ml-1">
                  ({result.expired_lines} batch(es) already expired — held in quarantine).
                </span>
              )}
            </p>
            <div className="mt-3">
              <Link
                to="/inventory/stock-on-hand"
                className="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-800 hover:text-emerald-950 underline"
              >
                <span>View updated stock on hand</span>
                <ExternalLink size={13} />
              </Link>
            </div>
          </div>
        </div>
      )}

      <div id="tour-opening-store-file">
        <Card padded title="1. Choose the store and the file">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-5 items-start">
            <Field label="Target store" required hint="Select which pharmacy store will receive this initial stock.">
              <Select value={storeId} onChange={(e) => { setStoreId(e.target.value); setValidated(false) }}>
                <option value="">Choose a store…</option>
                {(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
              </Select>
            </Field>
            <Field label="CSV data file" required hint="Columns: product_code, batch_number, expiry_date (YYYY-MM-DD), qty, unit_cost.">
              <FileDropzone
                fileName={fileName}
                onFileSelect={onFile}
                onClear={() => {
                  setFileName('')
                  setText('')
                  setValidated(false)
                }}
                hint="Max 10MB · Validated per product base unit"
              />
            </Field>
          </div>
          {parsed && parsed.missing.length > 0 && (
            <div className="mt-4 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700 font-semibold flex items-center gap-2">
              <AlertCircle size={16} className="shrink-0 text-rose-600" />
              <span>The file is missing required column(s): {parsed.missing.join(', ')}.</span>
            </div>
          )}
        </Card>
      </div>

      {rows.length > 0 && parsed?.missing.length === 0 && (
        <div id="tour-opening-validation">
          <Card
            className="mt-5"
            title={`2. Validate & post ${rows.length} row(s) from ${fileName}`}
            actions={
              <span className="inline-flex items-center px-3 py-1 rounded-lg bg-slate-100 text-xs tabular font-semibold text-slate-700 border border-slate-200/80">
                Value at cost ≈ {formatKes(totalValue.toFixed(2))}
              </span>
            }
          >
          <div className="max-h-[440px] overflow-auto">
            <table className="ui-table">
              <thead>
                <tr>
                  <th className="w-12 text-center">#</th>
                  <th>Product code</th>
                  <th>Batch number</th>
                  <th>Expiry date</th>
                  <th className="text-right">Quantity</th>
                  <th className="text-right">Unit cost</th>
                  <th>Validation status</th>
                </tr>
              </thead>
              <tbody>
                {rows.slice(0, 500).map((r, i) => {
                  const problems = rowErrors?.[String(i + 1)]
                  return (
                    <tr key={i} className={problems ? 'bg-rose-50/70' : undefined}>
                      <td className="text-center tabular text-slate-400 text-xs">{i + 1}</td>
                      <td className="tabular font-mono font-semibold text-slate-900">{r.product_code}</td>
                      <td className="font-mono text-slate-700">{r.batch_number}</td>
                      <td className="tabular text-slate-700">{r.expiry_date}</td>
                      <td className="text-right tabular font-semibold text-slate-900">{r.qty}</td>
                      <td className="text-right tabular font-semibold text-slate-900">{r.unit_cost}</td>
                      <td>
                        {problems ? (
                          <span className="inline-flex items-center gap-1 text-xs text-rose-600 font-semibold">
                            <AlertCircle size={13} /> {problems.join('; ')}
                          </span>
                        ) : validated ? (
                          <span className="inline-flex items-center gap-1 text-xs text-emerald-600 font-semibold">
                            <CheckCircle2 size={13} /> Valid
                          </span>
                        ) : (
                          <span className="text-xs text-slate-400">Ready to check</span>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
            {rows.length > 500 && (
              <div className="p-3 text-xs text-slate-500 bg-slate-50 border-t border-slate-100 text-center font-medium">
                Showing first 500 rows; all {rows.length} rows will be checked and posted.
              </div>
            )}
          </div>
          {rowErrors?.['0'] && (
            <div className="m-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700 font-semibold">
              {rowErrors['0'].join('; ')}
            </div>
          )}
          {err && !rowErrors && (
            <div className="m-4">
              <InlineError error={failure} />
            </div>
          )}
          <div className="flex flex-col-reverse sm:flex-row items-center justify-between gap-3 p-4 sm:p-5 border-t border-slate-100 bg-slate-50/50">
            <span className="text-xs text-slate-500 font-medium">
              {validated ? '✓ All rows are validated. Ready to commit opening stock.' : 'Validate rows first before posting to the ledger.'}
            </span>
            <div className="flex items-center gap-2.5 w-full sm:w-auto justify-end">
              <Button
                disabled={!storeId || check.isPending}
                onClick={() => check.mutate()}
                className="w-full sm:w-auto"
              >
                {check.isPending ? 'Checking…' : 'Check file'}
              </Button>
              <Button
                variant="primary"
                disabled={!validated || post.isPending}
                onClick={() => post.mutate()}
                className="w-full sm:w-auto"
              >
                {post.isPending ? 'Posting…' : `Post opening stock (${rows.length})`}
              </Button>
            </div>
          </div>
        </Card>
      </div>
    )}
  </Page>
  )
}
