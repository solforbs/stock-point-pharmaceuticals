import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Modal } from '../../components/ui/Modal'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Field, Input } from '../../components/ui/primitives'
import { apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { BankAccount } from '../../lib/types'

const EMPTY = { name: '', bank_name: '', account_number: '', account_name: '', branch_name: '' }

/** Our own bank accounts: where bank, cheque and card receipts are paid in. */
export default function BankAccountsPage() {
  const queryClient = useQueryClient()
  const canManage = usePermission('admin.settings')
  const [adding, setAdding] = useState(false)
  const [form, setForm] = useState(EMPTY)
  const accounts = useQuery({ queryKey: ['bank-accounts', 'all'], queryFn: () => apiGet<{ data: BankAccount[] }>('/api/bank-accounts', { include_inactive: 1 }).then((r) => r.data) })

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['bank-accounts'] })
  }
  const create = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<BankAccount>('/api/bank-accounts', { ...form, account_name: form.account_name || null, branch_name: form.branch_name || null }),
    onSuccess: (a) => {
      toast.success(`${a.name} added`, 'It now appears when a bank receipt is recorded.')
      setAdding(false)
      setForm(EMPTY)
      refresh()
    },
  })
  const toggle = useMutation({
    mutationFn: (a: BankAccount) => apiPatch<BankAccount>(`/api/bank-accounts/${a.id}`, { is_active: !a.is_active }),
    onSuccess: refresh,
  })
  const err = create.isError ? getApiError(create.error) : null

  const columns: Column<BankAccount>[] = [
    { key: 'name', header: 'Account', render: (a) => <span className="font-semibold text-slate-900">{a.name}</span>, sortValue: (a) => a.name },
    { key: 'bank', header: 'Bank', render: (a) => <>{a.bank_name}{a.branch_name ? <span className="text-slate-500"> · {a.branch_name}</span> : null}</> },
    { key: 'number', header: 'Account number', render: (a) => <span className="tabular font-mono">{a.account_number}</span> },
    { key: 'holder', header: 'Account name', render: (a) => a.account_name ?? '—' },
    { key: 'status', header: 'Status', render: (a) => <StatusBadge status={a.is_active ? 'ACTIVE' : 'INACTIVE'} /> },
    { key: 'act', header: '', align: 'right', render: (a) => (canManage ? <Button size="sm" onClick={() => toggle.mutate(a)} disabled={toggle.isPending}>{a.is_active ? 'Deactivate' : 'Activate'}</Button> : null) },
  ]

  return (
    <Page>
      <PageHeader
        parent="Finance"
        title="Bank Accounts"
        subtitle="The accounts our customers pay into. A bank, cheque or card receipt records which one it landed in, and who paid; the ledger still posts it to the Bank account."
        actions={canManage ? <Button variant="primary" onClick={() => setAdding(true)}>Add bank account</Button> : null}
      />
      <div className="ui-card">
        <DataTable columns={columns} rows={accounts.data} rowKey={(a) => a.id} isLoading={accounts.isLoading} error={accounts.error} onRetry={() => accounts.refetch()} emptyTitle="No bank accounts yet" emptyHint="Add the account customers deposit into." />
      </div>

      <Modal
        open={adding}
        onClose={() => setAdding(false)}
        title="Add bank account"
        footer={
          <>
            <Button onClick={() => setAdding(false)}>Cancel</Button>
            <Button variant="primary" disabled={!form.name || !form.bank_name || !form.account_number || create.isPending} onClick={() => create.mutate()}>{create.isPending ? 'Saving…' : 'Save account'}</Button>
          </>
        }
      >
        <div className="space-y-3">
          <Field label="Nickname" required hint="What staff will call it, e.g. Main current account" error={err?.errors.name?.[0]}>
            <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </Field>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Field label="Bank" required error={err?.errors.bank_name?.[0]}><Input placeholder="e.g. KCB" value={form.bank_name} onChange={(e) => setForm({ ...form, bank_name: e.target.value })} /></Field>
            <Field label="Bank branch"><Input value={form.branch_name} onChange={(e) => setForm({ ...form, branch_name: e.target.value })} /></Field>
            <Field label="Account number" required error={err?.errors.account_number?.[0]}><Input className="tabular" value={form.account_number} onChange={(e) => setForm({ ...form, account_number: e.target.value })} /></Field>
            <Field label="Account name"><Input value={form.account_name} onChange={(e) => setForm({ ...form, account_name: e.target.value })} /></Field>
          </div>
          {err && !Object.keys(err.errors).length && <InlineError error={create.error} />}
        </div>
      </Modal>
    </Page>
  )
}
