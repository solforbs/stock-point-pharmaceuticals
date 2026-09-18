import { useState, type MouseEvent } from 'react'
import './charts.css'
import { ChartTooltip, type TooltipState } from './ChartTooltip'
import { Legend } from './Legend'
import { formatCompact, formatFull, niceTicks, seriesColor, type ValueFormatter } from './scale'
import { useChartWidth } from './useChartWidth'

export type LineSeries = { key: string; label: string; color?: string }
export type LinePoint = { key: string; label: string; values: Record<string, number> }

/**
 * 2px lines over a shared category axis (days), one y-scale for all series
 * (never a second axis). A crosshair tooltip reads every series at the
 * hovered point; the last value of each series is labelled at its end.
 */
export function LineChart({
  data,
  series,
  height = 220,
  format = formatCompact,
  tooltipFormat = formatFull,
  ariaLabel,
}: {
  data: LinePoint[]
  series: LineSeries[]
  height?: number
  format?: ValueFormatter
  tooltipFormat?: ValueFormatter
  ariaLabel: string
}) {
  const { ref, width } = useChartWidth<HTMLDivElement>()
  const [tip, setTip] = useState<TooltipState>(null)
  const [hover, setHover] = useState<number | null>(null)
  const colors = series.map((s, i) => s.color ?? seriesColor(i))
  const left = 48
  const right = 56
  const top = 10
  const bottom = 22
  const plotW = Math.max(40, width - left - right)
  const plotH = height - top - bottom
  const all = data.flatMap((d) => series.map((s) => d.values[s.key] ?? 0))
  const minValue = Math.min(0, ...all)
  const ticks = niceTicks(Math.max(0, ...all), 4)
  const domainMax = ticks[ticks.length - 1] || 1
  const domainMin = minValue < 0 ? -(niceTicks(-minValue, 2).slice(-1)[0] ?? 0) : 0
  const sx = (i: number) => left + (data.length <= 1 ? plotW / 2 : (i / (data.length - 1)) * plotW)
  const sy = (v: number) => top + plotH - ((v - domainMin) / (domainMax - domainMin)) * plotH
  const labelEvery = Math.max(1, Math.ceil(data.length / Math.max(1, Math.floor(plotW / 64))))
  const last = data.length - 1

  function onMove(e: MouseEvent<SVGRectElement>) {
    const box = (e.currentTarget.ownerSVGElement as SVGSVGElement).getBoundingClientRect()
    const x = e.clientX - box.left
    const i = data.length <= 1 ? 0 : Math.max(0, Math.min(last, Math.round(((x - left) / plotW) * last)))
    const point = data[i]
    if (!point) return
    setHover(i)
    setTip({ x: sx(i), y: e.clientY - box.top, title: point.label, rows: series.map((s, si) => ({ label: s.label, value: tooltipFormat(point.values[s.key] ?? 0), color: colors[si] })) })
  }

  return (
    <div ref={ref} className="viz-root relative w-full" onMouseLeave={() => { setTip(null); setHover(null) }}>
      <Legend items={series.map((s, i) => ({ key: s.key, label: s.label, color: colors[i], shape: 'line' }))} />
      <svg width={width} height={height} role="img" aria-label={ariaLabel}>
        {[...(domainMin < 0 ? [domainMin] : []), ...ticks].map((t) => {
          const y = Math.round(sy(t)) + 0.5
          return (
            <g key={t}>
              <line x1={left} x2={left + plotW} y1={y} y2={y} stroke="var(--viz-grid)" strokeWidth={1} />
              <text x={left - 6} y={y + 3.5} textAnchor="end" fontSize={10} fill="var(--text-muted)">{format(t)}</text>
            </g>
          )
        })}
        {data.map((d, i) =>
          i % labelEvery === 0 ? (
            <text key={d.key} x={sx(i)} y={height - 6} textAnchor="middle" fontSize={10} fill="var(--text-muted)">{d.label}</text>
          ) : null,
        )}
        {hover !== null && <line x1={sx(hover) + 0.5} x2={sx(hover) + 0.5} y1={top} y2={top + plotH} stroke="var(--text-muted)" strokeWidth={1} opacity={0.5} />}
        {series.map((s, si) => {
          const points = data.map((d, i) => `${sx(i)},${sy(d.values[s.key] ?? 0)}`).join(' ')
          const lastValue = data[last]?.values[s.key] ?? 0
          const hovered = hover !== null ? data[hover] : undefined
          return (
            <g key={s.key}>
              <polyline points={points} fill="none" stroke={colors[si]} strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />
              {last >= 0 && (
                <>
                  <circle cx={sx(last)} cy={sy(lastValue)} r={4} fill={colors[si]} stroke="var(--viz-surface)" strokeWidth={2} />
                  <text x={sx(last) + 8} y={sy(lastValue) + 3.5} fontSize={10.5} fontWeight={600} fill="var(--text)">{format(lastValue)}</text>
                </>
              )}
              {hover !== null && hovered && (
                <circle cx={sx(hover)} cy={sy(hovered.values[s.key] ?? 0)} r={4} fill={colors[si]} stroke="var(--viz-surface)" strokeWidth={2} />
              )}
            </g>
          )
        })}
        <rect x={left} y={top} width={plotW} height={plotH} fill="transparent" onMouseMove={onMove} />
      </svg>
      <ChartTooltip tip={tip} width={width} />
    </div>
  )
}
