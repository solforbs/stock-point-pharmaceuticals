import { useEffect, useState, type ReactNode } from 'react'
import { Button, Field, Textarea } from './primitives'

export function Modal({
  open,
  onClose,
  title,
  children,
  width = 480,
  footer,
}: {
  open: boolean
  onClose: () => void
  title: ReactNode
  children: ReactNode
  /** Pixels, or any CSS length such as "75vw". */
  width?: number | string
  footer?: ReactNode
}) {
  useEffect(() => {
    if (!open) return
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') {
        e.stopPropagation()
        onClose()
      }
    }
    window.addEventListener('keydown', onKey, true)
    return () => window.removeEventListener('keydown', onKey, true)
  }, [open, onClose])

  if (!open) return null
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/40" onClick={onClose} aria-hidden />
      <div role="dialog" aria-modal="true" className="relative ui-card shadow-2xl w-full flex flex-col max-h-[92vh]" style={{ maxWidth: width }}>
        <header className="px-5 py-3 border-b border-slate-100">
          <h2 className="text-sm font-bold text-slate-900">{title}</h2>
        </header>
        <div className="px-5 py-4 overflow-y-auto text-xs">{children}</div>
        {footer && <footer className="px-5 py-3 border-t border-slate-100 flex justify-end gap-2">{footer}</footer>}
      </div>
    </div>
  )
}

/**
 * Part 1.2 principle 6 — the dangerous action is never the easy one. Void,
 * reject, quarantine and cancel all go through a deliberate second step
 * with a typed reason.
 */
export function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel = 'Confirm',
  danger,
  requireReason,
  reasonMinLength = 3,
  onConfirm,
  onCancel,
  isPending,
}: {
  open: boolean
  title: ReactNode
  message?: ReactNode
  confirmLabel?: string
  danger?: boolean
  requireReason?: string
  reasonMinLength?: number
  onConfirm: (reason: string) => void
  onCancel: () => void
  isPending?: boolean
}) {
  const [reason, setReason] = useState('')
  useEffect(() => {
    if (open) setReason('')
  }, [open])

  const reasonOk = !requireReason || reason.trim().length >= reasonMinLength

  return (
    <Modal
      open={open}
      onClose={onCancel}
      title={title}
      footer={
        <>
          <Button onClick={onCancel} disabled={isPending}>
            Cancel
          </Button>
          <Button variant={danger ? 'danger' : 'primary'} disabled={!reasonOk || isPending} onClick={() => onConfirm(reason.trim())}>
            {isPending ? 'Working…' : confirmLabel}
          </Button>
        </>
      }
    >
      {message && <p className="text-slate-600 mb-3">{message}</p>}
      {requireReason && (
        <Field label={requireReason} required hint={`At least ${reasonMinLength} characters. This is recorded in the audit log.`}>
          <Textarea rows={3} value={reason} onChange={(e) => setReason(e.target.value)} autoFocus />
        </Field>
      )}
    </Modal>
  )
}
