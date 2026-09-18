import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, FolderTree, Upload } from 'lucide-react'
import { useMemo, useState } from 'react'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { InlineError } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card, Field, Input, Select } from '../../components/ui/primitives'
import { api, apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { csvToObjects, downloadBlob } from '../../lib/csv'
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
type ImportSummary = { rows: number; updated: number; unchanged: number; categories_created: string[]; dry_run: boolean; changes: ImportChange[] }

/** The columns the import reads; any others in the file (name, default_price) are ignored. */
const IMPORT_COLUMNS = ['code', 'category_code', 'tax_code', 'reorder_point', 'safety_stock', 'lead_time_days', 'generic_name', 'strength', 'is_active'] as const

/**
 * Part 5 — catalogue maintenance from the Products header: the category
 * tree, and the CSV round trip (export, edit in Excel, import back).
 */
export function ProductCatalogueActions() {
  const canView = usePermission('product.view')
  const canEdit = usePermission('product.edit')
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
      <CategoriesDrawer open={categoriesOpen} onClose={() => setCategoriesOpen(false)} canEdit={canEdit} />
      {canEdit && <ProductImportDrawer open={importOpen} onClose={() => setImportOpen(false)} onExport={exportCsv} />}
    </>
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
                <label className="flex items-center gap-2 text-[12px] pt-5"><input type="checkbox" checked={form.is_active} onChange={(e) => set({ is_active: e.target.checked })} /> Active</label>
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
  const [text, setText] = useState('')
  const [createCategories, setCreateCategories] = useState(false)
  const [checked, setChecked] = useState<ImportSummary | null>(null)
  const [applied, setApplied] = useState<ImportSummary | null>(null)

  const parsed = useMemo(() => (text ? csvToObjects(text, ['code']) : null), [text])
  const usedColumns = useMemo(() => IMPORT_COLUMNS.filter((c) => parsed?.keys.includes(c)), [parsed])
  const rows = useMemo(() => (parsed?.rows ?? []).map((r) => Object.fromEntries(usedColumns.map((c) => [c, r[c] ?? '']))), [parsed, usedColumns])

  const payload = (dryRun: boolean) => ({ rows, dry_run: dryRun, create_missing_categories: createCategories })
  const check = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<ImportSummary>('/api/products/import', payload(true)),
    onSuccess: (s) => { setChecked(s); toast.success(`${s.rows} row(s) are valid — ${s.updated} product(s) would change`) },
    onError: () => setChecked(null),
  })
  const apply = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<ImportSummary>('/api/products/import', payload(false)),
    onSuccess: (s) => {
      setApplied(s)
      setChecked(null)
      setText('')
      setFileName('')
      toast.success(`${s.updated} product(s) updated`)
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
    setText(await file.text())
    setApplied(null)
    reset()
  }
  const close = () => { setText(''); setFileName(''); setApplied(null); reset(); onClose() }

  return (
    <Drawer open={open} onClose={close} title="Import product attributes" subtitle="Updates existing products only. A blank cell leaves that value unchanged; one bad row changes nothing." width={980}>
      <div className="space-y-4">
        {applied && (
          <Card title="Imported">
            <div className="p-4 text-[12.5px]">
              {applied.updated} product(s) updated, {applied.unchanged} already matched the file.
              {applied.categories_created.length > 0 && <> Created categories: {applied.categories_created.join(', ')}.</>}
            </div>
          </Card>
        )}

        <Card title="1. Choose the file">
          <div className="p-4 space-y-3">
            <div className="text-[12px] text-[var(--text-secondary)]">
              Start from <button type="button" className="underline font-semibold" onClick={onExport}>Export CSV</button>, edit it in Excel and save as CSV.
              Columns read: {IMPORT_COLUMNS.join(', ')}. Others (name, default_price) are ignored. is_active takes 1/0 or yes/no.
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
              <Field label="CSV file" required>
                <input type="file" accept=".csv,text/csv" className="text-[12px]" onChange={(e) => { void onFile(e.target.files?.[0]); e.target.value = '' }} />
              </Field>
              <label className="flex items-center gap-2 text-[12px]">
                <input type="checkbox" checked={createCategories} onChange={(e) => { setCreateCategories(e.target.checked); reset() }} />
                Create missing categories (named after their code; rename them under Categories)
              </label>
            </div>
            {parsed && parsed.missing.length > 0 && <div className="text-[12px] text-[var(--status-red)]">The file has no “code” column, so no product can be matched.</div>}
            {parsed && parsed.missing.length === 0 && rows.length === 0 && <div className="text-[12px] text-[var(--status-red)]">The file has a header but no rows.</div>}
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
                      <tr key={i} className={problems ? 'bg-[color-mix(in_srgb,var(--status-red)_8%,transparent)]' : ''}>
                        <td className="tabular">{i + 1}</td>
                        {usedColumns.map((c) => <td key={c} className={c === 'code' ? 'tabular font-semibold' : 'tabular'}>{r[c] || <span className="text-[var(--text-muted)]">·</span>}</td>)}
                        <td className="text-[11px] text-[var(--status-red)]">{problems?.join('; ')}</td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
              {rows.length > 500 && <div className="p-2 text-[11px] text-[var(--text-muted)]">Showing the first 500 rows; all {rows.length} are checked and applied.</div>}
            </div>
            <div className="p-3 space-y-2">
              {rowErrors?.['0'] && <div className="text-[12px] text-[var(--status-red)]">{rowErrors['0'].join('; ')}</div>}
              {err && !rowErrors && <InlineError error={failure} />}
              {checked && <ChangePreview summary={checked} />}
              <div className="flex justify-end gap-2">
                <Button disabled={check.isPending} onClick={() => check.mutate()}>{check.isPending ? 'Checking…' : 'Check file'}</Button>
                <Button variant="primary" disabled={!checked || checked.updated === 0 || apply.isPending} onClick={() => apply.mutate()}>
                  {apply.isPending ? 'Applying…' : `Apply (${checked?.updated ?? 0} product${checked?.updated === 1 ? '' : 's'})`}
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
    <div className="rounded-md border border-[var(--border)] p-3 text-[12px] space-y-2">
      <div className="font-semibold">
        {summary.updated} product(s) will change, {summary.unchanged} already match.
        {summary.categories_created.length > 0 && <span className="font-normal"> New categories: {summary.categories_created.join(', ')}.</span>}
      </div>
      {summary.changes.length > 0 && (
        <div className="max-h-[200px] overflow-auto">
          <table className="ui-table">
            <thead><tr><th>Product</th><th>Field</th><th>From</th><th>To</th></tr></thead>
            <tbody>
              {summary.changes.slice(0, 300).flatMap((c) => Object.entries(c.fields).map(([field, v]) => (
                <tr key={`${c.code}-${field}`}>
                  <td className="tabular font-semibold">{c.code}</td>
                  <td>{field}</td>
                  <td className="tabular text-[var(--text-muted)]">{show(v.from)}</td>
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
