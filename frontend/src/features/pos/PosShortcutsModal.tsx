import { Modal } from '../../components/ui/Modal'
import { Kbd } from '../../components/ui/primitives'

export interface PosShortcutsModalProps {
  open: boolean
  onClose: () => void
  canDiscount: boolean
}

export const POS_SHORTCUTS = [
  { key: 'F2', label: 'Scan / Search medicine' },
  { key: 'F3', label: 'Edit quantity' },
  { key: 'F4', label: 'Switch unit of measure (UOM)' },
  { key: 'F5', label: 'Select customer account' },
  { key: 'F6', label: 'Apply line discount' },
  { key: 'F8', label: 'Hold active cart' },
  { key: 'F9', label: 'Resume held cart' },
  { key: 'F10', label: 'Proceed to payment' },
  { key: 'Enter', label: 'Confirm / Add / Next' },
  { key: 'Esc', label: 'Cancel / Clear active input' },
  { key: 'Ctrl+Del', label: 'Remove selected line' },
  { key: '?', label: 'Open shortcuts help' },
]

export function PosShortcutsModal({ open, onClose, canDiscount }: PosShortcutsModalProps) {
  return (
    <Modal open={open} onClose={onClose} title="Terminal Keyboard Shortcuts" width={440}>
      <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-[12.5px]">
        {POS_SHORTCUTS.map((s) => (
          <div key={s.key} className="contents">
            <dt>
              <Kbd>{s.key}</Kbd>
            </dt>
            <dd className={s.key === 'F6' && !canDiscount ? 'text-[var(--text-muted)] line-through' : ''}>
              {s.label}
              {s.key === 'F6' && !canDiscount && ' (no permission)'}
            </dd>
          </div>
        ))}
      </dl>
      <div className="mt-4 p-2.5 rounded-lg bg-[var(--surface-2)] text-[11px] text-[var(--text-muted)]">
        <strong>Fast checkout workflow:</strong> Scan barcode / F2 → Enter → F10 Tender → Enter to Print.
      </div>
    </Modal>
  )
}
