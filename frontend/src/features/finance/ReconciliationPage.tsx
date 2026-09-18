import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { ConfirmDialog, Modal } from '../../components/ui/Modal'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, NoAccess } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Field, Input, Select } from '../../components/ui/primitives'
import { apiGet, apiPost, getApiError } from '../../lib/api'
import { formatDate, formatDateTime, titleCase, todayIso } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import { PAYMENT_METHODS, type Paginated } from '../../lib/types'

type ReconciliationPayment = {
  id: string
  method: string
  reference: string | null
  amount: string
  status: 'CLEARED' | 'REVERSED'
  received_at: string
  reconciled_at: string | null
  reconciliation_ref: string | null
  statement_date: string | null
  statement_amount: string | null
  customer?: { id: string; code: string; name: string } | null
  receiver?: { id: number; name: string } | null
  reconciler?: { id: number; name: string } | null
}

type MethodTotal = { method: string; count: number; unreconciled_count: number; amount: string; reconciled: string; unreconciled: string }

type ReconciliationResponse = Paginated<ReconciliationPayment> & { from: string; to: string; totals: MethodTotal[] }

function monthStartIso(): string {
  return `${todayIso().slice(0, 8)}01`
}

/** Part 12.5 — match every receipt to the bank or M-PESA statement line it appeared on. */
export default function ReconciliationPage() {
  const queryClient = useQueryClient()
  const canView = usePermission('finance.ar.view')
  const canReconcile = usePermission('payment.reconcile')
  const [method, setMethod] = useState('')
  const [status, setStatus] = useState<'' | 'reconciled' | 'unreconciled'>('unreconciled')
  const [from, setFrom] = useState(monthStartIso)
  const [to, setTo] = useState(todayIso)
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<Set<string>>(new Set())
  const [reconciling, setReconciling] = useState(false)
  const [unreconciling, setUnreconciling] = useState<ReconciliationPayment | null>(null)

  const list = useQuery({
    queryKey: ['finance', 'reconciliation', method, status, from, to, page],
    queryFn: () => apiGet<ReconciliationResponse>('/api/finance/reconciliation', { method: method || undefined, status: status || undefined, from, to, page, per_page: 50 }),
    placeholderData: (prev) => prev,
    enabled: canView,
  })

  const unreconcile = useMutation({
    mutationFn: ({ payment, reason }: { payment: ReconciliationPayment; reason: string }) => apiPost<ReconciliationPayment>(`/api/finance/reconciliation/${payment.id}/unreconcile`, { reason }),
    onSuccess: () => {
      toast.success('Reconciliation undone', 'The receipt is back in the unreconciled list.')
      queryClient.invalidateQueries({ queryKey: ['finance', 'reconciliation'] })
      setUnreconciling(null)
    },
  })

  if (!canView) return <NoAccess permission="finance.ar.view" />

  const rows = list.data?.data ?? []
  const selectable = rows.filter((p) => p.status === 'CLEARED' && !p.reconciled_at)
  const selectedRows = rows.filter((p) => selected.has(p.id))
  const selectedTotal = selectedRows.reduce((sum, p) => sum + Number(p.amount), 0)
  const allSelected = selectable.length > 0 && selectable.every((p) => selected.has(p.id))

  const toggle = (id: string) => {
    const next = new Set(selected)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    setSelected(next)
  }
  const resetSelection = () => setSelected(new Set())

  const columns: Column<ReconciliationPayment>[] = [
    ...(canReconcile
      ? [{
          key: 'select',
          header: <input type="checkbox" aria-label="Select all unreconciled on this page" checked={allSelected} disabled={!selectable.length} onChange={() => setSelected(allSelected ? new Set() : new Set(selectable.map((p) => p.id)))} />,
          width: '32px',
          render: (p: ReconciliationPayment) => (p.status === 'CLEARED' && !p.reconciled_at ? <input type="checkbox" aria-label={`Select ${p.reference ?? p.id}`} checked={selected.has(p.id)} onChange={() => toggle(p.id)} onClick={(e) => e.stopPropagation()} /> : null),
        }]
      : []),
    { key: 'received', header: 'Received', render: (p) => <span className="tabular">{formatDateTime(p.received_at)}</span>, sortValue: (p) => p.received_at },
    { key: 'customer', header: 'Customer', render: (p) => <>{p.customer?.name ?? '—'}<div className="text-[10.5px] text-[var(--text-muted)] tabular">{p.customer?.code}</div></>, sortValue: (p) => p.customer?.name ?? '' },
    { key: 'method', header: 'Method', render: (p) => titleCase(p.method), sortValue: (p) => p.method },
    { key: 'reference', header: 'Reference', render: (p) => <span className="tabular">{p.reference ?? '—'}</span> },
    { key: 'amount', header: 'Amount', align: 'right', render: (p) => <MoneyCell value={p.amount} />, sortValue: (p) => Number(p.amount) },
    { key: 'by', header: 'Received by', render: (p) => p.receiver?.name ?? '—' },
    {
      key: 'state',
      header: 'Reconciliation',
      render: (p) => {
        if (p.status === 'REVERSED') return <StatusBadge status="REVERSED" />
        if (!p.reconciled_at) return <StatusBadge status="UNRECONCILED" tone="amber" label="Unreconciled" />
        return (
          <div>
            <StatusBadge status="RECONCILED" tone="green" label="Reconciled" />
            <div className="text-[10.5px] text-[var(--text-muted)] mt-0.5">{p.reconciliation_ref} · {formatDate(p.statement_date)}{p.reconciler ? ` · ${p.reconciler.name}` : ''}</div>
          </div>
        )
      },
    },
    {
      key: 'action',
      header: '',
      align: 'right',
      render: (p) => (canReconcile && p.reconciled_at ? <Button size="sm" variant="ghost" onClick={(e) => { e.stopPropagation(); setUnreconciling(p) }}>Unreconcile</Button> : null),
    },
  ]

  return (
    <Page>
      <PageHeader
        parent="Finance"
        title="Payments & Reconciliation"
        subtitle="Tick the receipts that appear on a bank or M-PESA statement and reconcile them against its reference. Reversed receipts cannot be reconciled."
        actions={canReconcile ? <Button variant="primary" disabled={!selected.size} onClick={() => setReconciling(true)}>Reconcile selected{selected.size ? ` (${selected.size})` : ''}</Button> : null}
      />
      <FilterBar>
        <Field label="From" className="w-40"><Input type="date" value={from} onChange={(e) => { setFrom(e.target.value); setPage(1); resetSelection() }} /></Field>
        <Field label="To" className="w-40"><Input type="date" value={to} onChange={(e) => { setTo(e.target.value); setPage(1); resetSelection() }} /></Field>
        <Field label="Method" className="w-40">
          <Select value={method} onChange={(e) => { setMethod(e.target.value); setPage(1); resetSelection() }}>
            <option value="">All methods</option>
            {PAYMENT_METHODS.map((m) => (<option key={m} value={m}>{titleCase(m)}</option>))}
          </Select>
        </Field>
        <Field label="Status" className="w-44">
          <Select value={status} onChange={(e) => { setStatus(e.target.value as '' | 'reconciled' | 'unreconciled'); setPage(1); resetSelection() }}>
            <option value="">All</option>
            <option value="unreconciled">Unreconciled</option>
            <option value="reconciled">Reconciled</option>
          </Select>
        </Field>
      </FilterBar>

      <div className="grid gap-3 mb-4 grid-cols-2 lg:grid-cols-5">
        {(list.data?.totals ?? []).map((t) => (
          <div key={t.method} className="ui-card p-3">
            <div className="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{titleCase(t.method)} · {t.count} receipt{t.count === 1 ? '' : 's'}</div>
            <div className="text-[17px] font-bold mt-1"><MoneyCell value={t.amount} symbol /></div>
            <div className="text-[11px] mt-1 text-[var(--text-secondary)]">Reconciled <MoneyCell value={t.reconciled} /></div>
            <div className={`text-[11px] ${Number(t.unreconciled) > 0 ? 'text-[var(--status-amber)] font-semibold' : 'text-[var(--text-muted)]'}`}>Outstanding <MoneyCell value={t.unreconciled} /> ({t.unreconciled_count})</div>
          </div>
        ))}
        {list.data && !list.data.totals.length && <div className="text-[12px] text-[var(--text-muted)] col-span-full">No cleared receipts between {formatDate(list.data.from)} and {formatDate(list.data.to)}.</div>}
      </div>

      {selected.size > 0 && (
        <div className="mb-2 text-[12px] text-[var(--text-secondary)] flex items-center gap-3">
          <span>{selected.size} selected · total <MoneyCell value={selectedTotal} symbol className="font-bold" /></span>
          <Button size="sm" variant="ghost" onClick={resetSelection}>Clear selection</Button>
        </div>
      )}
      <div className="ui-card">
        <DataTable
          columns={columns}
          rows={list.data?.data}
          rowKey={(p) => p.id}
          isLoading={list.isLoading}
          error={list.error}
          onRetry={() => list.refetch()}
          onRowClick={canReconcile ? (p) => { if (p.status === 'CLEARED' && !p.reconciled_at) toggle(p.id) } : undefined}
          rowClassName={(p) => (selected.has(p.id) ? 'bg-[var(--surface-2)]' : p.status === 'REVERSED' ? 'opacity-60' : '')}
          emptyTitle={status === 'unreconciled' ? 'Everything is reconciled' : 'No receipts in this period'}
          emptyHint={status === 'unreconciled' ? 'Every cleared receipt in this window has been matched to a statement.' : 'Receipts are recorded from Receivables, POS credit settlements and dispatch.'}
        />
        <Pagination page={list.data} onPage={(p) => { setPage(p); resetSelection() }} />
      </div>

      <ReconcileModal
        open={reconciling}
        payments={selectedRows}
        total={selectedTotal}
        onClose={() => setReconciling(false)}
        onDone={() => { setReconciling(false); resetSelection() }}
      />
      <ConfirmDialog
        open={!!unreconciling}
        title={`Unreconcile ${unreconciling?.reference ?? 'receipt'}?`}
        message={unreconciling ? `It was matched to ${unreconciling.reconciliation_ref} on ${formatDate(unreconciling.statement_date)}.` : undefined}
        requireReason="Reason"
        confirmLabel="Unreconcile"
        danger
        isPending={unreconcile.isPending}
        onConfirm={(reason) => unreconciling && unreconcile.mutate({ payment: unreconciling, reason })}
        onCancel={() => setUnreconciling(null)}
      />
    </Page>
  )
}

function ReconcileModal({ open, payments, total, onClose, onDone }: { open: boolean; payments: ReconciliationPayment[]; total: number; onClose: () => void; onDone: () => void }) {
  const queryClient = useQueryClient()
  const [ref, setRef] = useState('')
  const [statementDate, setStatementDate] = useState(todayIso)

  const reconcile = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<{ reconciled: number }>('/api/finance/reconciliation/reconcile', { payment_ids: payments.map((p) => p.id), reconciliation_ref: ref, statement_date: statementDate }),
    onSuccess: (r) => {
      toast.success(`${r.reconciled} receipt${r.reconciled === 1 ? '' : 's'} reconciled`, `Against ${ref}.`)
      queryClient.invalidateQueries({ queryKey: ['finance', 'reconciliation'] })
      setRef('')
      onDone()
    },
  })
  const err = reconcile.isError ? getApiError(reconcile.error) : null

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Reconcile selected receipts"
      footer={
        <>
          <Button onClick={onClose} disabled={reconcile.isPending}>Cancel</Button>
          <Button variant="primary" disabled={ref.trim().length < 2 || !statementDate || !payments.length || reconcile.isPending} onClick={() => reconcile.mutate()}>{reconcile.isPending ? 'Reconciling…' : 'Reconcile'}</Button>
        </>
      }
    >
      <p className="text-[var(--text-secondary)] mb-3">{payments.length} receipt{payments.length === 1 ? '' : 's'} totalling <MoneyCell value={total} symbol className="font-bold" /> will be marked as appearing on this statement.</p>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Statement reference" required error={err?.errors.reconciliation_ref?.[0]}><Input value={ref} onChange={(e) => setRef(e.target.value)} placeholder="e.g. KCB-SEP-2026 p.3" autoFocus /></Field>
        <Field label="Statement date" required error={err?.errors.statement_date?.[0]}><Input type="date" value={statementDate} max={todayIso()} onChange={(e) => setStatementDate(e.target.value)} /></Field>
      </div>
      {err && !Object.keys(err.errors).length && <InlineError error={reconcile.error} className="mt-3" />}
    </Modal>
  )
}
