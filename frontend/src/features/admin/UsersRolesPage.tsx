import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton, NoAccess } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card, DescriptionList, Field, Input, Select } from '../../components/ui/primitives'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { formatDateTime, titleCase } from '../../lib/format'
import { useAdminBranches, useAdminRoles, usePermissionCatalogue } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { AdminBranch, AdminRole, AdminUser, Paginated, PermissionGroup } from '../../lib/types'

type Tab = 'users' | 'roles'

function isLocked(u: AdminUser): boolean {
  return !!u.locked_until && new Date(u.locked_until).getTime() > Date.now()
}

/** Groups a user's flat assignment rows into "LDW: Cashier, Storekeeper" per branch. */
function assignmentsByBranch(u: AdminUser): { branch_id: string; branch_code: string; roles: string[] }[] {
  const out = new Map<string, { branch_id: string; branch_code: string; roles: string[] }>()
  for (const a of u.assignments) {
    const entry = out.get(a.branch_id) ?? { branch_id: a.branch_id, branch_code: a.branch_code ?? a.branch_id.slice(0, 8), roles: [] }
    entry.roles.push(a.role)
    out.set(a.branch_id, entry)
  }
  return [...out.values()]
}

/** Part 18 — users, their per-branch role assignments, and the roles' permission sets. */
export default function UsersRolesPage() {
  const canManage = usePermission('admin.users')
  const [params, setParams] = useSearchParams()
  const tab: Tab = params.get('tab') === 'roles' ? 'roles' : 'users'

  return (
    <Page>
      <PageHeader parent="Admin" title="Users & Roles" subtitle="Role definitions are global; a person holds roles per branch (Part 18.1). Hidden buttons are not security — the server checks every request." />
      {!canManage ? (
        <div className="ui-card"><NoAccess permission="admin.users" /></div>
      ) : (
        <>
          <div className="flex gap-1 mb-4">
            {(['users', 'roles'] as Tab[]).map((t) => (
              <button key={t} type="button" onClick={() => setParams({ tab: t })} className={`h-8 px-3.5 rounded-md text-[12.5px] font-semibold ${tab === t ? 'bg-[var(--color-navy)] text-white' : 'bg-[var(--surface-2)] text-[var(--text-secondary)] hover:bg-[var(--surface-3)]'}`}>
                {titleCase(t)}
              </button>
            ))}
          </div>
          {tab === 'users' ? <UsersTab /> : <RolesTab />}
        </>
      )}
    </Page>
  )
}

// ---- Users ---------------------------------------------------------------

function UsersTab() {
  const [q, setQ] = useState('')
  const [active, setActive] = useState('')
  const [page, setPage] = useState(1)
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [editing, setEditing] = useState(false)
  const [creating, setCreating] = useState(false)
  const dq = useDebounced(q, 250)

  const list = useQuery({
    queryKey: ['admin', 'users', dq, active, page],
    queryFn: () => apiGet<Paginated<AdminUser>>('/api/admin/users', { q: dq, is_active: active, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })
  const selected = list.data?.data.find((u) => u.id === selectedId) ?? null

  const columns: Column<AdminUser>[] = [
    { key: 'name', header: 'Name', render: (u) => <><div className="font-semibold">{u.name}</div><div className="text-[10.5px] text-[var(--text-muted)] tabular">{u.username ?? '—'}</div></>, sortValue: (u) => u.name },
    { key: 'email', header: 'Email', render: (u) => u.email, sortValue: (u) => u.email },
    {
      key: 'assignments', header: 'Roles by branch',
      render: (u) => (
        <div className="flex flex-wrap gap-1">
          {assignmentsByBranch(u).map((b) => (
            <span key={b.branch_id} className="inline-flex items-center gap-1 px-1.5 py-[1px] rounded text-[10.5px] bg-[var(--surface-2)] border border-[var(--border)]"><b className="tabular">{b.branch_code}:</b> {b.roles.join(', ')}</span>
          ))}
          {u.assignments.length === 0 && <span className="text-[var(--text-muted)]">No roles</span>}
        </div>
      ),
    },
    { key: 'status', header: 'Status', render: (u) => <StatusBadge status={isLocked(u) ? 'LOCKED' : u.is_active ? 'ACTIVE' : 'INACTIVE'} tone={isLocked(u) ? 'red' : undefined} /> },
    { key: 'mfa', header: 'MFA', render: (u) => <StatusBadge status={u.mfa_required ? 'ACTIVE' : 'DISABLED'} label={u.mfa_required ? 'On' : 'Off'} /> },
    { key: 'login', header: 'Last login', render: (u) => <span className="tabular">{formatDateTime(u.last_login_at)}</span>, sortValue: (u) => u.last_login_at ?? '' },
  ]

  const closeDrawer = () => { setSelectedId(null); setEditing(false) }

  return (
    <>
      <FilterBar>
        <Field label="Search" className="w-72"><Input placeholder="Name, username or email" value={q} onChange={(e) => { setQ(e.target.value); setPage(1) }} /></Field>
        <Field label="Status">
          <Select value={active} onChange={(e) => { setActive(e.target.value); setPage(1) }}>
            <option value="">All</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </Select>
        </Field>
        <div className="ml-auto"><Button variant="primary" onClick={() => setCreating(true)}>New user</Button></div>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(u) => String(u.id)} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(u) => { setEditing(false); setSelectedId(u.id) }} selectedKey={selectedId === null ? null : String(selectedId)} emptyTitle="No users match" />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <Drawer open={!!selected} onClose={closeDrawer} title={selected?.name ?? ''} subtitle={selected ? `${selected.username ?? ''} · ${selected.email}` : undefined} width={editing ? 760 : 600}>
        {selected && editing && <UserForm user={selected} onDone={() => setEditing(false)} onCancel={() => setEditing(false)} />}
        {selected && !editing && <UserDetail user={selected} onEdit={() => setEditing(true)} />}
      </Drawer>
      <Drawer open={creating} onClose={() => setCreating(false)} title="New user" width={760}>
        {creating && <UserForm onDone={(u) => { setCreating(false); setSelectedId(u.id) }} onCancel={() => setCreating(false)} />}
      </Drawer>
    </>
  )
}

function UserDetail({ user, onEdit }: { user: AdminUser; onEdit: () => void }) {
  const queryClient = useQueryClient()
  const locked = isLocked(user)
  const unlock = useMutation({
    mutationFn: () => apiPost<AdminUser>(`/api/admin/users/${user.id}/unlock`),
    onSuccess: (u) => {
      toast.success(`${u.name} unlocked`)
      queryClient.invalidateQueries({ queryKey: ['admin', 'users'] })
    },
  })
  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <StatusBadge status={locked ? 'LOCKED' : user.is_active ? 'ACTIVE' : 'INACTIVE'} tone={locked ? 'red' : undefined} />
          <StatusBadge status={user.mfa_required ? 'ACTIVE' : 'DISABLED'} label={user.mfa_required ? 'MFA on' : 'MFA off'} />
          {user.must_change_password && <StatusBadge status="PENDING" label="Must change password" />}
        </div>
        <div className="flex gap-2">
          {locked && <Button variant="danger" size="sm" disabled={unlock.isPending} onClick={() => unlock.mutate()}>{unlock.isPending ? 'Unlocking…' : 'Unlock'}</Button>}
          <Button onClick={onEdit}>Edit</Button>
        </div>
      </div>
      <DescriptionList
        items={[
          { label: 'Username', value: user.username ?? '—' },
          { label: 'Email', value: user.email },
          { label: 'Phone', value: user.phone ?? '—' },
          { label: 'Last login', value: formatDateTime(user.last_login_at) },
          { label: 'Failed attempts', value: <span className="tabular">{user.failed_login_attempts}</span> },
          { label: 'Locked until', value: formatDateTime(user.locked_until) },
          { label: 'Created', value: formatDateTime(user.created_at) },
        ]}
      />
      <Card title="Role assignments">
        {user.assignments.length === 0 ? (
          <div className="p-4 text-[12px] text-[var(--text-muted)]">This person holds no roles and cannot sign in to any branch.</div>
        ) : (
          <table className="ui-table">
            <thead><tr><th>Branch</th><th>Roles</th></tr></thead>
            <tbody>
              {assignmentsByBranch(user).map((b) => (
                <tr key={b.branch_id}><td className="font-semibold tabular">{b.branch_code}</td><td>{b.roles.join(', ')}</td></tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  )
}

type UserFormState = {
  name: string; username: string; email: string; phone: string; password: string
  is_active: boolean; must_change_password: boolean; reset_mfa: boolean
  assignments: Record<string, string[]>
}

/** One form for create (no `user`) and edit. Assignments are sent as a full replacement (Part 18.1). */
function UserForm({ user, onDone, onCancel }: { user?: AdminUser; onDone: (u: AdminUser) => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const { data: me } = useCurrentUser()
  const branches = useAdminBranches()
  const roles = useAdminRoles()
  const [form, setForm] = useState<UserFormState>(() => {
    const assignments: Record<string, string[]> = {}
    for (const a of user?.assignments ?? []) (assignments[a.branch_id] ??= []).push(a.role)
    return {
      name: user?.name ?? '',
      username: user?.username ?? '',
      email: user?.email ?? '',
      phone: user?.phone ?? '',
      password: '',
      is_active: user?.is_active ?? true,
      must_change_password: user?.must_change_password ?? true,
      reset_mfa: false,
      assignments,
    }
  })
  const set = (patch: Partial<UserFormState>) => setForm({ ...form, ...patch })

  function toggleRole(branchId: string, role: string, on: boolean) {
    const current = form.assignments[branchId] ?? []
    const next = on ? [...new Set([...current, role])] : current.filter((r) => r !== role)
    set({ assignments: { ...form.assignments, [branchId]: next } })
  }

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const assignments = Object.entries(form.assignments).filter(([, r]) => r.length > 0).map(([branch_id, r]) => ({ branch_id, roles: r }))
      if (user) {
        return apiPatch<AdminUser>(`/api/admin/users/${user.id}`, {
          name: form.name,
          email: form.email,
          phone: form.phone || null,
          is_active: form.is_active,
          must_change_password: form.must_change_password,
          ...(form.password ? { password: form.password } : {}),
          ...(form.reset_mfa ? { reset_mfa: true } : {}),
          assignments,
        })
      }
      return apiPost<AdminUser>('/api/admin/users', {
        name: form.name,
        username: form.username,
        email: form.email,
        phone: form.phone || null,
        password: form.password,
        must_change_password: form.must_change_password,
        assignments,
      })
    },
    onSuccess: (u) => {
      toast.success(user ? `${u.name} updated` : `User ${u.name} created`)
      queryClient.invalidateQueries({ queryKey: ['admin', 'users'] })
      queryClient.invalidateQueries({ queryKey: ['admin', 'roles'] })
      queryClient.invalidateQueries({ queryKey: ['users'] })
      if (user && me && user.id === me.id) queryClient.invalidateQueries({ queryKey: ['auth', 'user'] })
      onDone(u)
    },
  })
  const err = save.isError ? getApiError(save.error) : null
  const passwordOk = user ? form.password === '' || form.password.length >= 12 : form.password.length >= 12
  const canSubmit = !!form.name && !!form.email && (user || !!form.username) && passwordOk && !save.isPending

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3">
        <Field label="Name" required error={err?.errors.name?.[0]}><Input value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
        <Field label="Username" required error={err?.errors.username?.[0]}><Input value={form.username} disabled={!!user} onChange={(e) => set({ username: e.target.value })} /></Field>
        <Field label="Email" required error={err?.errors.email?.[0]}><Input type="email" value={form.email} onChange={(e) => set({ email: e.target.value })} /></Field>
        <Field label="Phone" error={err?.errors.phone?.[0]}><Input value={form.phone} onChange={(e) => set({ phone: e.target.value })} /></Field>
        <Field label={user ? 'New password' : 'Temporary password'} required={!user} hint="At least 12 characters." error={err?.errors.password?.[0] ?? (form.password && !passwordOk ? 'At least 12 characters.' : null)}>
          <Input type="password" autoComplete="new-password" value={form.password} placeholder={user ? 'Leave blank to keep' : ''} onChange={(e) => set({ password: e.target.value })} />
        </Field>
        <div className="flex flex-col gap-2 pt-5 text-[12px]">
          <label className="flex items-center gap-2"><input type="checkbox" checked={form.must_change_password} onChange={(e) => set({ must_change_password: e.target.checked })} /> Must change password at next sign-in</label>
          {user && <label className="flex items-center gap-2"><input type="checkbox" checked={form.is_active} onChange={(e) => set({ is_active: e.target.checked })} /> Active</label>}
          {user && <label className="flex items-center gap-2"><input type="checkbox" checked={form.reset_mfa} onChange={(e) => set({ reset_mfa: e.target.checked })} /> Reset MFA (they re-enrol from Security)</label>}
        </div>
      </div>

      <Card title="Role assignments by branch">
        {(branches.isLoading || roles.isLoading) && <LoadingSkeleton rows={3} />}
        {(branches.isError || roles.isError) && <div className="p-3"><InlineError error={branches.error ?? roles.error} /></div>}
        {branches.data && roles.data && (
          <AssignmentEditor branches={branches.data} roles={roles.data} value={form.assignments} onToggle={toggleRole} />
        )}
      </Card>
      {err?.errors.assignments?.[0] && <div className="text-[11px] text-[var(--status-red)]">{err.errors.assignments[0]}</div>}

      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onCancel}>Cancel</Button>
        <Button variant="primary" disabled={!canSubmit} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : user ? 'Save changes' : 'Create user'}</Button>
      </div>
    </div>
  )
}

function AssignmentEditor({ branches, roles, value, onToggle }: { branches: AdminBranch[]; roles: AdminRole[]; value: Record<string, string[]>; onToggle: (branchId: string, role: string, on: boolean) => void }) {
  return (
    <table className="ui-table">
      <thead>
        <tr><th>Branch</th><th>Roles</th></tr>
      </thead>
      <tbody>
        {branches.map((b) => (
          <tr key={b.id}>
            <td className="align-top whitespace-nowrap"><b className="tabular">{b.code}</b><div className="text-[10.5px] text-[var(--text-muted)]">{b.name}{!b.is_active && ' · inactive'}</div></td>
            <td>
              <div className="flex flex-wrap gap-x-4 gap-y-1">
                {roles.map((r) => (
                  <label key={r.id} className="flex items-center gap-1.5 text-[12px] whitespace-nowrap">
                    <input type="checkbox" checked={(value[b.id] ?? []).includes(r.name)} onChange={(e) => onToggle(b.id, r.name, e.target.checked)} /> {r.name}
                  </label>
                ))}
              </div>
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

// ---- Roles ---------------------------------------------------------------

function RolesTab() {
  const roles = useAdminRoles()
  const catalogue = usePermissionCatalogue()
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [creating, setCreating] = useState(false)
  // The first role is shown until one is chosen; derived, so no effect is needed.
  const selected = roles.data?.find((r) => r.id === selectedId) ?? roles.data?.[0] ?? null

  return (
    <div className="grid gap-4 lg:grid-cols-[280px_1fr]">
      <div className="space-y-2">
        <Button variant="primary" className="w-full" onClick={() => setCreating(true)}>New role</Button>
        <div className="ui-card max-h-[70vh] overflow-y-auto">
          {roles.isLoading && <LoadingSkeleton rows={4} />}
          {roles.isError && <div className="p-3"><InlineError error={roles.error} /></div>}
          {(roles.data ?? []).map((r) => (
            <button key={r.id} type="button" onClick={() => { setSelectedId(r.id); setCreating(false) }} className={`w-full text-left px-3 py-2 border-b border-[var(--border)] last:border-b-0 ${selected?.id === r.id && !creating ? 'bg-[color-mix(in_srgb,var(--color-navy)_10%,var(--card))]' : 'hover:bg-[var(--surface-2)]'}`}>
              <div className="text-[12.5px] font-semibold">{r.name}</div>
              <div className="text-[10.5px] text-[var(--text-muted)] tabular">{r.permissions.length} permissions · {r.users_count} user{r.users_count === 1 ? '' : 's'}</div>
            </button>
          ))}
        </div>
      </div>
      <div className="min-w-0">
        {catalogue.isLoading && <LoadingSkeleton />}
        {catalogue.isError && <InlineError error={catalogue.error} />}
        {catalogue.data && creating && <RoleEditor key="new" groups={catalogue.data} onDone={(r) => { setCreating(false); setSelectedId(r.id) }} onCancel={() => setCreating(false)} />}
        {catalogue.data && !creating && selected && <RoleEditor key={selected.id} role={selected} groups={catalogue.data} onDone={() => undefined} onCancel={() => undefined} />}
      </div>
    </div>
  )
}

/** Permission matrix for one role. Saving replaces the whole set, which takes effect for every holder at once (Part 18.3). */
function RoleEditor({ role, groups, onDone, onCancel }: { role?: AdminRole; groups: PermissionGroup[]; onDone: (r: AdminRole) => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const [name, setName] = useState(role?.name ?? '')
  const [chosen, setChosen] = useState<Set<string>>(() => new Set(role?.permissions ?? []))
  const original = new Set(role?.permissions ?? [])
  const dirty = !role || chosen.size !== original.size || [...chosen].some((p) => !original.has(p))

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const permissions = [...chosen].sort()
      return role
        ? apiPatch<AdminRole>(`/api/admin/roles/${role.id}`, { permissions })
        : apiPost<AdminRole>('/api/admin/roles', { name, permissions })
    },
    onSuccess: (r) => {
      toast.success(role ? `Role ${r.name} saved` : `Role ${r.name} created`)
      queryClient.invalidateQueries({ queryKey: ['admin', 'roles'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'user'] })
      onDone(r)
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  function toggle(p: string, on: boolean) {
    setChosen((prev) => {
      const next = new Set(prev)
      if (on) next.add(p)
      else next.delete(p)
      return next
    })
  }
  function toggleGroup(g: PermissionGroup, on: boolean) {
    setChosen((prev) => {
      const next = new Set(prev)
      for (const p of g.permissions) {
        if (on) next.add(p)
        else next.delete(p)
      }
      return next
    })
  }

  return (
    <div className="space-y-3">
      <div className="flex items-end gap-3">
        {role ? (
          <div><h2 className="text-[15px] font-bold">{role.name}</h2><p className="text-[11.5px] text-[var(--text-muted)] tabular">{role.users_count} user{role.users_count === 1 ? '' : 's'} hold this role · {chosen.size} permissions</p></div>
        ) : (
          <Field label="Role name" required className="w-72" error={err?.errors.name?.[0]}><Input value={name} onChange={(e) => setName(e.target.value)} autoFocus /></Field>
        )}
        <div className="ml-auto flex gap-2">
          {!role && <Button onClick={onCancel}>Cancel</Button>}
          <Button variant="primary" disabled={save.isPending || !dirty || (!role && !name)} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : role ? 'Save' : 'Create role'}</Button>
        </div>
      </div>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="ui-card divide-y divide-[var(--border)]">
        {groups.map((g) => {
          const all = g.permissions.every((p) => chosen.has(p))
          const some = !all && g.permissions.some((p) => chosen.has(p))
          return (
            <div key={g.group} className="px-4 py-3">
              <label className="flex items-center gap-2 text-[12.5px] font-bold mb-2">
                <input type="checkbox" checked={all} ref={(el) => { if (el) el.indeterminate = some }} onChange={(e) => toggleGroup(g, e.target.checked)} />
                {titleCase(g.group)}
                <span className="text-[10.5px] font-normal text-[var(--text-muted)] tabular">{g.permissions.filter((p) => chosen.has(p)).length}/{g.permissions.length}</span>
              </label>
              <div className="grid gap-x-4 gap-y-1 sm:grid-cols-2 xl:grid-cols-3">
                {g.permissions.map((p) => (
                  <label key={p} className="flex items-center gap-1.5 text-[12px] tabular">
                    <input type="checkbox" checked={chosen.has(p)} onChange={(e) => toggle(p, e.target.checked)} /> {p}
                  </label>
                ))}
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
