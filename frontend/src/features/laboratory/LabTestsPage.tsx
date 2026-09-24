import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { FlaskConical, Plus, Search } from 'lucide-react'
import { useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { Modal } from '../../components/ui/Modal'
import { Page, PageHeader, FilterBar } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { Badge } from '../../components/ui/Badge'
import { Button, DrawerFooter, Field, FormSection, Input, PrimaryAction, Select, Textarea } from '../../components/ui/primitives'
import { InlineError, NoAccess } from '../../components/ui/States'
import { apiGet, apiPatch, apiPost } from '../../lib/api'
import { usePermissions } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { LabTest, LabTestCategory, Paginated } from '../../lib/types'

/**
 * The administrator's orderable test catalogue: categories and priced
 * tests, all configuration — nothing here is hard-coded in the workflow.
 */
export default function LabTestsPage() {
  const perms = usePermissions()
  const canView = perms.has('laboratory.view')
  const canManageTests = perms.has('laboratory.test.manage')
  const canManageCategories = perms.has('laboratory.category.manage')

  const [query, setQuery] = useState('')
  const debounced = useDebounced(query)
  const [categoryId, setCategoryId] = useState('')
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<LabTest | null>(null)
  const [creating, setCreating] = useState(false)
  const [addingCategory, setAddingCategory] = useState(false)

  const categories = useQuery({
    queryKey: ['laboratory', 'categories'],
    queryFn: () => apiGet<LabTestCategory[]>('/api/laboratory/categories'),
    enabled: canView,
    staleTime: 5 * 60_000,
  })

  const list = useQuery({
    queryKey: ['laboratory', 'tests', debounced, categoryId, page],
    queryFn: () => apiGet<Paginated<LabTest>>('/api/laboratory/tests', {
      q: debounced || undefined, category_id: categoryId || undefined, include_inactive: 1, page, per_page: 50,
    }),
    enabled: canView,
    placeholderData: (prev) => prev,
  })

  if (!canView) {
    return (
      <Page>
        <PageHeader parent="Laboratory" title="Test Catalogue" />
        <div className="ui-card"><NoAccess permission="laboratory.view" /></div>
      </Page>
    )
  }

  const columns: Column<LabTest>[] = [
    { key: 'code', header: 'Code', render: (t) => <span className="font-semibold text-slate-900">{t.code}</span> },
    { key: 'name', header: 'Test', render: (t) => t.name },
    { key: 'category', header: 'Category', render: (t) => t.category?.name ?? '—' },
    { key: 'sample_type', header: 'Sample', render: (t) => t.sample_type ?? '—' },
    { key: 'normal_range', header: 'Normal range', render: (t) => t.normal_range ?? '—' },
    { key: 'price', header: 'Price', align: 'right', render: (t) => <span className="tabular font-medium">{t.price}</span> },
    { key: 'active', header: 'Status', render: (t) => (t.is_active ? <Badge color="success">Active</Badge> : <Badge color="neutral">Inactive</Badge>) },
  ]

  return (
    <Page>
      <PageHeader
        parent="Laboratory"
        title="Test Catalogue"
        subtitle="Categories and priced tests, configured here — never hard-coded"
        actions={
          <span className="inline-flex gap-2">
            {canManageCategories && <Button variant="secondary" onClick={() => setAddingCategory(true)}>New category</Button>}
            {canManageTests && <PrimaryAction icon={Plus} onClick={() => setCreating(true)}>New test</PrimaryAction>}
          </span>
        }
      />

      <FilterBar>
        <Field label="Search">
          <div className="relative">
            <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <Input className="pl-8 w-64" placeholder="Name or code…" value={query} onChange={(e) => { setQuery(e.target.value); setPage(1) }} />
          </div>
        </Field>
        <Field label="Category">
          <Select value={categoryId} onChange={(e) => { setCategoryId(e.target.value); setPage(1) }}>
            <option value="">All</option>
            {(categories.data ?? []).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </Select>
        </Field>
      </FilterBar>

      <div className="ui-card">
        <DataTable<LabTest>
          columns={columns}
          rows={list.data?.data ?? []}
          rowKey={(t) => t.id}
          isLoading={list.isLoading}
          error={list.error}
          onRetry={list.refetch}
          onRowClick={canManageTests ? (t) => setEditing(t) : undefined}
          emptyTitle="No tests configured yet"
        />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <TestDrawer
        open={creating || !!editing}
        test={editing}
        categories={categories.data ?? []}
        onClose={() => { setCreating(false); setEditing(null) }}
      />

      <NewCategoryModal open={addingCategory} onClose={() => setAddingCategory(false)} />
    </Page>
  )
}

type Form = {
  category_id: string
  code: string
  name: string
  price: string
  sample_type: string
  description: string
  normal_range: string
  unit: string
  is_active: boolean
}

const EMPTY: Form = { category_id: '', code: '', name: '', price: '', sample_type: '', description: '', normal_range: '', unit: '', is_active: true }

function TestDrawer({ open, test, categories, onClose }: { open: boolean; test: LabTest | null; categories: LabTestCategory[]; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<Form>(EMPTY)

  useEffect(() => {
    if (!open) return
    setForm(test ? {
      category_id: test.category_id, code: test.code, name: test.name, price: test.price,
      sample_type: test.sample_type ?? '', description: test.description ?? '',
      normal_range: test.normal_range ?? '', unit: test.unit ?? '', is_active: test.is_active,
    } : EMPTY)
  }, [open, test])

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const payload = {
        ...form,
        sample_type: form.sample_type || null,
        description: form.description || null,
        normal_range: form.normal_range || null,
        unit: form.unit || null,
      }
      return test ? apiPatch(`/api/laboratory/tests/${test.id}`, payload) : apiPost('/api/laboratory/tests', payload)
    },
    onSuccess: () => {
      toast.success(test ? 'Test updated' : 'Test created')
      queryClient.invalidateQueries({ queryKey: ['laboratory', 'tests'] })
      queryClient.invalidateQueries({ queryKey: ['hospital', 'lab-tests'] })
      onClose()
    },
  })

  function set<K extends keyof Form>(key: K, value: Form[K]) {
    setForm((f) => ({ ...f, [key]: value }))
  }

  return (
    <Drawer
      open={open}
      onClose={onClose}
      title={test ? `Edit ${test.code}` : 'New laboratory test'}
      footer={
        <DrawerFooter
          onCancel={onClose}
          onSubmit={() => save.mutate()}
          submitLabel={test ? 'Save changes' : 'Create test'}
          isPending={save.isPending}
          disabled={!form.code.trim() || !form.name.trim() || !form.category_id || form.price === ''}
        />
      }
    >
      <div className="space-y-5">
        <FormSection title="Test" icon={FlaskConical}>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Code" required>
              <Input value={form.code} onChange={(e) => set('code', e.target.value.toUpperCase())} placeholder="CBC" />
            </Field>
            <Field label="Category" required>
              <Select value={form.category_id} onChange={(e) => set('category_id', e.target.value)}>
                <option value="">Select…</option>
                {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </Select>
            </Field>
            <Field label="Name" required className="col-span-2">
              <Input value={form.name} onChange={(e) => set('name', e.target.value)} placeholder="Complete Blood Count" />
            </Field>
            <Field label="Price" required>
              <Input type="number" min="0" step="0.01" value={form.price} onChange={(e) => set('price', e.target.value)} />
            </Field>
            <Field label="Sample type">
              <Input value={form.sample_type} onChange={(e) => set('sample_type', e.target.value)} placeholder="Blood" />
            </Field>
            <Field label="Normal range">
              <Input value={form.normal_range} onChange={(e) => set('normal_range', e.target.value)} placeholder="3.9 – 7.8" />
            </Field>
            <Field label="Unit">
              <Input value={form.unit} onChange={(e) => set('unit', e.target.value)} placeholder="mmol/L" />
            </Field>
            <Field label="Description" className="col-span-2">
              <Textarea rows={2} value={form.description} onChange={(e) => set('description', e.target.value)} />
            </Field>
          </div>
          {test && (
            <label className="flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer mt-3">
              <input type="checkbox" className="w-4 h-4 rounded border-slate-300 text-blue-600" checked={form.is_active} onChange={(e) => set('is_active', e.target.checked)} />
              Test is orderable
            </label>
          )}
        </FormSection>
        {save.isError && <InlineError error={save.error} />}
      </div>
    </Drawer>
  )
}

function NewCategoryModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [name, setName] = useState('')

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost('/api/laboratory/categories', { name: name.trim() }),
    onSuccess: () => {
      toast.success('Category added')
      queryClient.invalidateQueries({ queryKey: ['laboratory', 'categories'] })
      setName('')
      onClose()
    },
  })

  return (
    <Modal open={open} onClose={onClose} title="New test category"
      footer={
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>Cancel</Button>
          <Button variant="primary" onClick={() => create.mutate()} disabled={!name.trim() || create.isPending}>Add category</Button>
        </div>
      }
    >
      <Field label="Name" required>
        <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Hematology" autoFocus />
      </Field>
      {create.isError && <InlineError error={create.error} className="mt-3" />}
    </Modal>
  )
}
