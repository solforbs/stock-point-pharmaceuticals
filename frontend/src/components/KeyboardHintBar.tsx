import { Kbd } from './ui/primitives'

export type KeyHint = { key: string; label: string; disabled?: boolean }

/** Part 1.5 — persistent shortcut hints in the POS (Part 16.4 map). */
export function KeyboardHintBar({ hints, className = '' }: { hints: KeyHint[]; className?: string }) {
  return (
    <div className={`flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-1.5 border-t border-[var(--border)] bg-[var(--surface-2)] text-[11px] text-[var(--text-secondary)] ${className}`}>
      {hints.map((hint) => (
        <span key={hint.key} className={`inline-flex items-center gap-1.5 ${hint.disabled ? 'opacity-40' : ''}`}>
          <Kbd>{hint.key}</Kbd>
          {hint.label}
        </span>
      ))}
    </div>
  )
}
