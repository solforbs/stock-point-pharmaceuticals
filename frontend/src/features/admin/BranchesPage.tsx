import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError, NoAccess } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card, DescriptionList, Field, Input, Select } from '../../components/ui/primitives'
import { apiPatch, apiPost, getApiError } from '../../lib/api'
import { titleCase } from '../../lib/format'
import { useAdminBranches } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import { STORE_TYPES, type AdminBranch, type AdminStore } from '../../lib/types'

/** Part 17 — branches and the stores inside them. The branch switcher reads ['auth','user'], so every change refreshes it. */
export default function BranchesPage() {
  const canManage = usePermission('admin.settings')
  const branches = useAdminBranches(canManage)
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [editing, setEditing] = useState(false)
  const [addingStore, setAddingStore] = useState(false)
  const [creating, setCreating] = useState(false)
  const selected = branches.data?.find((b) => b.id === selectedId) ?? null

  const columns: Column<AdminBranch>[] = [
    { key: 'code', header: 'Code', render: (b) => <span className="font-semibold tabular">{b.code}</span>, sortValue: (b) => b.code },
    { key: 'name', header: 'Name', render: (b) => b.name, sortValue: (b) => b.name },
    { key: 'county', header: 'County', render: (b) => b.county ?? '—' },
    { key: 'modes', header: 'Sale modes', render: (b) => <ModeBadges branch={b} /> },
    { key: 'stores', header: 'Stores', align: 'right', render: (b) => <span className="tabular">{b.stores.length}</span>, sortValue: (b) => b.stores.length },
    { key: 'status', header: 'Status', render: (b) => <StatusBadge status={b.is_active ? 'ACTIVE' : 'INACTIVE'} /> },
  ]

  const close = () => { setSelectedId(null); setEditing(false); setAddingStore(false) }

  return (
    <Page>
      <PageHeader
        parent="Admin"
        title="Branches & Stores"
        subtitle="Every stock balance and every sale belongs to a store inside a branch (Part 17.1). Stores cannot be deleted once they hold stock."
        actions={canManage ? <Button variant="primary" onClick={() => setCreating(true)}>New branch</Button> : null}
      />
      {!canManage ? (
        <div className="ui-card"><NoAccess permission="admin.settings" /></div>
      ) : (
        <div className="ui-card">
          <DataTable columns={columns} rows={branches.data} rowKey={(b) => b.id} isLoading={branches.isLoading} error={branches.error} onRetry={() => branches.refetch()} onRowClick={(b) => { setEditing(false); setAddingStore(false); setSelectedId(b.id) }} selectedKey={selectedId} emptyTitle="No branches" />
        </div>
      )}
      <Drawer open={!!selected} onClose={close} title={selected?.name ?? ''} subtitle={selected?.code} width={680}>
        {selected && editing && <BranchForm branch={selected} onDone={() => setEditing(false)} onCancel={() => setEditing(false)} />}
        {selected && !editing && (
          <div className="space-y-4">
            <div className="flex items-center justify-between gap-2">
              <div className="flex items-center gap-2"><StatusBadge status={selected.is_active ? 'ACTIVE' : 'INACTIVE'} /><ModeBadges branch={selected} /></div>
              <Button onClick={() => setEditing(true)}>Edit</Button>
            </div>
            <DescriptionList items={[{ label: 'Address', value: selected.address ?? '—' }, { label: 'County', value: selected.county ?? '—' }]} />
            <Card title={`Stores (${selected.stores.length})`} actions={!addingStore ? <Button size="sm" onClick={() => setAddingStore(true)}>Add store</Button> : null}>
              {addingStore && <div className="p-4 border-b border-[var(--border)]"><StoreForm branch={selected} onDone={() => setAddingStore(false)} onCancel={() => setAddingStore(false)} /></div>}
              {selected.stores.length === 0 ? (
                <div className="p-4 text-[12px] text-[var(--text-muted)]">No stores yet. A branch needs at least a MAIN store before it can receive goods.</div>
              ) : (
                <table className="ui-table">
                  <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Sellable</th></tr></thead>
                  <tbody>
                    {selected.stores.map((s: AdminStore) => (
                      <tr key={s.id}>
                        <td className="font-semibold tabular">{s.code}</td>
                        <td>{s.name}</td>
                        <td><StatusBadge status={s.store_type} tone={s.store_type === 'QUARANTINE' ? 'purple' : s.store_type === 'COLD' ? 'cold' : s.store_type === 'TRANSIT' ? 'blue' : 'slate'} /></td>
                        <td>{s.is_sellable ? 'Yes' : 'No'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </Card>
          </div>
        )}
      </Drawer>
      <Drawer open={creating} onClose={() => setCreating(false)} title="New branch" width={680}>
        {creating && <BranchForm onDone={(b) => { setCreating(false); setSelectedId(b.id) }} onCancel={() => setCreating(false)} />}
      </Drawer>
    </Page>
  )
}

function ModeBadges({ branch }: { branch: AdminBranch }) {
  const modes = [branch.retail_enabled && 'RETAIL', branch.wholesale_enabled && 'WHOLESALE', branch.dispensing_enabled && 'DISPENSING'].filter((m): m is string => !!m)
  if (modes.length === 0) return <span className="text-[var(--text-muted)] text-[11px]">None</span>
  return <div className="flex gap-1">{modes.map((m) => <StatusBadge key={m} status={m} />)}</div>
}

type BranchFormState = { code: string; name: string; address: string; county: string; is_active: boolean; retail_enabled: boolean; wholesale_enabled: boolean; dispensing_enabled: boolean }

function BranchForm({ branch, onDone, onCancel }: { branch?: AdminBranch; onDone: (b: AdminBranch) => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<BranchFormState>({
    code: branch?.code ?? '',
    name: branch?.name ?? '',
    address: branch?.address ?? '',
    county: branch?.county ?? '',
    is_active: branch?.is_active ?? true,
    retail_enabled: branch?.retail_enabled ?? true,
    wholesale_enabled: branch?.wholesale_enabled ?? false,
    dispensing_enabled: branch?.dispensing_enabled ?? false,
  })
  const set = (patch: Partial<BranchFormState>) => setForm({ ...form, ...patch })

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const body = {
        name: form.name,
        address: form.address || null,
        county: form.county || null,
        retail_enabled: form.retail_enabled,
        wholesale_enabled: form.wholesale_enabled,
        dispensing_enabled: form.dispensing_enabled,
      }
      return branch
        ? apiPatch<AdminBranch>(`/api/admin/branches/${branch.id}`, { ...body, is_active: form.is_active })
        : apiPost<AdminBranch>('/api/admin/branches', { ...body, code: form.code })
    },
    onSuccess: (b) => {
      toast.success(branch ? `${b.code} updated` : `Branch ${b.code} created`)
      queryClient.invalidateQueries({ queryKey: ['admin', 'branches'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'user'] })
      queryClient.invalidateQueries({ queryKey: ['stores'] })
      onDone(b)
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Field label="Code" required error={err?.errors.code?.[0]}><Input value={form.code} disabled={!!branch} maxLength={10} onChange={(e) => set({ code: e.target.value.toUpperCase() })} /></Field>
        <Field label="Name" required error={err?.errors.name?.[0]}><Input value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
        <Field label="Address" className="col-span-2"><Input value={form.address} onChange={(e) => set({ address: e.target.value })} /></Field>
        <Field label="County"><Input value={form.county} onChange={(e) => set({ county: e.target.value })} /></Field>
        <div className="flex flex-col gap-1.5 pt-5 text-[12px]">
          <label className="flex items-center gap-2"><input type="checkbox" checked={form.retail_enabled} onChange={(e) => set({ retail_enabled: e.target.checked })} /> Retail</label>
          <label className="flex items-center gap-2"><input type="checkbox" checked={form.wholesale_enabled} onChange={(e) => set({ wholesale_enabled: e.target.checked })} /> Wholesale</label>
          <label className="flex items-center gap-2"><input type="checkbox" checked={form.dispensing_enabled} onChange={(e) => set({ dispensing_enabled: e.target.checked })} /> Dispensing</label>
          {branch && <label className="flex items-center gap-2"><input type="checkbox" checked={form.is_active} onChange={(e) => set({ is_active: e.target.checked })} /> Active</label>}
        </div>
      </div>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onCancel}>Cancel</Button>
        <Button variant="primary" disabled={!form.code || !form.name || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : branch ? 'Save changes' : 'Create branch'}</Button>
      </div>
    </div>
  )
}

function StoreForm({ branch, onDone, onCancel }: { branch: AdminBranch; onDone: () => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState({ code: '', name: '', store_type: 'MAIN', is_sellable: true })
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<AdminStore>(`/api/admin/branches/${branch.id}/stores`, form),
    onSuccess: (s) => {
      toast.success(`Store ${branch.code}/${s.code} created`)
      queryClient.invalidateQueries({ queryKey: ['admin', 'branches'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'user'] })
      queryClient.invalidateQueries({ queryKey: ['stores'] })
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-3 gap-3">
        <Field label="Code" required error={err?.errors.code?.[0]}><Input value={form.code} maxLength={20} onChange={(e) => set({ code: e.target.value.toUpperCase() })} /></Field>
        <Field label="Name" required error={err?.errors.name?.[0]}><Input value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
        <Field label="Type" required error={err?.errors.store_type?.[0]}>
          <Select value={form.store_type} onChange={(e) => set({ store_type: e.target.value, is_sellable: e.target.value === 'QUARANTINE' || e.target.value === 'TRANSIT' ? false : form.is_sellable })}>
            {STORE_TYPES.map((t) => (<option key={t} value={t}>{titleCase(t)}</option>))}
          </Select>
        </Field>
      </div>
      <label className="flex items-center gap-2 text-[12px]"><input type="checkbox" checked={form.is_sellable} onChange={(e) => set({ is_sellable: e.target.checked })} /> Sellable (stock here counts as free to sell)</label>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button size="sm" onClick={onCancel}>Cancel</Button>
        <Button size="sm" variant="primary" disabled={!form.code || !form.name || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Add store'}</Button>
      </div>
    </div>
  )
}
