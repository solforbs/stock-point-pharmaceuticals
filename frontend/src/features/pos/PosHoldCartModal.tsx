import { Modal } from '../../components/ui/Modal'
import { Button, Field, Input } from '../../components/ui/primitives'

export interface PosHoldCartModalProps {
  open: boolean
  onClose: () => void
  holdName: string
  onChangeName: (val: string) => void
  onConfirmHold: () => void
}

export function PosHoldCartModal({
  open,
  onClose,
  holdName,
  onChangeName,
  onConfirmHold,
}: PosHoldCartModalProps) {
  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Hold Active Cart"
      footer={
        <>
          <Button onClick={onClose}>Cancel</Button>
          <Button variant="primary" onClick={onConfirmHold}>
            Hold Cart
          </Button>
        </>
      }
    >
      <Field
        label="Customer Note / Identifier (Optional)"
        hint="Held carts stay on this terminal and can be retrieved using F9."
      >
        <Input
          autoFocus
          placeholder="e.g. Kakuma ward prescription / Waiting for cash"
          value={holdName}
          onChange={(e) => onChangeName(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') onConfirmHold()
          }}
        />
      </Field>
    </Modal>
  )
}
