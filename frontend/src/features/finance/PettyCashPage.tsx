import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Banknote, Coins, Scale } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { ConfirmDialog, Modal } from '../../components/ui/Modal'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError } from '../../components/ui/States'
import { StatCard } from '../../components/ui/StatCard'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Field, Input, Select } from '../../components/ui/primitives'
import { apiGet, apiPost, apiPut, getApiError } from '../../lib/api'
import { dIsPos } from '../../lib/decimal'
import { formatDate, todayIso } from '../../lib/format'
import { formatMoney } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { PettyCashSummary, PettyCashVoucher } from '../../lib/types'

const MONEY = /^\d+(\.\d{1,2})?$/

/** Petty cash: the float held at this branch, what tops it up and what was spent, voucher by voucher. */
export default function PettyCashPage() {
  const queryClient = useQueryClient()
  const canManage = usePermission('petty.cash.manage')
  const canSetFloat = usePermission('admin.settings')
  const [modal, setModal] = useState<'topup' | 'spend' | 'float' | null>(null)
  const [voiding, setVoiding] = useState<PettyCashVoucher | null>(null)

  const summary = useQuery({ queryKey: ['finance', 'petty-cash'], queryFn: () => apiGet<PettyCashSummary>('/api/finance/petty-cash', { per_page: 50 }), enabled: canManage })
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['finance'] })
    queryClient.invalidateQueries({ queryKey: ['reports'] })
  }
  const s = summary.data

  const voidVoucher = useMutation({
    meta: { silent: true },
    mutationFn: ({ id, reason }: { id: string; reason: string }) => apiPost<PettyCashVoucher>(`/api/finance/petty-cash/vouchers/${id}/void`, { reason }),
    onSuccess: (v) => {
      toast.success(`${v.doc_number} voided`, 'A reversing journal was posted; the float is restored.')
      setVoiding(null)
      refresh()
    },
  })

  const columns: Column<PettyCashVoucher>[] = [
    { key: 'date', header: 'Date', render: (v) => <span className="tabular">{formatDate(v.voucher_date)}</span>, sortValue: (v) => v.voucher_date },
    { key: 'doc', header: 'Voucher', render: (v) => <span className="tabular font-mono font-semibold">{v.doc_number}</span> },
    { key: 'type', header: 'Type', render: (v) => (v.voucher_type === 'TOPUP' ? <StatusBadge status="OPEN" label={`Top-up · ${v.funding_source === 'BANK' ? 'bank' : 'cash'}`} /> : <StatusBadge status="PENDING" label="Expense" />) },
    { key: 'what', header: 'Details', render: (v) => <><div className="font-medium text-slate-900">{v.description}</div><div className="text-xs text-slate-500">{[v.account ? `${v.account.code} ${v.account.name}` : null, v.payee ? `paid to ${v.payee}` : null, v.receipt_ref ? `receipt ${v.receipt_ref}` : null].filter(Boolean).join(' · ')}</div></> },
    { key: 'amount', header: 'Amount', align: 'right', render: (v) => <MoneyCell value={v.amount} className={v.status === 'VOID' ? 'line-through text-slate-400' : v.voucher_type === 'TOPUP' ? 'text-emerald-700 font-semibold' : ''} />, sortValue: (v) => Number(v.amount) },
    { key: 'status', header: 'Status', render: (v) => <StatusBadge status={v.status === 'VOID' ? 'VOIDED' : 'POSTED'} label={v.status === 'VOID' ? 'Void' : 'Posted'} /> },
    { key: 'by', header: 'By', render: (v) => v.creator?.name ?? '—' },
    { key: 'act', header: '', align: 'right', render: (v) => (canManage && v.status === 'POSTED' ? <Button size="sm" variant="danger" onClick={() => setVoiding(v)}>Void</Button> : null) },
  ]

  return (
    <Page>
      <PageHeader
        parent="Finance"
        title="Petty Cash"
        subtitle="A small float kept at the branch for everyday costs. Every top-up and every voucher posts its own journal, so the petty cash book and the ledger always agree."
        actions={canManage ? (
          <>
            <Link to="/reports?report=finance.petty_cash_book" className="text-xs font-semibold text-blue-700 hover:underline">Petty cash book →</Link>
            <Button onClick={() => setModal('topup')}>Top up float</Button>
            <Button variant="primary" onClick={() => setModal('spend')}>New voucher</Button>
          </>
        ) : null}
      />

      {!canManage ? (
        <div className="ui-card p-6 text-sm text-slate-600">Petty cash needs the <b>petty.cash.manage</b> permission. Ask your Director to grant it.</div>
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-3">
            <StatCard icon={Coins} label="Float held now" value={<span className="tabular">{formatMoney(s?.balance ?? '0')}</span>} hint="What the ledger says is in the tin" isLoading={summary.isLoading} />
            <StatCard
              icon={Scale}
              label="Agreed float"
              value={<span className="tabular">{formatMoney(s?.float_limit ?? '0')}</span>}
              hint={canSetFloat ? <button type="button" className="text-blue-700 font-semibold hover:underline" onClick={() => setModal('float')}>Change the float</button> : 'Set by a Director'}
              isLoading={summary.isLoading}
            />
            <StatCard icon={Banknote} label="To restore" value={<span className="tabular">{formatMoney(s?.to_restore ?? '0')}</span>} hint={s && dIsPos(s.to_restore) ? 'Top up by this much to bring the float back to its agreed size' : 'The float is at its agreed size'} isLoading={summary.isLoading} />
          </div>
          <div className="ui-card">
            <DataTable columns={columns} rows={s?.data} rowKey={(v) => v.id} isLoading={summary.isLoading} error={summary.error} onRetry={() => summary.refetch()} emptyTitle="No petty cash yet" emptyHint="Top up the float first, then record each cost as a voucher." />
          </div>
        </>
      )}

      <TopUpModal open={modal === 'topup'} onClose={() => setModal(null)} onDone={() => { setModal(null); refresh() }} suggested={s?.to_restore} />
      <SpendModal open={modal === 'spend'} onClose={() => setModal(null)} onDone={() => { setModal(null); refresh() }} accounts={s?.expense_accounts ?? []} balance={s?.balance ?? '0'} />
      <FloatModal open={modal === 'float'} onClose={() => setModal(null)} onDone={() => { setModal(null); refresh() }} current={s?.float_limit ?? '0'} />
      <ConfirmDialog
        open={voiding !== null}
        title={voiding ? `Void ${voiding.doc_number}?` : ''}
        message="A reversing journal is posted and the money returns to the float. The voucher stays on record as void."
        confirmLabel="Void voucher"
        danger
        requireReason="Reason for voiding"
        isPending={voidVoucher.isPending}
        onCancel={() => setVoiding(null)}
        onConfirm={(reason) => voiding && voidVoucher.mutate({ id: voiding.id, reason })}
      />
      {voidVoucher.isError && <InlineError error={voidVoucher.error} />}
    </Page>
  )
}

function TopUpModal({ open, onClose, onDone, suggested }: { open: boolean; onClose: () => void; onDone: () => void; suggested?: string }) {
  const [source, setSource] = useState<'CASH' | 'BANK'>('CASH')
  const [amount, setAmount] = useState('')
  const [description, setDescription] = useState('Petty cash float top-up')
  const [date, setDate] = useState(todayIso())
  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<PettyCashVoucher>('/api/finance/petty-cash/top-up', { voucher_date: date, funding_source: source, amount, description }),
    onSuccess: (v) => {
      toast.success(`${v.doc_number} posted`, `Dr Petty cash / Cr ${source === 'BANK' ? 'Bank' : 'Cash in till'}`)
      setAmount('')
      onDone()
    },
  })

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Top up the petty cash float"
      footer={<><Button onClick={onClose}>Cancel</Button><Button variant="primary" disabled={!MONEY.test(amount) || Number(amount) <= 0 || !description.trim() || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Posting…' : 'Post top-up'}</Button></>}
    >
      <div className="space-y-3">
        <Field label="Money comes from" required>
          <Select value={source} onChange={(e) => setSource(e.target.value as 'CASH' | 'BANK')}>
            <option value="CASH">Cash in till</option>
            <option value="BANK">Bank (withdrawal)</option>
          </Select>
        </Field>
        <Field label="Amount (KES)" required hint={suggested && dIsPos(suggested) ? `${formatMoney(suggested)} brings the float back to its agreed size.` : undefined}>
          <div className="flex gap-2">
            <Input inputMode="decimal" className="tabular text-right" value={amount} onChange={(e) => setAmount(e.target.value.replace(/[^\d.]/g, ''))} />
            {suggested && dIsPos(suggested) && <Button size="sm" onClick={() => setAmount(String(Number(suggested)))}>Restore</Button>}
          </div>
        </Field>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Date" required><Input type="date" max={todayIso()} value={date} onChange={(e) => setDate(e.target.value)} /></Field>
          <Field label="Note" required><Input value={description} onChange={(e) => setDescription(e.target.value)} /></Field>
        </div>
        {save.isError && <InlineError error={save.error} />}
      </div>
    </Modal>
  )
}

function SpendModal({ open, onClose, onDone, accounts, balance }: { open: boolean; onClose: () => void; onDone: () => void; accounts: { id: string; code: string; name: string }[]; balance: string }) {
  const [accountId, setAccountId] = useState('')
  const [amount, setAmount] = useState('')
  const [payee, setPayee] = useState('')
  const [description, setDescription] = useState('')
  const [receipt, setReceipt] = useState('')
  const [date, setDate] = useState(todayIso())
  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<PettyCashVoucher>('/api/finance/petty-cash/vouchers', { voucher_date: date, account_id: accountId, amount, payee: payee || null, description, receipt_ref: receipt || null }),
    onSuccess: (v) => {
      toast.success(`${v.doc_number} posted`, `Charged to ${v.account?.code} ${v.account?.name}`)
      setAmount('')
      setPayee('')
      setDescription('')
      setReceipt('')
      onDone()
    },
  })
  const tooMuch = MONEY.test(amount) && Number(amount) > Number(balance)

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Petty cash voucher"
      footer={<><Button onClick={onClose}>Cancel</Button><Button variant="primary" disabled={!accountId || !MONEY.test(amount) || Number(amount) <= 0 || !description.trim() || tooMuch || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Posting…' : 'Post voucher'}</Button></>}
    >
      <div className="space-y-3">
        <div className="text-xs text-slate-600">The float holds <b className="tabular">{formatMoney(balance)}</b>. A voucher cannot spend more than that.</div>
        <Field label="What was it for" required hint="The expense account the cost is charged to">
          <Select value={accountId} onChange={(e) => setAccountId(e.target.value)}>
            <option value="">Choose an expense account…</option>
            {accounts.map((a) => (<option key={a.id} value={a.id}>{a.code} · {a.name}</option>))}
          </Select>
        </Field>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Amount (KES)" required error={tooMuch ? 'More than the float holds' : null}><Input inputMode="decimal" className="tabular text-right" value={amount} onChange={(e) => setAmount(e.target.value.replace(/[^\d.]/g, ''))} /></Field>
          <Field label="Date" required><Input type="date" max={todayIso()} value={date} onChange={(e) => setDate(e.target.value)} /></Field>
          <Field label="Paid to"><Input placeholder="Person or shop" value={payee} onChange={(e) => setPayee(e.target.value)} /></Field>
          <Field label="Receipt no."><Input value={receipt} onChange={(e) => setReceipt(e.target.value)} /></Field>
        </div>
        <Field label="Description" required><Input placeholder="e.g. Boda fare to deliver samples" value={description} onChange={(e) => setDescription(e.target.value)} /></Field>
        {save.isError && <InlineError error={save.error} />}
      </div>
    </Modal>
  )
}

function FloatModal({ open, onClose, onDone, current }: { open: boolean; onClose: () => void; onDone: () => void; current: string }) {
  const [value, setValue] = useState(String(Number(current)))
  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPut('/api/finance/petty-cash/float', { petty_cash_float: value }),
    onSuccess: () => {
      toast.success('Float updated')
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  return (
    <Modal open={open} onClose={onClose} title="Agreed petty cash float" footer={<><Button onClick={onClose}>Cancel</Button><Button variant="primary" disabled={!MONEY.test(value) || save.isPending} onClick={() => save.mutate()}>Save</Button></>}>
      <div className="space-y-3">
        <Field label="Float for this branch (KES)" hint="The amount the tin is meant to hold after every top-up. It does not move any money by itself." error={err?.errors.petty_cash_float?.[0]}>
          <Input inputMode="decimal" className="tabular text-right" value={value} onChange={(e) => setValue(e.target.value.replace(/[^\d.]/g, ''))} />
        </Field>
        {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      </div>
    </Modal>
  )
}
