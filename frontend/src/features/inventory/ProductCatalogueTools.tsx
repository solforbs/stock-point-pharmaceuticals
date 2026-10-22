import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, FileSpreadsheet, FolderTree, Trash2, Upload } from 'lucide-react'
import { useMemo, useState } from 'react'
import { DeleteRecordButton } from '../../components/DeleteRecordButton'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { Modal } from '../../components/ui/Modal'
import { InlineError } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card, Field, FileDropzone, Input, Select } from '../../components/ui/primitives'
import { api, apiGet, apiPatch, apiPost, ensureCsrfCookie, getApiError } from '../../lib/api'
import { downloadBlob, parseCsv, tableToObjects } from '../../lib/csv'
import { readXlsxRows } from '../../lib/xlsx'
import { buildXlsx } from '../../lib/xlsxWriter'
import { usePermission } from '../../lib/permissions'
import { toast, toastApiError } from '../../lib/toast'

type CategoryRow = {
  id: string
  code: string
  name: string
  parent_id: string | null
  is_active: boolean
  parent: { id: string; code: string; name: string } | null
  products_count: number
}

type ImportChange = { code: string; fields: Record<string, { from: unknown; to: unknown }> }
type ImportSummary = { rows: number; created: number; updated: number; unchanged: number; categories_created: string[]; dry_run: boolean; created_codes: string[]; changes: ImportChange[] }

/** The columns the import reads; any others in the file are ignored. */
const IMPORT_COLUMNS = [
  'code', 'name', 'brand_name', 'sku', 'gtin', 'strength', 'description',
  'category_code', 'category_name', 'parent_category_code', 'parent_category_name',
  'dosage_form_code', 'storage_condition_code', 'base_uom', 'tax_code', 'default_price',
  'reorder_point', 'safety_stock', 'lead_time_days', 'requires_batch', 'is_active',
] as const

/**
 * Part 5 — catalogue maintenance from the Products header: the category
 * tree, and the CSV round trip (export, edit in Excel, import back).
 */
export function ProductCatalogueActions() {
  const canView = usePermission('product.view')
  const canEdit = usePermission('product.edit')
  const canDelete = usePermission('record.delete') && canEdit
  const [deleteAllOpen, setDeleteAllOpen] = useState(false)
  const [priceListOpen, setPriceListOpen] = useState(false)
  const [categoriesOpen, setCategoriesOpen] = useState(false)
  const [importOpen, setImportOpen] = useState(false)
  const [exporting, setExporting] = useState(false)

  if (!canView) return null

  const exportCsv = async () => {
    setExporting(true)
    try {
      const { data } = await api.get<Blob>('/api/products/export', { responseType: 'blob' })
      downloadBlob(data, `products-${new Date().toISOString().slice(0, 10)}.csv`)
    } catch (e) {
      toastApiError(e, 'Export failed')
    } finally {
      setExporting(false)
    }
  }

  return (
    <>
      <Button onClick={() => setCategoriesOpen(true)}><FolderTree size={13} /> Categories</Button>
      <Button onClick={exportCsv} disabled={exporting}><Download size={13} /> {exporting ? 'Exporting…' : 'Export CSV'}</Button>
      {canEdit && <Button onClick={() => setImportOpen(true)}><Upload size={13} /> Import CSV</Button>}
      <Button onClick={() => setPriceListOpen(true)}><FileSpreadsheet size={13} /> Export master list</Button>
      {canDelete && <Button variant="danger" onClick={() => setDeleteAllOpen(true)}><Trash2 size={13} /> Delete all</Button>}
      {priceListOpen && <PriceListDrawer onClose={() => setPriceListOpen(false)} />}
      <CategoriesDrawer open={categoriesOpen} onClose={() => setCategoriesOpen(false)} canEdit={canEdit} />
      {canEdit && <ProductImportDrawer open={importOpen} onClose={() => setImportOpen(false)} onExport={exportCsv} />}
      {canDelete && <DeleteAllProductsModal open={deleteAllOpen} onClose={() => setDeleteAllOpen(false)} />}
    </>
  )
}

const DELETE_ALL_CONFIRMATION = 'DELETE ALL PRODUCTS'

/**
 * Clears the catalogue before a fresh import. The server keeps any product
 * with stock, purchase or sales history behind it and says how many.
 */
function DeleteAllProductsModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [typed, setTyped] = useState('')

  const remove = useMutation({
    meta: { silent: true },
    mutationFn: async () => {
      await ensureCsrfCookie()
      const { data } = await api.delete<{ deleted: number; kept: number }>('/api/products', { data: { confirm: DELETE_ALL_CONFIRMATION } })
      return data
    },
    onSuccess: (r) => {
      toast.success(r.kept > 0
        ? `${r.deleted} products deleted; ${r.kept} kept because they have stock, purchase or sales history`
        : `${r.deleted} products deleted`)
      queryClient.invalidateQueries({ queryKey: ['products'] })
      close()
    },
    onError: (e) => toast.error(getApiError(e).message),
  })

  const close = () => { setTyped(''); remove.reset(); onClose() }

  return (
    <Modal
      open={open}
      onClose={close}
      title="Delete all products?"
      footer={
        <>
          <Button onClick={close}>Cancel</Button>
          <Button variant="danger" disabled={typed !== DELETE_ALL_CONFIRMATION || remove.isPending} onClick={() => remove.mutate()}>
            {remove.isPending ? 'Deleting…' : 'Delete permanently'}
          </Button>
        </>
      }
    >
      <div className="space-y-3">
        <p className="text-sm text-slate-700 leading-relaxed">
          Every product with no history is removed, along with its units and prices, so the catalogue can be imported clean. Products
          that have been received, counted, sold or prescribed are kept — deactivate those instead. Each deleted row is written to the audit log.
        </p>
        <Field label={`Type ${DELETE_ALL_CONFIRMATION} to confirm`}>
          <Input value={typed} onChange={(e) => setTyped(e.target.value)} placeholder={DELETE_ALL_CONFIRMATION} autoFocus />
        </Field>
      </div>
    </Modal>
  )
}

type CategoryForm = { code: string; name: string; parent_id: string; is_active: boolean }

function CategoriesDrawer({ open, onClose, canEdit }: { open: boolean; onClose: () => void; canEdit: boolean }) {
  const queryClient = useQueryClient()
  const list = useQuery({ queryKey: ['product-categories', 'all'], queryFn: () => apiGet<CategoryRow[]>('/api/product-categories/all'), enabled: open })
  const [editing, setEditing] = useState<CategoryRow | 'new' | null>(null)
  const [form, setForm] = useState<CategoryForm>({ code: '', name: '', parent_id: '', is_active: true })
  const set = (patch: Partial<CategoryForm>) => setForm({ ...form, ...patch })

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const body = { code: form.code.trim(), name: form.name.trim(), parent_id: form.parent_id || null, is_active: form.is_active }
      return editing === 'new' || editing === null
        ? apiPost<CategoryRow>('/api/product-categories', body)
        : apiPatch<CategoryRow>(`/api/product-categories/${editing.id}`, body)
    },
    onSuccess: (c) => {
      toast.success(`Category ${c.code} ${editing === 'new' ? 'created' : 'saved'}`)
      queryClient.invalidateQueries({ queryKey: ['product-categories'] })
      setEditing(null)
    },
  })
  const err = save.isError ? getApiError(save.error) : null
  const startNew = () => { setEditing('new'); setForm({ code: '', name: '', parent_id: '', is_active: true }); save.reset() }
  const startEdit = (c: CategoryRow) => { setEditing(c); setForm({ code: c.code, name: c.name, parent_id: c.parent_id ?? '', is_active: c.is_active }); save.reset() }

  const columns: Column<CategoryRow>[] = [
    { key: 'code', header: 'Code', render: (c) => <span className="font-semibold tabular">{c.code}</span>, sortValue: (c) => c.code },
    { key: 'name', header: 'Name', render: (c) => c.name, sortValue: (c) => c.name },
    { key: 'parent', header: 'Parent', render: (c) => c.parent ? `${c.parent.code} · ${c.parent.name}` : '—', sortValue: (c) => c.parent?.code ?? '' },
    { key: 'products', header: 'Products', align: 'right', render: (c) => <span className="tabular">{c.products_count}</span>, sortValue: (c) => c.products_count },
    { key: 'status', header: 'Status', render: (c) => <StatusBadge status={c.is_active ? 'ACTIVE' : 'INACTIVE'} /> },
    {
      key: 'delete',
      header: '',
      align: 'right',
      render: (c) => (
        <DeleteRecordButton type="product-categories" id={c.id} label={`${c.code} · ${c.name}`} permission="product.edit"
          invalidateKeys={[['product-categories', 'all'], ['product-categories']]} />
      ),
    },
  ]

  // A category cannot sit under itself or its own descendants.
  const parentOptions = useMemo(() => {
    const rows = list.data ?? []
    if (editing === null || editing === 'new') return rows
    const blocked = new Set<string>([editing.id])
    let grew = true
    while (grew) {
      grew = false
      for (const r of rows) if (r.parent_id && blocked.has(r.parent_id) && !blocked.has(r.id)) { blocked.add(r.id); grew = true }
    }
    return rows.filter((r) => !blocked.has(r.id))
  }, [list.data, editing])

  return (
    <Drawer open={open} onClose={() => { setEditing(null); onClose() }} title="Product categories" subtitle="Inactive categories stay on existing products but are no longer offered for new ones." width={760}
      actions={canEdit && editing === null ? <Button size="sm" variant="primary" onClick={startNew}>New category</Button> : null}
    >
      <div className="space-y-4">
        {editing !== null && (
          <Card title={editing === 'new' ? 'New category' : `Edit ${editing.code}`}>
            <div className="p-4 space-y-3">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Field label="Code" required error={err?.errors.code?.[0]}><Input value={form.code} onChange={(e) => set({ code: e.target.value.toUpperCase() })} placeholder="ANALG" /></Field>
                <Field label="Name" required error={err?.errors.name?.[0]}><Input value={form.name} onChange={(e) => set({ name: e.target.value })} placeholder="Analgesics" /></Field>
                <Field label="Parent category" error={err?.code === 'CATEGORY_CYCLE' ? err.message : err?.errors.parent_id?.[0]}>
                  <Select value={form.parent_id} onChange={(e) => set({ parent_id: e.target.value })}>
                    <option value="">None (top level)</option>
                    {parentOptions.map((c) => (<option key={c.id} value={c.id}>{c.code} · {c.name}</option>))}
                  </Select>
                </Field>
                <label className="flex items-center gap-2 text-xs font-medium text-slate-700 pt-5"><input type="checkbox" checked={form.is_active} onChange={(e) => set({ is_active: e.target.checked })} /> Active</label>
              </div>
              {err && err.code !== 'CATEGORY_CYCLE' && !Object.keys(err.errors).length && <InlineError error={save.error} />}
              <div className="flex justify-end gap-2">
                <Button onClick={() => setEditing(null)}>Cancel</Button>
                <Button variant="primary" disabled={!form.code.trim() || !form.name.trim() || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Save category'}</Button>
              </div>
            </div>
          </Card>
        )}
        <div className="ui-card">
          <DataTable columns={columns} rows={list.data} rowKey={(c) => c.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()}
            onRowClick={canEdit ? startEdit : undefined} selectedKey={editing && editing !== 'new' ? editing.id : null}
            emptyTitle="No categories yet" emptyHint={canEdit ? 'Create one here, or tick “create missing categories” when importing a product CSV.' : undefined}
          />
        </div>
      </div>
    </Drawer>
  )
}

function ProductImportDrawer({ open, onClose, onExport }: { open: boolean; onClose: () => void; onExport: () => void }) {
  const queryClient = useQueryClient()
  const [fileName, setFileName] = useState('')
  const [table, setTable] = useState<string[][] | null>(null)
  const [readError, setReadError] = useState('')
  const [createCategories, setCreateCategories] = useState(false)
  const [checked, setChecked] = useState<ImportSummary | null>(null)
  const [applied, setApplied] = useState<ImportSummary | null>(null)

  const parsed = useMemo(() => (table ? tableToObjects(table, ['code']) : null), [table])
  const usedColumns = useMemo(() => IMPORT_COLUMNS.filter((c) => parsed?.keys.includes(c)), [parsed])
  const rows = useMemo(() => (parsed?.rows ?? []).map((r) => Object.fromEntries(usedColumns.map((c) => [c, r[c] ?? '']))), [parsed, usedColumns])

  const payload = (dryRun: boolean) => ({ rows, dry_run: dryRun, create_missing_categories: createCategories })
  const check = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<ImportSummary>('/api/products/import', payload(true)),
    onSuccess: (s) => { setChecked(s); toast.success(`${s.rows} row(s) are valid — ${s.created} new, ${s.updated} would change`) },
    onError: () => setChecked(null),
  })
  const apply = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<ImportSummary>('/api/products/import', payload(false)),
    onSuccess: (s) => {
      setApplied(s)
      setChecked(null)
      setTable(null)
      setFileName('')
      toast.success(`${s.created} product(s) created, ${s.updated} updated`)
      queryClient.invalidateQueries({ queryKey: ['products'] })
      queryClient.invalidateQueries({ queryKey: ['product-categories'] })
    },
  })
  const failure = check.error ?? apply.error
  const err = failure ? getApiError(failure) : null
  const rowErrors = (err?.details?.rows ?? null) as Record<string, string[]> | null

  const reset = () => { setChecked(null); check.reset(); apply.reset() }
  const onFile = async (file: File | undefined) => {
    if (!file) return
    setFileName(file.name)
    setApplied(null)
    setReadError('')
    reset()
    try {
      setTable(/\.xlsx$/i.test(file.name) ? await readXlsxRows(file) : parseCsv(await file.text()))
    } catch (e) {
      setTable(null)
      setReadError(e instanceof Error ? e.message : 'The file could not be read.')
    }
  }
  const close = () => { setTable(null); setFileName(''); setReadError(''); setApplied(null); reset(); onClose() }
  const pending = (checked?.created ?? 0) + (checked?.updated ?? 0)

  return (
    <Drawer open={open} onClose={close} title="Import products" subtitle="New codes create products; existing codes are updated. A blank cell leaves that value unchanged; one bad row changes nothing." width={980}>
      <div className="space-y-4">
        {applied && (
          <Card title="Imported">
            <div className="p-4 text-sm">
              {applied.created} product(s) created, {applied.updated} updated, {applied.unchanged} already matched the file.
              {applied.categories_created.length > 0 && <> Created categories: {applied.categories_created.join(', ')}.</>}
            </div>
          </Card>
        )}

        <Card padded title="1. Choose the Excel or CSV file">
          <div className="space-y-4">
            <div className="text-xs text-slate-600 leading-relaxed bg-slate-50 p-3 rounded-xl border border-slate-100">
              Start from <button type="button" className="text-blue-600 hover:text-blue-700 hover:underline font-semibold" onClick={onExport}>Export CSV</button>, edit it in Excel and import it back as .xlsx or .csv (the first sheet is read; row 1 holds the column names).
              <span className="block text-slate-500 mt-1">Columns read: {IMPORT_COLUMNS.join(', ')}. A new code needs name and base_uom. requires_batch and is_active take 1/0 or yes/no.</span>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
              <Field label="Product file" required hint="Excel .xlsx, or UTF-8 CSV">
                <FileDropzone
                  accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                  fileName={fileName}
                  onFileSelect={(file) => void onFile(file)}
                  onClear={() => {
                    setFileName('')
                    setTable(null)
                    setReadError('')
                    reset()
                  }}
                  hint="Click to browse or drop your .xlsx or .csv file"
                />
              </Field>
              <div className="pt-2 sm:pt-6">
                <label className="flex items-start gap-2.5 p-3 rounded-xl border border-slate-200/80 bg-white hover:bg-slate-50 cursor-pointer select-none">
                  <input
                    type="checkbox"
                    checked={createCategories}
                    onChange={(e) => { setCreateCategories(e.target.checked); reset() }}
                    className="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                  />
                  <span className="text-xs text-slate-700 leading-relaxed">
                    <strong>Auto-create missing categories</strong> (named after their code; rename them later under Categories)
                  </span>
                </label>
              </div>
            </div>
            {readError && <div className="text-xs text-rose-600 font-medium">{readError}</div>}
            {parsed && parsed.missing.length > 0 && <div className="text-xs text-rose-600 font-medium">The file has no “code” column, so no product can be matched.</div>}
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
                        {usedColumns.map((c) => <td key={c} className={c === 'code' ? 'tabular font-mono font-semibold' : 'tabular'}>{r[c] || <span className="text-slate-400">·</span>}</td>)}
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
              {checked && <ChangePreview summary={checked} />}
              <div className="flex justify-end gap-2">
                <Button disabled={check.isPending} onClick={() => check.mutate()}>{check.isPending ? 'Checking…' : 'Check file'}</Button>
                <Button variant="primary" disabled={!checked || pending === 0 || apply.isPending} onClick={() => apply.mutate()}>
                  {apply.isPending ? 'Applying…' : `Apply (${pending} product${pending === 1 ? '' : 's'})`}
                </Button>
              </div>
            </div>
          </Card>
        )}
      </div>
    </Drawer>
  )
}

function ChangePreview({ summary }: { summary: ImportSummary }) {
  const show = (v: unknown) => (v === null || v === undefined || v === '' ? '—' : typeof v === 'boolean' ? (v ? 'yes' : 'no') : String(v))
  return (
    <div className="rounded-md border border-slate-200 p-3 text-xs space-y-2">
      <div className="font-semibold">
        {summary.created} new product(s), {summary.updated} will change, {summary.unchanged} already match.
        {summary.categories_created.length > 0 && <span className="font-normal"> New categories: {summary.categories_created.join(', ')}.</span>}
      </div>
      {summary.changes.length > 0 && (
        <div className="max-h-[200px] overflow-auto">
          <table className="ui-table">
            <thead><tr><th>Product</th><th>Field</th><th>From</th><th>To</th></tr></thead>
            <tbody>
              {summary.changes.slice(0, 300).flatMap((c) => Object.entries(c.fields).map(([field, v]) => (
                <tr key={`${c.code}-${field}`}>
                  <td className="tabular font-mono font-semibold">{c.code}</td>
                  <td>{field}</td>
                  <td className="tabular text-slate-500">{show(v.from)}</td>
                  <td className="tabular">{show(v.to)}</td>
                </tr>
              )))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

type PriceListColumn = 'code' | 'name' | 'brand_name' | 'price'
type PriceListPayment = {
  mpesa_paybill: string | null
  mpesa_account: string | null
  mpesa_till: string | null
  note: string | null
  account_name: string
  banks: { bank_name: string | null; account_name: string | null; account_number: string | null; branch_name: string | null }[]
}
type PriceListData = {
  title: string
  columns: Record<PriceListColumn, string>
  payment: PriceListPayment
  products: { id: string; code: string; name: string; brand_name: string | null; default_price: string | null }[]
}

const SHOWN_ROWS = 300

/** The payment block as text lines (a blank line and a heading first), for the Excel file and the on-screen preview. */
function paymentLines(p: PriceListPayment): string[] {
  const lines: string[] = []
  if (p.mpesa_paybill || p.mpesa_till) {
    lines.push(`LIPA NA M-PESA${p.note ? ` (${p.note})` : ''}`)
    if (p.mpesa_paybill) lines.push(`Business number (paybill): ${p.mpesa_paybill}${p.mpesa_account ? ` · Account number: ${p.mpesa_account}` : ''}`)
    if (p.mpesa_till) lines.push(`Buy Goods till: ${p.mpesa_till}`)
    lines.push(`Account name: ${p.account_name}`)
  }
  for (const b of p.banks) {
    lines.push(`Bank: ${[b.bank_name, b.branch_name].filter(Boolean).join(', ')} · A/C ${b.account_number ?? ''} · ${b.account_name || p.account_name}`)
  }
  return lines.length ? ['', 'HOW TO PAY', ...lines] : []
}

/**
 * The master price list, cut for one recipient: choose the columns, fill in
 * any product without a price, adjust prices for this list only (a typed
 * price, or every saved price by a percentage), then download Excel or PDF.
 * Nothing is written back — the saved prices stay as they are.
 */
function PriceListDrawer({ onClose }: { onClose: () => void }) {
  const data = useQuery({ queryKey: ['products', 'price-list'], queryFn: () => apiGet<PriceListData>('/api/products/price-list') })
  const [columns, setColumns] = useState<PriceListColumn[]>(['name', 'brand_name', 'price'])
  const [adjustPct, setAdjustPct] = useState('')
  const [overrides, setOverrides] = useState<Record<string, string>>({})
  const [search, setSearch] = useState('')
  const [onlyMissing, setOnlyMissing] = useState(false)
  const [busy, setBusy] = useState<'xlsx' | 'pdf' | null>(null)

  const products = data.data?.products ?? []
  const pct = Number(adjustPct) || 0
  /** The price on this list: a typed price as typed, otherwise the saved price adjusted by the percentage. */
  const priceOf = (p: PriceListData['products'][number]): number | null => {
    const typed = overrides[p.id]
    if (typed !== undefined && typed !== '') return Number(typed)
    if (p.default_price === null || p.default_price === '') return null
    return Math.round(Number(p.default_price) * (1 + pct / 100) * 100) / 100
  }
  const missing = products.filter((p) => priceOf(p) === null)
  const visible = useMemo(() => {
    const term = search.trim().toLowerCase()
    return products.filter((p) => (!onlyMissing || (p.default_price === null && !overrides[p.id]))
      && (!term || [p.code, p.name, p.brand_name].some((v) => v && v.toLowerCase().includes(term))))
  }, [products, search, onlyMissing, overrides])

  const ordered = (['code', 'name', 'brand_name', 'price'] as PriceListColumn[]).filter((c) => columns.includes(c))
  const rows = () => products.map((p) => ({ code: p.code, name: p.name, brand_name: p.brand_name ?? '', price: priceOf(p) }))
  const fileName = (ext: string) => `${data.data?.title ?? 'Price list'} ${new Date().toISOString().slice(0, 10)}.${ext}`

  const downloadExcel = () => {
    if (!data.data) return
    setBusy('xlsx')
    try {
      const labels = data.data.columns
      const blob = buildXlsx({
        sheetName: 'Price List',
        title: data.data.title,
        // Every row is numbered, as on the PDF.
        header: ['No.', ...ordered.map((c) => labels[c])],
        rows: [...rows().map((r, i) => [String(i + 1), ...ordered.map((c) => (c === 'price' ? r.price : r[c]))]), ...paymentLines(data.data.payment).map((line) => [line])],
        widths: [6, ...ordered.map((c) => (c === 'name' ? 60 : c === 'brand_name' ? 24 : c === 'price' ? 14 : 14))],
      })
      downloadBlob(blob, fileName('xlsx'), blob.type)
      toast.success('Price list downloaded', `${products.length} products, Excel`)
    } finally {
      setBusy(null)
    }
  }

  const downloadPdf = async () => {
    setBusy('pdf')
    try {
      await ensureCsrfCookie()
      const { data: pdf } = await api.post<Blob>('/api/products/price-list/pdf', { columns: ordered, rows: rows() }, { responseType: 'blob' })
      downloadBlob(pdf, fileName('pdf'), 'application/pdf')
      toast.success('Price list downloaded', `${products.length} products, PDF`)
    } catch (e) {
      toastApiError(e, 'The PDF could not be made')
    } finally {
      setBusy(null)
    }
  }

  return (
    <Drawer open onClose={onClose} title="Export master list" subtitle={data.data?.title} width={980}>
      <div className="space-y-4">
        {data.isLoading && <p className="text-sm text-slate-600">Loading products…</p>}
        {data.isError && <InlineError error={data.error} />}
        {data.data && (
          <>
            <Card padded title="1. Columns">
              <div className="flex flex-wrap gap-4">
                {(Object.keys(data.data.columns) as PriceListColumn[]).map((c) => (
                  <label key={c} className="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" checked={columns.includes(c)} onChange={(e) => setColumns(e.target.checked ? [...columns, c] : columns.filter((x) => x !== c))} />
                    {data.data.columns[c]}
                  </label>
                ))}
              </div>
            </Card>

            {columns.includes('price') && (
              <Card padded title="2. Prices for this list">
                <div className="space-y-3">
                  <p className="text-xs text-slate-600 leading-relaxed">
                    These prices apply to this download only — the products' saved prices do not change. Adjust every saved price by a percentage (e.g. 20 for a list going outside Nairobi), and type a price for any product to set it exactly.
                  </p>
                  <div className="flex flex-wrap items-end gap-3">
                    <Field label="Adjust saved prices by (%)" hint="Negative lowers them. Typed prices are used as typed.">
                      <Input value={adjustPct} inputMode="decimal" placeholder="0" className="w-32 tabular text-right" onChange={(e) => setAdjustPct(e.target.value.replace(/[^\d.-]/g, ''))} />
                    </Field>
                    <Field label="Find a product"><Input value={search} placeholder="Code, name or brand" onChange={(e) => setSearch(e.target.value)} /></Field>
                    <label className="flex items-center gap-2 text-sm pb-2 cursor-pointer">
                      <input type="checkbox" checked={onlyMissing} onChange={(e) => setOnlyMissing(e.target.checked)} />
                      Only products with no price ({products.filter((p) => p.default_price === null).length})
                    </label>
                  </div>
                  {missing.length > 0 && <p className="text-xs text-amber-700 font-medium">{missing.length} product(s) still have no price; they will show “—” unless you type one.</p>}
                  <div className="max-h-[360px] overflow-auto rounded-xl border border-slate-200">
                    <table className="ui-table">
                      <thead><tr><th>Code</th><th>Description</th><th>Brand</th><th className="text-right">Saved price</th><th className="text-right">Price on this list</th></tr></thead>
                      <tbody>
                        {visible.slice(0, SHOWN_ROWS).map((p) => (
                          <tr key={p.id} className={priceOf(p) === null ? 'bg-amber-50/60' : ''}>
                            <td className="tabular font-mono text-xs font-semibold">{p.code}</td>
                            <td>{p.name}</td>
                            <td>{p.brand_name ?? '—'}</td>
                            <td className="text-right tabular text-slate-500">{p.default_price === null ? '—' : Number(p.default_price).toFixed(2)}</td>
                            <td className="text-right">
                              <input
                                value={overrides[p.id] ?? ''}
                                placeholder={priceOf(p) === null ? 'enter price' : priceOf(p)!.toFixed(2)}
                                inputMode="decimal"
                                onChange={(e) => setOverrides({ ...overrides, [p.id]: e.target.value.replace(/[^\d.]/g, '') })}
                                className="ui-input h-7 w-28 tabular text-right"
                              />
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                    {visible.length > SHOWN_ROWS && <div className="p-2 text-xs text-slate-500">Showing {SHOWN_ROWS} of {visible.length}. Search to find others; every product is included in the download.</div>}
                  </div>
                </div>
              </Card>
            )}

            <Card padded title="How to pay (printed at the foot of the list)">
              {paymentLines(data.data.payment).length > 2
                ? <div className="text-sm text-slate-700 space-y-0.5">{paymentLines(data.data.payment).slice(2).map((line) => <div key={line}>{line}</div>)}</div>
                : <p className="text-sm text-amber-700">No payment details yet. Add the M-PESA paybill and account under Admin → Company Profile, and bank accounts under Finance → Bank Accounts.</p>}
            </Card>

            <Card padded title={`3. Download (${products.length} products in stock)`}>
              <div className="flex flex-wrap gap-2">
                <Button variant="primary" disabled={ordered.length === 0 || busy !== null} onClick={downloadExcel}><Download size={13} /> {busy === 'xlsx' ? 'Preparing…' : 'Excel (.xlsx)'}</Button>
                <Button variant="primary" disabled={ordered.length === 0 || busy !== null} onClick={() => void downloadPdf()}><Download size={13} /> {busy === 'pdf' ? 'Preparing PDF…' : 'PDF'}</Button>
              </div>
              {ordered.length === 0 && <p className="text-xs text-rose-600 mt-2">Choose at least one column.</p>}
            </Card>
          </>
        )}
      </div>
    </Drawer>
  )
}
