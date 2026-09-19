import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Send, Trash2, UserCheck } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { ConfirmDialog } from '../../components/ui/Modal'
import { ErrorState, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card } from '../../components/ui/primitives'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { apiGet, apiPost } from '../../lib/api'
import { dIsZero } from '../../lib/decimal'
import { formatDateTime } from '../../lib/format'
import { useIsOffline } from '../../lib/offline/connectivity'
import type { OutboxSale } from '../../lib/offline/db'
import { discardRejected, replayOutbox, takeOver, useOutbox, useRecentlySynced } from '../../lib/offline/outbox'
import { usePermission } from '../../lib/permissions'
import { toast, toastApiError } from '../../lib/toast'
import type { Decimal, Paginated } from '../../lib/types'

/**
 * Part 16.7 — sales still held by this device. Normally empty: the till
 * sends them itself on reconnect. What is left is either waiting for its
 * cashier to sign in again, or was refused by the server outright.
 */
export function DeviceOutboxCard() {
  const { data: user } = useCurrentUser()
  const offline = useIsOffline()
  const outbox = useOutbox()
  const synced = useRecentlySynced(10)
  const [discardId, setDiscardId] = useState<string | null>(null)
  const [sending, setSending] = useState(false)

  const mine = (outbox ?? []).filter((s) => s.userId === user?.id && s.status === 'PENDING')
  const others = (outbox ?? []).filter((s) => s.userId !== user?.id && s.status === 'PENDING')

  async function sendNow() {
    setSending(true)
    try {
      const taken = await replayOutbox(user?.id)
      toast.success(taken > 0 ? `${taken} sent to the server` : 'Nothing was sent', taken > 0 ? undefined : 'The server may still be unreachable.')
    } finally {
      setSending(false)
    }
  }

  const columns: Column<OutboxSale>[] = [
    { key: 'sold', header: 'Sold', render: (s) => <span className="tabular">{formatDateTime(s.soldAt)}</span>, sortValue: (s) => s.soldAt },
    { key: 'cashier', header: 'Cashier', render: (s) => s.userName },
    { key: 'items', header: 'Items', render: (s) => <span className="text-[11.5px]">{s.lines.map((l) => `${l.product_name} × ${l.qty}`).join(', ')}</span> },
    { key: 'total', header: 'Total', align: 'right', render: (s) => <MoneyCell value={s.total} /> },
    {
      key: 'state',
      header: 'State',
      render: (s) =>
        s.status === 'REJECTED' ? (
          <span title={s.lastError ?? undefined}><StatusBadge status="REJECTED" tone="red" label="Refused by server" /></span>
        ) : s.userId !== user?.id ? (
          <StatusBadge status="WAITING" tone="slate" label={`Waiting for ${s.userName}`} />
        ) : (
          <StatusBadge status="PENDING" tone="amber" label={s.attempts > 0 ? `Pending · ${s.attempts} tries` : 'Pending'} />
        ),
    },
    {
      key: 'actions',
      header: '',
      render: (s) =>
        s.status === 'REJECTED' ? (
          <Button size="sm" variant="danger" onClick={() => setDiscardId(s.id)}><Trash2 size={12} /> Discard</Button>
        ) : null,
    },
  ]

  return (
    <Card
      title="On this device"
      actions={
        <div className="flex items-center gap-2">
          {others.length > 0 && user && (
            <Button size="sm" onClick={() => void takeOver(others.map((s) => s.id), user.id, user.name).then(sendNow)} title="Send another cashier's offline sales under your name; each keeps a note of who actually sold it.">
              <UserCheck size={12} /> Take over {others.length}
            </Button>
          )}
          <Button size="sm" variant="primary" disabled={offline || sending || mine.length === 0} onClick={() => void sendNow()}>
            <Send size={12} /> {sending ? 'Sending…' : 'Send now'}
          </Button>
        </div>
      }
    >
      {outbox === undefined ? (
        <LoadingSkeleton rows={2} />
      ) : (
        <DataTable columns={columns} rows={outbox} rowKey={(s) => s.id} emptyTitle="Nothing waiting on this device" emptyHint="Sales made offline appear here until the server has them." />
      )}
      {synced && synced.length > 0 && (
        <div className="px-4 py-2 border-t border-[var(--border)] text-[11px] text-[var(--text-muted)]">
          Recently sent:{' '}
          {synced.map((s) => (
            <span key={s.id} className="mr-3 tabular">
              {s.status === 'POSTED' ? s.docNumber : <span className="text-[var(--status-red)] font-semibold">conflict ({s.errorCode})</span>} · {formatDateTime(s.syncedAt)}
            </span>
          ))}
        </div>
      )}
      <ConfirmDialog
        open={discardId !== null}
        title="Discard this offline sale?"
        message="The server refused it, so it is not in the books. Discarding removes it from this device for good; record the sale some other way first if the customer paid."
        confirmLabel="Discard"
        danger
        onCancel={() => setDiscardId(null)}
        onConfirm={() => {
          if (discardId) void discardRejected(discardId)
          setDiscardId(null)
        }}
      />
    </Card>
  )
}

type ServerOfflineSale = {
  id: string
  idempotency_key: string
  terminal_id: string | null
  sold_at: string
  status: 'POSTED' | 'CONFLICT' | 'DISMISSED'
  offline_total: Decimal
  server_total: Decimal | null
  price_variance: Decimal
  error_code: string | null
  error_message: string | null
  attempts: number
  resolution_note: string | null
  payload_json: { sold_by?: string | null; lines: { product_id: string; qty: string }[] }
  sale: { id: string; doc_number: string; grand_total: Decimal } | null
  store: { id: string; code: string; name: string } | null
  user: { id: number; name: string } | null
  resolver: { id: number; name: string } | null
}

/**
 * Part 16.7 — what the tills handed over after an outage. A conflict is a
 * sale the customer paid for that is not yet in the books: retry it once
 * the cause is fixed (usually stock), or dismiss it saying what happened
 * to the money.
 */
export function ServerOfflineSalesCard() {
  const queryClient = useQueryClient()
  const canRetry = usePermission('sale.create')
  const canDismiss = usePermission('sale.void')
  const [status, setStatus] = useState<'CONFLICT' | ''>('CONFLICT')
  const [dismissing, setDismissing] = useState<ServerOfflineSale | null>(null)

  const list = useQuery({
    queryKey: ['offline-sales', status],
    queryFn: () => apiGet<Paginated<ServerOfflineSale>>('/api/pos/offline-sales', { status, per_page: 50 }),
  })

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ['offline-sales'] })
    void queryClient.invalidateQueries({ queryKey: ['sync', 'status'] })
  }

  const retry = useMutation({
    mutationFn: (id: string) => apiPost<ServerOfflineSale>(`/api/pos/offline-sales/${id}/retry`),
    onSuccess: (r) => {
      if (r.status === 'POSTED') toast.success(`Posted ${r.sale?.doc_number ?? ''}`)
      else toast.error('Still a conflict', r.error_message ?? undefined)
      refresh()
    },
    onError: (e) => toastApiError(e, 'Could not retry'),
  })

  const dismiss = useMutation({
    mutationFn: ({ id, reason }: { id: string; reason: string }) => apiPost(`/api/pos/offline-sales/${id}/dismiss`, { reason }),
    onSuccess: () => {
      toast.success('Dismissed')
      setDismissing(null)
      refresh()
    },
    onError: (e) => toastApiError(e, 'Could not dismiss'),
  })

  const columns: Column<ServerOfflineSale>[] = [
    { key: 'sold', header: 'Sold', render: (r) => <span className="tabular">{formatDateTime(r.sold_at)}</span>, sortValue: (r) => r.sold_at },
    { key: 'till', header: 'Till', render: (r) => <span className="tabular">{r.terminal_id ?? '—'} · {r.store?.code ?? ''}</span> },
    { key: 'by', header: 'Cashier', render: (r) => r.payload_json.sold_by ?? r.user?.name ?? '—' },
    { key: 'total', header: 'Charged', align: 'right', render: (r) => <MoneyCell value={r.offline_total} /> },
    {
      key: 'variance',
      header: 'vs server price',
      align: 'right',
      render: (r) => (r.server_total === null ? <span className="text-[11px] text-[var(--text-muted)]">unknown</span> : dIsZero(r.price_variance) ? '—' : <MoneyCell value={r.price_variance} className="font-semibold text-[var(--status-amber)]" />),
    },
    {
      key: 'status',
      header: 'Status',
      render: (r) =>
        r.status === 'POSTED' ? (
          r.sale ? <Link to="/sell/invoices" className="underline tabular">{r.sale.doc_number}</Link> : <StatusBadge status="POSTED" />
        ) : r.status === 'DISMISSED' ? (
          <span title={r.resolution_note ?? undefined}><StatusBadge status="DISMISSED" tone="slate" label={`Dismissed by ${r.resolver?.name ?? '—'}`} /></span>
        ) : (
          <span title={r.error_message ?? undefined}><StatusBadge status="CONFLICT" tone="red" label={r.error_code ?? 'Conflict'} /></span>
        ),
    },
    {
      key: 'actions',
      header: '',
      render: (r) =>
        r.status === 'CONFLICT' ? (
          <div className="flex items-center gap-1.5 justify-end">
            {canRetry && <Button size="sm" disabled={retry.isPending} onClick={() => retry.mutate(r.id)}>Retry</Button>}
            {canDismiss && <Button size="sm" variant="danger" onClick={() => setDismissing(r)}>Dismiss</Button>}
          </div>
        ) : null,
    },
  ]

  return (
    <Card
      title="Handed over by the tills"
      actions={
        <div className="flex items-center gap-1 text-[11.5px]">
          {(['CONFLICT', ''] as const).map((s) => (
            <button key={s || 'all'} type="button" onClick={() => setStatus(s)} className={`px-2 py-0.5 rounded-md font-semibold cursor-pointer ${status === s ? 'bg-[var(--color-navy)] text-white' : 'text-[var(--text-secondary)] hover:bg-[var(--surface-2)]'}`}>
              {s === 'CONFLICT' ? 'Needs a supervisor' : 'All'}
            </button>
          ))}
        </div>
      }
    >
      {list.isLoading && <LoadingSkeleton rows={3} />}
      {list.isError && <ErrorState error={list.error} onRetry={() => list.refetch()} />}
      {list.data && (
        <DataTable
          columns={columns}
          rows={list.data.data}
          rowKey={(r) => r.id}
          emptyTitle={status === 'CONFLICT' ? 'No conflicts' : 'No offline sales yet'}
          emptyHint={status === 'CONFLICT' ? 'Every offline sale the tills handed over is in the books.' : 'Sales made while the server was unreachable appear here once their till reconnects.'}
        />
      )}
      {list.data?.data.some((r) => r.status === 'CONFLICT') && (
        <div className="px-4 py-2 border-t border-[var(--border)] text-[11px] text-[var(--text-muted)]">
          A conflict is a sale the customer has paid for that is not yet in the books. Fix the cause (usually receive or transfer the missing stock) and retry, or dismiss it saying what happened to the money.
        </div>
      )}
      <ConfirmDialog
        open={dismissing !== null}
        title="Dismiss this offline sale?"
        message={dismissing ? `It will not be posted. The customer paid ${dismissing.offline_total}; the reason must say what was done about that money.` : undefined}
        confirmLabel="Dismiss"
        danger
        requireReason="What happened to the money"
        reasonMinLength={5}
        isPending={dismiss.isPending}
        onCancel={() => setDismissing(null)}
        onConfirm={(reason) => dismissing && dismiss.mutate({ id: dismissing.id, reason })}
      />
    </Card>
  )
}
