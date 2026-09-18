import { useState } from 'react'
import './charts.css'
import { ChartTooltip, type TooltipRow, type TooltipState } from './ChartTooltip'
import { hBarPath, truncate } from './paths'
import { formatCompact, formatFull, niceTicks, seriesColor, type ValueFormatter } from './scale'
import { useChartWidth } from './useChartWidth'

export type BarDatum = { key: string; label: string; value: number; detail?: TooltipRow[] }

/**
 * Horizontal bars for ranked categories (top products, value by store).
 * One series, one colour; the value sits at each bar's tip.
 */
export function BarChart({
  data,
  format = formatCompact,
  tooltipFormat = formatFull,
  color = seriesColor(0),
  labelWidth = 150,
  ariaLabel,
}: {
  data: BarDatum[]
  format?: ValueFormatter
  tooltipFormat?: ValueFormatter
  color?: string
  labelWidth?: number
  ariaLabel: string
}) {
  const { ref, width } = useChartWidth<HTMLDivElement>()
  const [tip, setTip] = useState<TooltipState>(null)
  const [active, setActive] = useState<string | null>(null)
  const rowH = 28
  const barH = Math.min(16, rowH - 10)
  const top = 4
  const axisH = 18
  const valueRoom = 56
  const plotX = Math.min(labelWidth, width * 0.4)
  const plotW = Math.max(40, width - plotX - valueRoom)
  const max = Math.max(0, ...data.map((d) => d.value))
  const ticks = niceTicks(max, 4)
  const domain = ticks[ticks.length - 1] || 1
  const height = top + data.length * rowH + axisH
  const sx = (v: number) => (Math.max(0, v) / domain) * plotW
  const maxChars = Math.max(6, Math.floor(plotX / 6.2))

  return (
    <div ref={ref} className="viz-root relative w-full" onMouseLeave={() => { setTip(null); setActive(null) }}>
      <svg width={width} height={height} role="img" aria-label={ariaLabel}>
        {ticks.map((t) => (
          <g key={t}>
            <line x1={plotX + sx(t) + 0.5} x2={plotX + sx(t) + 0.5} y1={top} y2={top + data.length * rowH} stroke="var(--viz-grid)" strokeWidth={1} />
            <text x={plotX + sx(t)} y={height - 4} textAnchor="middle" fontSize={10} fill="var(--text-muted)">{format(t)}</text>
          </g>
        ))}
        {data.map((d, i) => {
          const y = top + i * rowH
          const w = sx(d.value)
          const dim = active !== null && active !== d.key
          return (
            <g
              key={d.key}
              onMouseMove={(e) => {
                const box = (e.currentTarget.ownerSVGElement as SVGSVGElement).getBoundingClientRect()
                setActive(d.key)
                setTip({ x: e.clientX - box.left, y: e.clientY - box.top, title: d.label, rows: [{ label: 'Value', value: tooltipFormat(d.value), color }, ...(d.detail ?? [])] })
              }}
            >
              <rect x={0} y={y} width={width} height={rowH} fill="transparent" />
              <text x={plotX - 8} y={y + rowH / 2 + 3.5} textAnchor="end" fontSize={11} fill="var(--text-secondary)">
                <title>{d.label}</title>
                {truncate(d.label, maxChars)}
              </text>
              {w > 0 && <path d={hBarPath(plotX, y + (rowH - barH) / 2, w, barH)} fill={color} opacity={dim ? 0.35 : 1} />}
              <text x={plotX + w + 6} y={y + rowH / 2 + 3.5} fontSize={11} fontWeight={600} fill="var(--text)">{format(d.value)}</text>
            </g>
          )
        })}
      </svg>
      <ChartTooltip tip={tip} width={width} />
    </div>
  )
}
