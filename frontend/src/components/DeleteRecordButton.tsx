import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Trash2 } from 'lucide-react'
import { useState } from 'react'
import { apiDelete, apiGet, getApiError } from '../lib/api'
import { toast } from '../lib/toast'
import { usePermissions } from '../lib/permissions'
import { Modal } from './ui/Modal'
import { Button } from './ui/primitives'

type References = {
  label: string
  deletable: boolean
  references: { table: string; column: string; count: number }[]
}

const prettyTable = (table: string) => table.replace(/_/g, ' ')

/**
 * Part 18.3 — deleting one master-data record. The dialog asks the server
 * what points at the record before offering the button, so the answer to
 * "why can't I delete this?" is on screen rather than in a failed request.
 * Transactions are never deletable and never get one of these.
 */
export function DeleteRecordButton({
  type,
  id,
  label,
  permission,
  invalidateKeys,
  onDeleted,
  size = 'sm',
}: {
  type: string
  id: string
  label: string
  /** The permission this area needs, alongside record.delete. */
  permission: string
  invalidateKeys?: unknown[][]
  onDeleted?: () => void
  size?: 'sm' | 'md'
}) {
  const perms = usePermissions()
  const queryClient = useQueryClient()
  const [open, setOpen] = useState(false)

  const references = useQuery({
    queryKey: ['deletable', type, id],
    queryFn: () => apiGet<References>(`/api/admin/records/${type}/${id}/references`),
    enabled: open,
    retry: false,
  })

  const remove = useMutation({
    meta: { silent: true },
    mutationFn: () => apiDelete<{ label: string }>(`/api/admin/records/${type}/${id}`),
    onSuccess: (result) => {
      toast.success(`${result.label} deleted`)
      for (const key of invalidateKeys ?? []) queryClient.invalidateQueries({ queryKey: key })
      setOpen(false)
      onDeleted?.()
    },
    onError: (e) => toast.error(getApiError(e).message),
  })

  if (!perms.has('record.delete') || !perms.has(permission)) return null

  const blocked = references.data && !references.data.deletable

  return (
    <>
      <Button size={size} variant="danger" onClick={() => setOpen(true)}>
        <Trash2 size={12} /> Delete
      </Button>

      <Modal
        open={open}
        onClose={() => setOpen(false)}
        title={`Delete ${label}?`}
        footer={
          <>
            <Button onClick={() => setOpen(false)}>Cancel</Button>
            <Button variant="danger" disabled={!references.data?.deletable || remove.isPending} onClick={() => remove.mutate()}>
              {remove.isPending ? 'Deleting…' : 'Delete permanently'}
            </Button>
          </>
        }
      >
        {references.isLoading && <p className="text-sm text-slate-600">Checking what uses this record…</p>}

        {references.data?.deletable && (
          <p className="text-sm text-slate-700 leading-relaxed">
            Nothing refers to <span className="font-bold">{label}</span>, so it can be removed. The full record is written to the audit
            log first, so it can be read back if this turns out to be a mistake.
          </p>
        )}

        {blocked && (
          <div className="space-y-2">
            <p className="text-sm text-slate-700 leading-relaxed">
              <span className="font-bold">{label}</span> cannot be deleted — deleting it would leave these records pointing at
              something that no longer exists:
            </p>
            <ul className="text-sm text-slate-600 space-y-1 pl-4 list-disc">
              {references.data?.references.map((r) => (
                <li key={`${r.table}.${r.column}`}>
                  <span className="font-bold tabular">{r.count}</span> {prettyTable(r.table)}
                </li>
              ))}
            </ul>
            <p className="text-xs text-slate-500">Deactivate it instead: it stops appearing in new work while its history stays intact.</p>
          </div>
        )}
      </Modal>
    </>
  )
}
