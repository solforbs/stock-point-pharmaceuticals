export type LegendItem = { key: string; label: string; color: string; shape?: 'square' | 'line' }

/** Always shown for two or more series; identity never rests on colour-matching alone. */
export function Legend({ items }: { items: LegendItem[] }) {
  if (items.length < 2) return null
  return (
    <ul className="flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-[var(--text-secondary)] mb-2" aria-label="Legend">
      {items.map((item) => (
        <li key={item.key} className="inline-flex items-center gap-1.5">
          {item.shape === 'line' ? (
            <span className="inline-block w-3.5 h-[2px] rounded" style={{ background: item.color }} aria-hidden />
          ) : (
            <span className="inline-block w-2.5 h-2.5 rounded-[3px]" style={{ background: item.color }} aria-hidden />
          )}
          {item.label}
        </li>
      ))}
    </ul>
  )
}
