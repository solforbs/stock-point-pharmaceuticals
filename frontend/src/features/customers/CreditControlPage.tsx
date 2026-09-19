import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card, Field, Input, Textarea } from '../../components/ui/primitives'
import { apiGet, apiPatch } from '../../lib/api'
import { dIsNeg } from '../../lib/decimal'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Customer, CustomerCredit, Paginated } from '../../lib/types'

/** Part 6.4 — credit limit and hold, overridable only with customer.credit.override; every change is audited. */
export default function CreditControlPage() {
  const [params] = useSearchParams()
  const queryClient = useQueryClient()
  const canOverride = usePermission('customer.credit.override')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<Customer | null>(null)
  const [limit, setLimit] = useState('')
  const [onHold, setOnHold] = useState(false)
  const [holdReason, setHoldReason] = useState('')
  const dq = useDebounced(q, 250)

  const list = useQuery({
    queryKey: ['customers', 'credit', dq, page],
    queryFn: () => apiGet<Paginated<Customer>>('/api/customers', { q: dq, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  useEffect(() => {
    const preset = params.get('customer')
    if (preset && !selected) {
      const found = list.data?.data.find((c) => c.id === preset)
      if (found) setSelected(found)
    }
  }, [params, list.data, selected])

  useEffect(() => {
    if (!selected) return
    setLimit(String(Number(selected.credit?.credit_limit ?? 0)))
    setOnHold(!!selected.credit?.on_hold)
    setHoldReason(selected.credit?.hold_reason ?? '')
  }, [selected])

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPatch<CustomerCredit>(`/api/customers/${selected?.id}/credit`, { credit_limit: limit, on_hold: onHold, hold_reason: onHold ? holdReason : null }),
    onSuccess: () => {
      toast.success('Credit updated', 'The change is in the audit log.')
      queryClient.invalidateQueries({ queryKey: ['customers'] })
      setSelected(null)
    },
  })

  const columns: Column<Customer>[] = [
    { key: 'name', header: 'Customer', render: (c) => <><div className="font-semibold">{c.name}</div><div className="text-xs text-slate-500 font-mono">{c.code} · tier {c.tier?.code ?? '—'}</div></>, sortValue: (c) => c.name },
    { key: 'limit', header: 'Limit', align: 'right', render: (c) => <MoneyCell value={c.credit?.credit_limit ?? '0'} />, sortValue: (c) => Number(c.credit?.credit_limit ?? 0) },
    { key: 'balance', header: 'Outstanding', align: 'right', render: (c) => <MoneyCell value={c.credit?.current_balance ?? '0'} />, sortValue: (c) => Number(c.credit?.current_balance ?? 0) },
    { key: 'available', header: 'Available', align: 'right', render: (c) => <MoneyCell value={c.available_credit ?? '0'} className={dIsNeg(c.available_credit ?? '0') ? 'font-bold' : ''} />, sortValue: (c) => Number(c.available_credit ?? 0) },
    { key: 'hold', header: 'Status', render: (c) => (c.credit?.on_hold ? <StatusBadge status="ON_HOLD" label="On hold" /> : dIsNeg(c.available_credit ?? '0') ? <StatusBadge status="OVERDUE" label="Over limit" /> : <StatusBadge status="OK" label="Within limit" />) },
  ]

  return (
    <Page>
      <PageHeader parent="Customers" title="Credit Control" subtitle="Limits, holds and exposure. The server checks credit at order confirmation and dispatch." />
      <FilterBar>
        <Field label="Search" className="w-72"><Input placeholder="Customer" value={q} onChange={(e) => setQ(e.target.value)} /></Field>
      </FilterBar>
      <div className="grid gap-4 lg:grid-cols-[1fr_380px]">
        <div className="ui-card">
          <DataTable columns={columns} rows={list.data?.data} rowKey={(c) => c.id} isLoading={list.isLoading} error={list.error} onRowClick={setSelected} selectedKey={selected?.id ?? null} emptyTitle="No customers" />
          <Pagination page={list.data} onPage={setPage} />
        </div>
        <Card title={selected ? selected.name : 'Select a customer'}>
          <div className="p-4 space-y-3">
            {!selected ? (
              <p className="text-xs text-slate-500">Pick a customer to change the limit or place a hold.</p>
            ) : (
              <>
                <Field label="Credit limit (KES)"><Input inputMode="decimal" className="tabular text-right" value={limit} disabled={!canOverride} onChange={(e) => setLimit(e.target.value.replace(/[^\d.]/g, ''))} /></Field>
                <label className="flex items-center gap-2 text-xs text-slate-600 cursor-pointer"><input type="checkbox" checked={onHold} disabled={!canOverride} onChange={(e) => setOnHold(e.target.checked)} /> Place on credit hold (blocks credit sales)</label>
                {onHold && <Field label="Hold reason" required><Textarea rows={2} value={holdReason} disabled={!canOverride} onChange={(e) => setHoldReason(e.target.value)} /></Field>}
                {save.isError && <InlineError error={save.error} />}
                <Button variant="primary" className="w-full" disabled={!canOverride || save.isPending || (onHold && !holdReason.trim())} onClick={() => save.mutate()} title={canOverride ? undefined : 'Needs customer.credit.override'}>
                  {save.isPending ? 'Saving…' : 'Save credit settings'}
                </Button>
              </>
            )}
          </div>
        </Card>
      </div>
    </Page>
  )
}
