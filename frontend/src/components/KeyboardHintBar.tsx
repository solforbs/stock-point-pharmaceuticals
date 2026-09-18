import { Keyboard } from 'lucide-react'

export type KeyHint = { key: string; label: string; disabled?: boolean }

export interface KeyboardHintBarProps {
  hints: KeyHint[]
  onOpenHelp?: () => void
  className?: string
}

export function KeyboardHintBar({ hints, onOpenHelp, className = '' }: KeyboardHintBarProps) {
  return (
    <div
      className={`h-8 px-4 flex items-center justify-between border-t border-slate-200/80 bg-white text-[11px] text-slate-500 shrink-0 select-none overflow-hidden ${className}`}
    >
      <div className="flex items-center gap-3 overflow-x-auto no-scrollbar py-1">
        {hints.map((hint) => (
          <span
            key={hint.key}
            className={`inline-flex items-center gap-1.5 whitespace-nowrap transition-opacity ${
              hint.disabled ? 'opacity-30 line-through' : 'opacity-85 hover:opacity-100'
            }`}
          >
            <kbd className="px-1.5 py-0.2 rounded bg-slate-100 border border-slate-200/80 text-[10px] font-mono font-bold text-slate-700 shadow-2xs">
              {hint.key}
            </kbd>
            <span className="font-medium text-slate-600">{hint.label}</span>
          </span>
        ))}
      </div>

      {onOpenHelp && (
        <button
          type="button"
          onClick={onOpenHelp}
          className="ml-3 shrink-0 inline-flex items-center gap-1 text-[11px] font-semibold text-blue-600 hover:text-blue-700 hover:underline cursor-pointer"
        >
          <Keyboard size={12} />
          <span>All shortcuts</span>
        </button>
      )}
    </div>
  )
}
