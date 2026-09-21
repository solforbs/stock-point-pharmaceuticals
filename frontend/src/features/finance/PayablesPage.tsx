import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError } from '../../components/ui/States'
import { Button, Card, Field, Input, Select } from '../../components/ui/primitives'
import { apiPost } from '../../lib/api'
import { dIsPos } from '../../lib/decimal'
import { useSuppliers } from '../../lib/hooks'
import { formatMoney } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Supplier } from '../../lib/types'

type SupplierPayment = { id: string; supplier_id: string; method: string; reference: string | null; amount: string; payable_balance: string }

export default function PayablesPage() {
  const [params] = useSearchParams()
  const queryClient = useQueryClient()
  const canPay = usePermission('payment.record')
  const suppliers = useSuppliers()
  const [supplierId, setSupplierId] = useState(params.get('supplier') ?? '')
  const [method, setMethod] = useState('BANK')
  const [reference, setReference] = useState('')
  const [amount, setAmount] = useState('')
  const [last, setLast] = useState<SupplierPayment | null>(null)

  const owed = (suppliers.data?.data ?? []).filter((s) => dIsPos(s.payable_balance ?? '0'))
  const chosen = suppliers.data?.data.find((s) => s.id === supplierId)

  const pay = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<SupplierPayment>('/api/supplier-payments', { supplier_id: supplierId, method, reference: reference || null, amount }),
    onSuccess: (p) => {
      setLast(p)
      setAmount('')
      setReference('')
      toast.success(`Paid ${formatMoney(p.amount)}`, `Remaining payable ${formatMoney(p.payable_balance)}`)
      queryClient.invalidateQueries({ queryKey: ['suppliers'] })
      queryClient.invalidateQueries({ queryKey: ['finance'] })
    },
  })

  const columns: Column<Supplier>[] = [
    { key: 'name', header: 'Supplier', render: (s) => <><div className="font-semibold text-slate-900">{s.name}</div><div className="text-xs text-slate-500 font-mono">{s.code}</div></>, sortValue: (s) => s.name },
    { key: 'terms', header: 'Terms', align: 'right', render: (s) => <span className="tabular">{s.payment_terms_days ?? 0} d</span> },
    { key: 'payable', header: 'Payable balance', align: 'right', render: (s) => <MoneyCell value={s.payable_balance} className="font-bold" />, sortValue: (s) => Number(s.payable_balance ?? 0) },
    { key: 'act', header: '', align: 'right', render: (s) => <Button size="sm" onClick={() => setSupplierId(s.id)}>Pay</Button> },
  ]

  return (
    <Page>
      <PageHeader parent="Finance" title="Payables" subtitle="What is owed to suppliers from matched invoices. A payment can never exceed the balance." />
      <div className="grid gap-4 lg:grid-cols-[1fr_380px]">
        <div id="tour-payables-table" className="ui-card">
          <DataTable columns={columns} rows={suppliers.data ? owed : undefined} rowKey={(s) => s.id} isLoading={suppliers.isLoading} error={suppliers.error} emptyTitle="Nothing owed" emptyHint="Payables appear once a supplier invoice matches." initialSort={{ key: 'payable', dir: 'desc' }} />
        </div>
        <div id="tour-payables-form">
          <Card title="Record supplier payment">
          <div className="p-4 space-y-3">
            <Field label="Supplier" required>
              <Select value={supplierId} onChange={(e) => setSupplierId(e.target.value)}>
                <option value="">Choose…</option>
                {(suppliers.data?.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.name} · owed {formatMoney(s.payable_balance)}</option>))}
              </Select>
            </Field>
            {chosen && <div className="text-xs tabular text-slate-700">Balance: <b>{formatMoney(chosen.payable_balance)}</b></div>}
            <Field label="Method" required>
              <Select value={method} onChange={(e) => setMethod(e.target.value)}>
                {['BANK', 'MPESA', 'CASH', 'CHEQUE'].map((m) => (<option key={m} value={m}>{m}</option>))}
              </Select>
            </Field>
            <Field label="Reference"><Input value={reference} onChange={(e) => setReference(e.target.value)} placeholder="RTGS / cheque / M-PESA ref" /></Field>
            <Field label="Amount (KES)" required>
              <div className="flex gap-2">
                <Input inputMode="decimal" className="tabular text-right text-sm" value={amount} onChange={(e) => setAmount(e.target.value.replace(/[^\d.]/g, ''))} />
                {chosen && <Button size="sm" onClick={() => setAmount(String(Number(chosen.payable_balance ?? 0)))}>Full</Button>}
              </div>
            </Field>
            {pay.isError && <InlineError error={pay.error} />}
            <Button variant="primary" className="w-full" disabled={!canPay || !supplierId || !/^\d+(\.\d+)?$/.test(amount) || Number(amount) <= 0 || pay.isPending} onClick={() => pay.mutate()} title={canPay ? undefined : 'Needs payment.record'}>
              {pay.isPending ? 'Posting…' : 'Post payment (Dr AP / Cr Bank)'}
            </Button>
            {last && <div className="text-xs text-slate-500 tabular">Last payment {formatMoney(last.amount)} via {last.method}{last.reference ? ` (${last.reference})` : ''}; remaining {formatMoney(last.payable_balance)}.</div>}
          </div>
        </Card>
        </div>
      </div>
    </Page>
  )
}
