import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog } from '../../components/ui/Modal'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card, DescriptionList } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { ProductBatch } from '../../lib/types'
import { ExpiryBadge } from './ExpiryBadge'

export interface BatchDrawerProps {
  id: string | null
  onClose: () => void
}

export function BatchDrawer({ id, onClose }: BatchDrawerProps) {
  const queryClient = useQueryClient()
  const canRelease = usePermission('quality.release')
  const showCost = usePermission('product.cost.view')
  const [action, setAction] = useState<'release' | 'quarantine' | null>(null)
  const batch = useQuery({ queryKey: ['batches', id], queryFn: () => apiGet<ProductBatch>(`/api/batches/${id}`), enabled: !!id })

  const transition = useMutation({
    mutationFn: ({ kind, reason }: { kind: 'release' | 'quarantine'; reason: string }) =>
      apiPost<ProductBatch>(`/api/batches/${id}/${kind}`, kind === 'release' ? { justification: reason || null } : { reason }),
    onSuccess: (b) => {
      toast.success(`Batch ${b.batch_number} is now ${titleCase(b.status)}`)
      setAction(null)
      queryClient.invalidateQueries({ queryKey: ['batches'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      queryClient.invalidateQueries({ queryKey: ['products'] })
    },
  })

  const b = batch.data
  const canReleaseNow = b && ['PENDING_QC', 'QUARANTINED'].includes(b.status)
  const canQuarantineNow = b && ['RELEASED', 'PENDING_QC'].includes(b.status)

  return (
    <Drawer
      open={!!id}
      onClose={onClose}
      title={b ? `Batch ${b.batch_number}` : 'Batch Trace'}
      subtitle={b ? `${b.product?.name ?? ''} · expires ${formatDate(b.expiry_date)}` : undefined}
      width={800}
      actions={
        b && canRelease ? (
          <div className="flex gap-2">
            {canReleaseNow && <Button size="sm" variant="success" onClick={() => setAction('release')}>Release QC</Button>}
            {canQuarantineNow && <Button size="sm" variant="danger" onClick={() => setAction('quarantine')}>Quarantine</Button>}
          </div>
        ) : null
      }
    >
      {batch.isLoading && <LoadingSkeleton />}
      {batch.isError && <InlineError error={batch.error} />}
      {b && (
        <div className="space-y-4">
          <div className="flex items-center gap-2">
            <StatusBadge status={b.status} />
            <ExpiryBadge date={b.expiry_date} />
          </div>
          <DescriptionList
            items={[
              { label: 'Manufactured', value: formatDate(b.manufacture_date) },
              { label: 'Supplier', value: b.supplier?.name ?? '—' },
              { label: 'Manufacturer ref', value: b.manufacturer_batch_ref ?? '—' },
              { label: 'QC released', value: formatDateTime(b.qc_released_at) },
              ...(showCost ? [{ label: 'Unit / landed cost', value: <><MoneyCell value={b.unit_cost} /> / <MoneyCell value={b.landed_unit_cost} /></> }] : []),
              { label: 'Distributed (base)', value: <QtyCell value={b.distributed_base ?? '0'} /> },
            ]}
          />
          <Card title="Balances by store">
            <table className="ui-table">
              <thead>
                <tr><th>Store</th><th className="text-right">On hand</th><th className="text-right">Reserved</th>{showCost && <th className="text-right">WAC</th>}</tr>
              </thead>
              <tbody>
                {(b.balances ?? []).map((bal) => (
                  <tr key={bal.id}>
                    <td>{bal.store?.code ?? bal.store_id.slice(0, 8)}</td>
                    <td className="text-right"><QtyCell value={bal.qty_on_hand} /></td>
                    <td className="text-right"><QtyCell value={bal.qty_reserved} /></td>
                    {showCost && <td className="text-right"><MoneyCell value={bal.wac} /></td>}
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>
          <Card title={`Movements (${b.movements?.length ?? 0})`}>
            <table className="ui-table">
              <thead>
                <tr><th>When</th><th>Type</th><th>Store</th><th className="text-right">Qty</th>{showCost && <th className="text-right">Unit cost</th>}<th>Source</th></tr>
              </thead>
              <tbody>
                {(b.movements ?? []).map((m) => (
                  <tr key={m.id}>
                    <td className="tabular whitespace-nowrap">{formatDateTime(m.txn_datetime)}</td>
                    <td>{titleCase(m.txn_type)}</td>
                    <td>{m.store?.code ?? '—'}</td>
                    <td className="text-right"><QtyCell value={m.qty_base} /></td>
                    {showCost && <td className="text-right"><MoneyCell value={m.unit_cost} /></td>}
                    <td className="text-[var(--text-muted)] tabular">{m.source_doc_type ? `${titleCase(m.source_doc_type)} ${m.source_doc_id?.slice(0, 8) ?? ''}` : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>
          <Card title={`Recipients (${b.recipients?.length ?? 0})`}>
            {(b.recipients ?? []).length === 0 ? (
              <div className="p-4 text-[12px] text-[var(--text-muted)]">Nothing from this batch has been sold yet.</div>
            ) : (
              <table className="ui-table">
                <thead>
                  <tr><th>Sale</th><th>Posted</th><th>Mode</th><th>Customer</th><th className="text-right">Qty</th></tr>
                </thead>
                <tbody>
                  {(b.recipients ?? []).map((r, i) => (
                    <tr key={`${r.sale_id}-${i}`}>
                      <td className="tabular font-semibold">{r.doc_number}</td>
                      <td className="tabular">{formatDateTime(r.posted_at)}</td>
                      <td><StatusBadge status={r.sale_mode} /></td>
                      <td>{r.customer_name ?? 'Walk-in'}</td>
                      <td className="text-right"><QtyCell value={r.qty_base} />{r.is_bonus && <span className="ml-1 text-[10px] font-bold text-[var(--status-green)]">FREE</span>}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </Card>
        </div>
      )}
      <ConfirmDialog
        open={action !== null}
        title={action === 'release' ? `Release batch ${b?.batch_number ?? ''}` : `Quarantine batch ${b?.batch_number ?? ''}`}
        message={action === 'release' ? 'Released stock becomes free to sell (FEFO order).' : 'Quarantined stock is blocked from every sale until released again.'}
        confirmLabel={action === 'release' ? 'Release' : 'Quarantine'}
        danger={action === 'quarantine'}
        requireReason={action === 'quarantine' ? 'Reason' : undefined}
        isPending={transition.isPending}
        onCancel={() => setAction(null)}
        onConfirm={(reason) => action && transition.mutate({ kind: action, reason })}
      />
    </Drawer>
  )
}
