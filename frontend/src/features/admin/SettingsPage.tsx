import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Drawer } from '../../components/ui/Drawer'
import { OrganisationCard } from './OrganisationCard'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError, LoadingSkeleton, NoAccess } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select } from '../../components/ui/primitives'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { apiGet, apiPut, getApiError } from '../../lib/api'
import { formatDateTime, titleCase } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { SettingRow, SettingValue, SettingsResponse } from '../../lib/types'

const SALE_MODE_CHOICES = ['', 'RETAIL', 'WHOLESALE', 'DISPENSING']
const ROUND_TO_CHOICES = ['NONE', '0.05', '0.10', '0.50', '1']

function choicesFor(row: SettingRow): string[] | null {
  const id = `${row.scope}.${row.key}`
  if (id === 'pos.default_sale_mode') return SALE_MODE_CHOICES
  if (id === 'pricing.round_to') return ROUND_TO_CHOICES
  return null
}

function displayValue(v: SettingValue): string {
  if (v === null || v === undefined || v === '') return '—'
  return String(v)
}

const sourceTone = { default: 'slate', organisation: 'blue', branch: 'green' } as const

/** Part 17.4 — every knob the application resolves, with the value in force for the active branch and where it comes from. */
export default function SettingsPage() {
  const canManage = usePermission('admin.settings')
  const { data: user } = useCurrentUser()
  const settings = useQuery({ queryKey: ['admin', 'settings'], queryFn: () => apiGet<SettingsResponse>('/api/admin/settings'), enabled: canManage })
  const [editing, setEditing] = useState<SettingRow | null>(null)

  const rows = settings.data?.data ?? []
  const scopes = [...new Set(rows.map((r) => r.scope))]

  return (
    <Page>
      <PageHeader parent="Admin" title="Settings" subtitle="Settings are versioned, never edited in place; a branch value overrides the organisation value for that branch only (Part 17.4)." />
      <div id="tour-settings-org">
        <OrganisationCard />
      </div>
      {!canManage ? (
        <div className="ui-card"><NoAccess permission="admin.settings" /></div>
      ) : (
        <>
          {settings.isLoading && <div className="ui-card"><LoadingSkeleton /></div>}
          {settings.isError && <InlineError error={settings.error} />}
          {settings.data && (
            <div id="tour-settings-scopes" className="space-y-4">
              <div className="text-xs text-slate-500">Showing values in force for <b>{user?.active_branch?.code ?? settings.data.branch_id}</b>.</div>
              {scopes.map((scope) => (
                <section key={scope} className="ui-card">
                  <header className="px-4 py-2.5 border-b border-slate-200 text-xs font-semibold uppercase tracking-wide text-slate-900">{titleCase(scope)}</header>
                  <table className="ui-table">
                    <thead>
                      <tr><th>Key</th><th>Description</th><th className="text-right">Effective value</th><th>Source</th><th>Set</th><th /></tr>
                    </thead>
                    <tbody>
                      {rows.filter((r) => r.scope === scope).map((r) => (
                        <tr key={r.key}>
                          <td className="font-semibold tabular whitespace-nowrap">{r.key}</td>
                          <td className="text-slate-600 max-w-md">{r.description}</td>
                          <td className="text-right tabular font-semibold font-mono">{displayValue(r.value)}</td>
                          <td><StatusBadge status={r.source} tone={sourceTone[r.source]} /></td>
                          <td className="tabular text-slate-400 font-mono whitespace-nowrap">{r.set_at ? formatDateTime(r.set_at) : '—'}</td>
                          <td className="text-right"><Button size="sm" onClick={() => setEditing(r)}>Edit</Button></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </section>
              ))}
            </div>
          )}
        </>
      )}
      <Drawer open={!!editing} onClose={() => setEditing(null)} title={editing ? `${editing.scope}.${editing.key}` : ''} subtitle={editing?.description} width={480}>
        {editing && <SettingForm key={`${editing.scope}.${editing.key}`} row={editing} branchCode={user?.active_branch?.code ?? null} onDone={() => setEditing(null)} onCancel={() => setEditing(null)} />}
      </Drawer>
    </Page>
  )
}

function SettingForm({ row, branchCode, onDone, onCancel }: { row: SettingRow; branchCode: string | null; onDone: () => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const choices = choicesFor(row)
  const [value, setValue] = useState<string>(row.value === null || row.value === undefined ? '' : String(row.value))
  const [branchScoped, setBranchScoped] = useState(row.source === 'branch')

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      // Integers travel as numbers, decimals and strings as strings; blank means "unset" (null).
      const trimmed = value.trim()
      let payload: SettingValue = null
      if (trimmed !== '') payload = row.type === 'integer' ? Number(trimmed) : trimmed
      return apiPut<unknown>('/api/admin/settings', { scope: row.scope, key: row.key, value: payload, branch_scoped: branchScoped })
    },
    onSuccess: () => {
      toast.success(`${row.scope}.${row.key} saved`, branchScoped ? `For ${branchCode ?? 'this branch'} only.` : 'For the whole organisation.')
      queryClient.invalidateQueries({ queryKey: ['admin', 'settings'] })
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null
  const numericInvalid = row.type !== 'string' && value.trim() !== '' && Number.isNaN(Number(value))

  return (
    <div className="space-y-4">
      <DescriptionList
        items={[
          { label: 'Type', value: row.type },
          { label: 'Default', value: displayValue(row.default) },
          { label: 'Organisation value', value: displayValue(row.organisation_value) },
          { label: 'Branch value', value: displayValue(row.branch_value) },
        ]}
      />
      <Field label="Value" hint="Leave blank to fall back to the next level (branch → organisation → default)." error={err?.errors.value?.[0] ?? (numericInvalid ? 'Enter a number.' : null)}>
        {choices ? (
          <Select value={value} onChange={(e) => setValue(e.target.value)}>
            {choices.map((c) => (<option key={c} value={c}>{c === '' ? '(unset)' : c}</option>))}
          </Select>
        ) : (
          <Input type={row.type === 'string' ? 'text' : 'number'} step={row.type === 'decimal' ? '0.01' : '1'} inputMode={row.type === 'string' ? 'text' : 'decimal'} className={row.type === 'string' ? '' : 'tabular'} value={value} onChange={(e) => setValue(e.target.value)} autoFocus />
        )}
      </Field>
      <label className="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
        <input type="checkbox" checked={branchScoped} onChange={(e) => setBranchScoped(e.target.checked)} /> Only for this branch{branchCode ? ` (${branchCode})` : ''}
      </label>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onCancel}>Cancel</Button>
        <Button variant="primary" disabled={save.isPending || numericInvalid} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Save'}</Button>
      </div>
    </div>
  )
}
