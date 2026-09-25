import { useId, type KeyboardEvent, type Ref } from 'react'
import { dCmp, dMulInt, isValidDecimal } from '../../lib/decimal'
import { formatQty } from '../../lib/money'

type Props = {
  value: string
  onChange: (value: string) => void
  uomCode: string
  factorToBase: number
  baseUomCode: string
  isDiscrete?: boolean
  max?: string | null
  inputRef?: Ref<HTMLInputElement>
  onEnter?: () => void
  onEscape?: () => void
  className?: string
  disabled?: boolean
  compact?: boolean
}

/**
 * Part 1.5 — QuantityInput: UOM-aware, shows the base-unit conversion live
 * beneath the field ("3 BOX = 600 TAB"). Integer for discrete products;
 * warns (but does not block — the server decides) above free-to-sell.
 */
export function QuantityInput({
  value,
  onChange,
  uomCode,
  factorToBase,
  baseUomCode,
  isDiscrete = true,
  max,
  inputRef,
  onEnter,
  onEscape,
  className = '',
  disabled,
  compact,
}: Props) {
  const id = useId()
  const valid = isValidDecimal(value) && Number(value) > 0
  const integerViolation = isDiscrete && valid && !/^\d+$/.test(value.trim())
  const qtyBase = valid ? dMulInt(value, factorToBase) : null
  const overMax = qtyBase !== null && max !== null && max !== undefined && dCmp(qtyBase, max) > 0
  const showConversion = factorToBase !== 1 && qtyBase !== null
  const hint = integerViolation
    ? 'Whole units only'
    : overMax
      ? `Only ${formatQty(max)} ${baseUomCode} free to sell`
      : showConversion
        ? `${formatQty(value)} ${uomCode} = ${formatQty(qtyBase)} ${baseUomCode}`
        : null

  function onKeyDown(e: KeyboardEvent<HTMLInputElement>) {
    if (e.key === 'Enter') {
      e.preventDefault()
      onEnter?.()
    } else if (e.key === 'Escape') {
      e.preventDefault()
      onEscape?.()
    }
  }

  return (
    <div className={className}>
      <div className="flex items-center gap-1">
        <input
          id={id}
          ref={inputRef}
          type="text"
          inputMode={isDiscrete ? 'numeric' : 'decimal'}
          value={value}
          disabled={disabled}
          onChange={(e) => onChange(e.target.value)}
          onKeyDown={onKeyDown}
          onFocus={(e) => e.target.select()}
          aria-invalid={!valid || integerViolation || overMax}
          title={compact ? hint ?? undefined : undefined}
          className={`ui-input tabular text-right ${compact ? 'h-7 text-xs' : ''} ${!valid || integerViolation ? '!border-rose-500' : overMax ? '!border-amber-500' : ''}`}
          style={{ width: compact ? 64 : undefined }}
        />
        <span className="text-xs text-slate-500 shrink-0">{uomCode}</span>
      </div>
      {/* Compact fields keep to one line; the hint travels as the input's tooltip instead. */}
      {!compact && (
        <div className="text-xs mt-0.5 leading-tight min-h-[16px]">
          {integerViolation ? (
            <span className="text-rose-600 font-medium">Whole units only</span>
          ) : overMax ? (
            <span className="text-amber-700 font-medium">
              Only {formatQty(max)} {baseUomCode} free to sell
            </span>
          ) : showConversion ? (
            <span className="text-slate-500 tabular">
              {formatQty(value)} {uomCode} = {formatQty(qtyBase)} {baseUomCode}
            </span>
          ) : null}
        </div>
      )}
    </div>
  )
}
