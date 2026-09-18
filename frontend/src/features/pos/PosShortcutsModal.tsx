import { Modal } from '../../components/ui/Modal'
import { Kbd } from '../../components/ui/primitives'

export interface PosShortcutsModalProps {
  open: boolean
  onClose: () => void
  canDiscount: boolean
}

export const POS_SHORTCUTS = [
  { key: 'F2', label: 'Search' },
  { key: 'F3', label: 'Quantity' },
  { key: 'F4', label: 'UOM' },
  { key: 'F5', label: 'Customer' },
  { key: 'F6', label: 'Discount' },
  { key: 'F8', label: 'Hold' },
  { key: 'F9', label: 'Resume' },
  { key: 'F10', label: 'Pay' },
  { key: 'Esc', label: 'Clear / Close' },
  { key: '?', label: 'Shortcuts' },
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
