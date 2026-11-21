import { useMutation } from '@tanstack/react-query'
import { Mail } from 'lucide-react'
import { useState } from 'react'
import { Drawer } from '../../components/ui/Drawer'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { DrawerFooter, Field, Textarea } from '../../components/ui/primitives'
import { apiPost } from '../../lib/api'
import { useSuppliers } from '../../lib/hooks'
import { toast } from '../../lib/toast'

type SendResult = { queued: number; skipped_no_email: string[] }

/** Email a purchase request (PDF without prices) and a covering message to the suppliers ticked here. */
export function SendToSuppliersDrawer({ requisitionId, docNumber, open, onClose }: { requisitionId: string; docNumber: string; open: boolean; onClose: () => void }) {
  const suppliers = useSuppliers()
  const [selected, setSelected] = useState<string[]>([])
  const [message, setMessage] = useState('')
  const active = (suppliers.data?.data ?? []).filter((s) => s.status === 'ACTIVE')

  const send = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<SendResult>(`/api/requisitions/${requisitionId}/send-to-suppliers`, { supplier_ids: selected, message: message.trim() }),
    onSuccess: (res) => {
      toast.success(
        `${docNumber} queued for ${res.queued} supplier${res.queued === 1 ? '' : 's'}`,
        res.skipped_no_email.length > 0 ? `No email on file for: ${res.skipped_no_email.join(', ')}.` : undefined,
      )
      setSelected([])
      setMessage('')
      onClose()
    },
  })

  const close = () => {
    send.reset()
    onClose()
  }

  return (
    <Drawer
      open={open}
      onClose={close}
      title="Send to suppliers"
      subtitle={`${docNumber} goes out as a PDF without prices`}
      width={520}
      footer={
        <DrawerFooter
          badge={`${selected.length} selected`}
          onCancel={close}
          onSubmit={() => send.mutate()}
          submitLabel="Send"
          disabled={selected.length === 0 || message.trim().length < 3 || send.isPending}
          isPending={send.isPending}
        />
      }
    >
      <div className="space-y-4">
        <Field label="Message to suppliers">
          <Textarea rows={5} value={message} onChange={(e) => setMessage(e.target.value)} placeholder="e.g. Please quote your best prices and delivery time for the attached items by Friday." />
        </Field>
        <div>
          <div className="text-xs font-semibold text-slate-600 mb-1 flex items-center gap-1.5"><Mail size={12} /> Suppliers</div>
          {suppliers.isLoading && <LoadingSkeleton />}
          {suppliers.isError && <InlineError error={suppliers.error} />}
          <div className="max-h-[360px] overflow-auto rounded-md border border-slate-200 divide-y divide-slate-100">
            {active.map((s) => (
              <label key={s.id} className="flex items-center gap-2 px-3 py-2 text-sm cursor-pointer hover:bg-slate-50">
                <input type="checkbox" checked={selected.includes(s.id)} onChange={(e) => setSelected(e.target.checked ? [...selected, s.id] : selected.filter((x) => x !== s.id))} />
                <span className="flex-1">{s.name}</span>
                {!s.email && <span className="text-[11px] text-amber-600 font-medium">no email</span>}
              </label>
            ))}
          </div>
        </div>
        {send.isError && <InlineError error={send.error} />}
      </div>
    </Drawer>
  )
}
