import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError, NoAccess } from '../../components/ui/States'
import { Button, Card, Field, Select } from '../../components/ui/primitives'
import { apiPost, getApiError } from '../../lib/api'
import { useStores } from '../../lib/hooks'
import { formatKes, formatQty } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'

type Row = { product_code: string; batch_number: string; expiry_date: string; qty: string; unit_cost: string; manufacture_date?: string }
type Summary = { reference: string; lines: number; total_qty: string; total_value: string; expired_lines: number }

const REQUIRED = ['product_code', 'batch_number', 'expiry_date', 'qty', 'unit_cost'] as const
const TEMPLATE = 'product_code,batch_number,expiry_date,qty,unit_cost\nA0001,AB1234,2027-06-30,24,1250.00\n'

/** RFC 4180-ish CSV: quoted fields, doubled quotes, commas inside quotes ("2,500"). */
function parseCsv(text: string): string[][] {
  const rows: string[][] = []
  let row: string[] = []
  let cell = ''
  let quoted = false
  const src = text.replace(/^﻿/, '')
  for (let i = 0; i < src.length; i++) {
    const c = src[i]
    if (quoted) {
      if (c === '"' && src[i + 1] === '"') { cell += '"'; i++ }
      else if (c === '"') quoted = false
      else cell += c
    } else if (c === '"') quoted = true
    else if (c === ',') { row.push(cell); cell = '' }
    else if (c === '\n' || c === '\r') {
      if (c === '\r' && src[i + 1] === '\n') i++
      row.push(cell); cell = ''
      if (row.some((v) => v.trim() !== '')) rows.push(row)
      row = []
    } else cell += c
  }
  row.push(cell)
  if (row.some((v) => v.trim() !== '')) rows.push(row)
  return rows
}

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

  const downloadTemplate = () => {
    const url = URL.createObjectURL(new Blob([TEMPLATE], { type: 'text/csv' }))
    const a = document.createElement('a')
    a.href = url
    a.download = 'opening-stock-template.csv'
    a.click()
    URL.revokeObjectURL(url)
  }

  const totalValue = rows.reduce((sum, r) => sum + (Number(r.qty.replace(/,/g, '')) || 0) * (Number(r.unit_cost.replace(/,/g, '')) || 0), 0)

  return (
    <Page>
      <PageHeader
        parent="Inventory"
        title="Opening stock"
        subtitle="Load the go-live stock take once per store. Every row is checked first; one bad row posts nothing (Part 7.1)."
        actions={<Button onClick={downloadTemplate}>Download CSV template</Button>}
      />

      {result && (
        <Card className="mb-4" title="Posted">
          <div className="text-[12.5px] tabular">
            {result.reference}: {result.lines} batch(es), {formatQty(result.total_qty)} units, value {formatKes(result.total_value)}
            {result.expired_lines > 0 && <span className="text-[var(--status-red)]"> · {result.expired_lines} already expired (held, not sellable)</span>}
            {' · '}<Link to="/inventory/stock-on-hand" className="underline">View stock on hand</Link>
          </div>
        </Card>
      )}

      <Card title="1. Choose the store and the file">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Store" required>
            <Select value={storeId} onChange={(e) => { setStoreId(e.target.value); setValidated(false) }}>
              <option value="">Choose…</option>
              {(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
            </Select>
          </Field>
          <Field label="CSV file" hint="Columns: product_code, batch_number, expiry_date (YYYY-MM-DD), qty, unit_cost — per product base unit.">
            <input type="file" accept=".csv,text/csv" className="text-[12px]" onChange={(e) => onFile(e.target.files?.[0])} />
          </Field>
        </div>
        {parsed && parsed.missing.length > 0 && (
          <div className="mt-3 text-[12px] text-[var(--status-red)]">The file is missing column(s): {parsed.missing.join(', ')}.</div>
        )}
      </Card>

      {rows.length > 0 && parsed?.missing.length === 0 && (
        <Card className="mt-4" title={`2. Check ${rows.length} row(s) from ${fileName}`} actions={<span className="text-[12px] tabular">Value at cost ≈ {formatKes(totalValue.toFixed(2))}</span>}>
          <div className="max-h-[420px] overflow-auto">
            <table className="ui-table">
              <thead>
                <tr><th>#</th><th>Product</th><th>Batch</th><th>Expiry</th><th className="text-right">Qty</th><th className="text-right">Unit cost</th><th>Problems</th></tr>
              </thead>
              <tbody>
                {rows.slice(0, 500).map((r, i) => {
                  const problems = rowErrors?.[String(i + 1)]
                  return (
                    <tr key={i} className={problems ? 'bg-[color-mix(in_srgb,var(--status-red)_8%,transparent)]' : ''}>
                      <td className="tabular">{i + 1}</td>
                      <td className="tabular font-semibold">{r.product_code}</td>
                      <td>{r.batch_number}</td>
                      <td className="tabular">{r.expiry_date}</td>
                      <td className="text-right tabular">{r.qty}</td>
                      <td className="text-right tabular">{r.unit_cost}</td>
                      <td className="text-[11px] text-[var(--status-red)]">{problems?.join('; ')}</td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
            {rows.length > 500 && <div className="p-2 text-[11px] text-[var(--text-muted)]">Showing the first 500 rows; all {rows.length} are checked and posted.</div>}
          </div>
          {rowErrors?.['0'] && <div className="mt-2 text-[12px] text-[var(--status-red)]">{rowErrors['0'].join('; ')}</div>}
          {err && !rowErrors && <div className="mt-2"><InlineError error={failure} /></div>}
          <div className="flex justify-end gap-2 mt-3">
            <Button disabled={!storeId || check.isPending} onClick={() => check.mutate()}>{check.isPending ? 'Checking…' : 'Check file'}</Button>
            <Button variant="primary" disabled={!validated || post.isPending} onClick={() => post.mutate()}>{post.isPending ? 'Posting…' : `Post opening stock (${rows.length})`}</Button>
          </div>
        </Card>
      )}
    </Page>
  )
}
