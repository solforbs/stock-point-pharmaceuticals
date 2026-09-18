import { Check } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError, LoadingSkeleton, NoAccess } from '../../components/ui/States'
import { Field, Input } from '../../components/ui/primitives'
import { titleCase } from '../../lib/format'
import { useAdminRoles, usePermissionCatalogue } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'

/** Part 18.3 — the read-only role × permission matrix. Editing lives on Users & Roles. */
export default function PermissionsPage() {
  const canView = usePermission('admin.users')
  const roles = useAdminRoles(canView)
  const catalogue = usePermissionCatalogue(canView)
  const [q, setQ] = useState('')
  const term = q.trim().toLowerCase()

  const groups = (catalogue.data ?? [])
    .map((g) => ({ ...g, permissions: g.permissions.filter((p) => !term || p.includes(term)) }))
    .filter((g) => g.permissions.length > 0)
  const roleSets = (roles.data ?? []).map((r) => ({ ...r, set: new Set(r.permissions) }))

  return (
    <Page>
      <PageHeader
        parent="Admin"
        title="Permissions"
        subtitle="Which role holds which permission. Change a role's set on Users & Roles."
        actions={canView ? <Link to="/admin/users-roles?tab=roles" className="text-[12px] text-[var(--color-navy)] underline">Edit roles</Link> : null}
      />
      {!canView ? (
        <div className="ui-card"><NoAccess permission="admin.users" /></div>
      ) : (
        <>
          <FilterBar>
            <Field label="Filter permissions" className="w-72"><Input placeholder="e.g. stock.adjust" value={q} onChange={(e) => setQ(e.target.value)} /></Field>
          </FilterBar>
          {(roles.isLoading || catalogue.isLoading) && <div className="ui-card"><LoadingSkeleton /></div>}
          {(roles.isError || catalogue.isError) && <InlineError error={roles.error ?? catalogue.error} />}
          {roles.data && catalogue.data && (
            <div className="ui-card overflow-auto max-h-[75vh]">
              <table className="ui-table">
                <thead>
                  <tr>
                    <th className="sticky left-0 bg-[var(--card)] z-10">Permission</th>
                    {roleSets.map((r) => (
                      <th key={r.id} className="text-center whitespace-nowrap"><div>{r.name}</div><div className="text-[10px] font-normal text-[var(--text-muted)] tabular">{r.users_count} user{r.users_count === 1 ? '' : 's'}</div></th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {groups.map((g) => (
                    <GroupRows key={g.group} group={g.group} permissions={g.permissions} roles={roleSets} />
                  ))}
                  {groups.length === 0 && (
                    <tr><td colSpan={roleSets.length + 1} className="text-center text-[var(--text-muted)] py-6">No permissions match.</td></tr>
                  )}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}
    </Page>
  )
}

function GroupRows({ group, permissions, roles }: { group: string; permissions: string[]; roles: { id: number; name: string; set: Set<string> }[] }) {
  return (
    <>
      <tr className="bg-[var(--surface-2)]">
        <td colSpan={roles.length + 1} className="font-bold text-[11px] uppercase tracking-wide text-[var(--text-secondary)]">{titleCase(group)}</td>
      </tr>
      {permissions.map((p) => (
        <tr key={p}>
          <td className="tabular sticky left-0 bg-[var(--card)] whitespace-nowrap">{p}</td>
          {roles.map((r) => (
            <td key={r.id} className="text-center">
              {r.set.has(p) ? <Check size={14} className="inline text-[var(--status-green)]" aria-label={`${r.name} holds ${p}`} /> : <span className="text-[var(--border-strong)]">·</span>}
            </td>
          ))}
        </tr>
      ))}
    </>
  )
}
