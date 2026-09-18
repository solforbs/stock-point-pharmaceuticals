import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { useState } from 'react'
import { CustomerPicker } from '../../../components/CustomerPicker'
import { DataTable, type Column } from '../../../components/ui/DataTable'
import { Drawer } from '../../../components/ui/Drawer'
import { ConfirmDialog } from '../../../components/ui/Modal'
import { MoneyCell } from '../../../components/ui/MoneyCell'
import { FilterBar } from '../../../components/ui/PageHeader'
import { Pagination } from '../../../components/ui/Pagination'
import { InlineError } from '../../../components/ui/States'
import { StatusBadge } from '../../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select } from '../../../components/ui/primitives'
import { api, apiGet, apiPatch, apiPost, getApiError } from '../../../lib/api'
import { addDaysIso, formatDate, todayIso } from '../../../lib/format'
import { toast, toastApiError } from '../../../lib/toast'
import type { Customer, Paginated } from '../../../lib/types'
import { ProductField, UomSelect } from './fields'
import { dateOnly, decimalInput, defaultUomId, type CustomerPrice } from './shared'

function contractState(c: CustomerPrice) {
  const today = todayIso()
  if (dateOnly(c.effective_to) < today) return { status: 'EXPIRED', label: 'Expired' }
  if (dateOnly(c.effective_from) > today) return { status: 'PENDING', label: 'Starts later' }
  return { status: 'ACTIVE', label: 'In force' }
}

/**
 * Part 4.2 rank 1 — negotiated contract prices per customer, product and
 * unit. Every contract has an end date; one in force is ended, not deleted.
 */
export default function CustomerPricesTab({ canManage }: { canManage: boolean }) {
  const queryClient = useQueryClient()
  const [customer, setCustomer] = useState<Customer | null>(null)
  const [productId, setProductId] = useState('')
  const [status, setStatus] = useState('CURRENT')
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<CustomerPrice | 'new' | null>(null)
  const [deleting, setDeleting] = useState<CustomerPrice | null>(null)

  const list = useQuery({
    queryKey: ['pricing-rules', 'customer-prices', customer?.id, productId, status, page],
    queryFn: () => apiGet<Paginated<CustomerPrice>>('/api/pricing-rules/customer-prices', { customer_id: customer?.id, product_id: productId, status, page }),
    placeholderData: (prev) => prev,
  })
  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['pricing-rules', 'customer-prices'] })
  const remove = useMutation({
    mutationFn: (c: CustomerPrice) => api.delete(`/api/pricing-rules/customer-prices/${c.id}`),
    onSuccess: () => {
      toast.success('Contract price deleted')
      setDeleting(null)
      invalidate()
    },
    onError: (e) => toastApiError(e),
  })

  const columns: Column<CustomerPrice>[] = [
    { key: 'customer', header: 'Customer', render: (c) => <>{c.customer?.name ?? '—'}<div className="text-[10.5px] text-[var(--text-muted)]">{c.customer?.code}</div></>, sortValue: (c) => c.customer?.name ?? '' },
    { key: 'product', header: 'Product', render: (c) => <>{c.product?.name ?? '—'}<div className="text-[10.5px] text-[var(--text-muted)]">{c.product?.code} · per {c.uom?.code}</div></>, sortValue: (c) => c.product?.name ?? '' },
    { key: 'price', header: 'Contract price', align: 'right', render: (c) => <MoneyCell value={c.unit_price} className="font-semibold" />, sortValue: (c) => Number(c.unit_price) },
    { key: 'ref', header: 'Contract', render: (c) => <span className="tabular">{c.contract_ref}</span> },
    { key: 'dates', header: 'Valid', render: (c) => <span className="tabular">{formatDate(c.effective_from)} – {formatDate(c.effective_to)}</span>, sortValue: (c) => c.effective_from },
    { key: 'state', header: 'Status', render: (c) => { const s = contractState(c); return <StatusBadge status={s.status} label={s.label} /> } },
  ]

  return (
    <>
      <FilterBar>
        <Field label="Customer" className="w-72"><CustomerPicker value={customer} onChange={(c) => { setCustomer(c); setPage(1) }} /></Field>
        <Field label="Product" className="w-64"><ProductField productId={productId} onChange={(p) => { setProductId(p?.id ?? ''); setPage(1) }} /></Field>
        <Field label="Status">
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All</option>
            <option value="CURRENT">In force</option>
            <option value="FUTURE">Starts later</option>
            <option value="EXPIRED">Expired</option>
          </Select>
        </Field>
        {canManage && <div className="ml-auto"><Button variant="primary" onClick={() => setEditing('new')}><Plus size={13} /> New contract price</Button></div>}
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(c) => c.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(c) => setEditing(c)} emptyTitle="No contract prices" emptyHint="A contract price is rank 1: it wins whenever it is cheaper than the list or a promotion." />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <Drawer open={editing !== null} onClose={() => setEditing(null)} title={editing === 'new' ? 'New contract price' : `Contract ${editing?.contract_ref ?? ''}`} width={560}>
        {editing !== null && (
          <ContractForm
            key={editing === 'new' ? 'new' : editing.id}
            contract={editing === 'new' ? null : editing}
            initialCustomer={customer}
            canManage={canManage}
            onDelete={editing !== 'new' ? () => setDeleting(editing) : undefined}
            onDone={() => { setEditing(null); invalidate() }}
          />
        )}
      </Drawer>
      <ConfirmDialog
        open={!!deleting}
        title="Delete this contract price?"
        message="Only a contract that has not started yet can be deleted."
        confirmLabel="Delete"
        danger
        isPending={remove.isPending}
        onConfirm={() => deleting && remove.mutate(deleting, { onSuccess: () => setEditing(null) })}
        onCancel={() => setDeleting(null)}
      />
    </>
  )
}

function ContractForm({ contract, initialCustomer, canManage, onDelete, onDone }: { contract: CustomerPrice | null; initialCustomer: Customer | null; canManage: boolean; onDelete?: () => void; onDone: () => void }) {
  const [customer, setCustomer] = useState<Customer | null>(initialCustomer)
  const [form, setForm] = useState({
    product_id: contract?.product_id ?? '',
    uom_id: contract?.uom_id ?? '',
    unit_price: contract?.unit_price ? String(Number(contract.unit_price)) : '',
    contract_ref: contract?.contract_ref ?? '',
    effective_from: dateOnly(contract?.effective_from) || todayIso(),
    effective_to: dateOnly(contract?.effective_to) || addDaysIso(365),
  })
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })
  const started = !!contract && dateOnly(contract.effective_from) <= todayIso()

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      contract
        ? apiPatch<CustomerPrice>(`/api/pricing-rules/customer-prices/${contract.id}`, {
            unit_price: form.unit_price, contract_ref: form.contract_ref, effective_to: form.effective_to,
            ...(started ? {} : { effective_from: form.effective_from }),
          })
        : apiPost<CustomerPrice>('/api/pricing-rules/customer-prices', { ...form, customer_id: customer?.id }),
    onSuccess: (c) => {
      toast.success(contract ? 'Contract price saved' : 'Contract price created', `${c.customer?.name ?? ''} · ${c.product?.name ?? ''}`)
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null
  const readOnly = !canManage

  return (
    <fieldset disabled={readOnly} className="space-y-4">
      {contract ? (
        <DescriptionList
          items={[
            { label: 'Customer', value: `${contract.customer?.code ?? ''} · ${contract.customer?.name ?? ''}` },
            { label: 'Product', value: `${contract.product?.code ?? ''} · ${contract.product?.name ?? ''} per ${contract.uom?.code ?? ''}` },
            { label: 'Approved by', value: contract.approver?.name ?? '—' },
          ]}
        />
      ) : (
        <>
          <Field label="Customer" required error={err?.errors.customer_id?.[0]}><CustomerPicker value={customer} onChange={setCustomer} /></Field>
          <div className="grid grid-cols-[1fr_130px] gap-3">
            <Field label="Product" required error={err?.errors.product_id?.[0]}><ProductField productId={form.product_id} onChange={(p) => set({ product_id: p?.id ?? '', uom_id: defaultUomId(p) })} /></Field>
            <Field label="Unit" required error={err?.errors.uom_id?.[0]}><UomSelect productId={form.product_id} value={form.uom_id} onChange={(u) => set({ uom_id: u })} /></Field>
          </div>
        </>
      )}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Field label="Contract price (per unit)" required error={err?.errors.unit_price?.[0]}><Input inputMode="decimal" className="tabular" value={form.unit_price} onChange={(e) => set({ unit_price: decimalInput(e.target.value) })} /></Field>
        <Field label="Contract reference" required error={err?.errors.contract_ref?.[0]}><Input value={form.contract_ref} onChange={(e) => set({ contract_ref: e.target.value })} /></Field>
        <Field label="Valid from" required error={err?.errors.effective_from?.[0]} hint={started ? 'Already in force; cannot move.' : undefined}><Input type="date" value={form.effective_from} disabled={started} onChange={(e) => set({ effective_from: e.target.value })} /></Field>
        <Field label="Valid to" required error={err?.errors.effective_to?.[0]} hint="Mandatory: undated contracts leak margin."><Input type="date" value={form.effective_to} min={form.effective_from} onChange={(e) => set({ effective_to: e.target.value })} /></Field>
      </div>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      {canManage && (
        <div className="flex justify-between gap-2">
          <div>
            {contract && !started && onDelete && <Button variant="danger" onClick={onDelete}>Delete</Button>}
            {contract && started && <Button onClick={() => set({ effective_to: todayIso() })}>End today</Button>}
          </div>
          <div className="flex gap-2">
            <Button onClick={onDone}>Cancel</Button>
            <Button variant="primary" disabled={(!contract && (!customer || !form.product_id || !form.uom_id)) || !form.unit_price || !form.contract_ref || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : contract ? 'Save' : 'Create contract price'}</Button>
          </div>
        </div>
      )}
    </fieldset>
  )
}
