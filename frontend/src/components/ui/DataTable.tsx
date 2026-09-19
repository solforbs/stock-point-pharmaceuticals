import { ChevronDown, ChevronRight, ChevronsUpDown, ChevronUp } from 'lucide-react'
import { useMemo, useState, type ReactNode } from 'react'
import { EmptyState, ErrorState, LoadingSkeleton } from './States'

export type Column<T> = {
  key: string
  header: ReactNode
  render: (row: T) => ReactNode
  sortValue?: (row: T) => string | number | null | undefined
  sortKey?: string
  sortable?: boolean
  align?: 'left' | 'right' | 'center'
  width?: string
  className?: string
}

type Props<T> = {
  columns: Column<T>[]
  rows: T[] | undefined
  rowKey: (row: T) => string
  isLoading?: boolean
  error?: unknown
  onRetry?: () => void
  onRowClick?: (row: T) => void
  selectedKey?: string | null
  emptyTitle?: string
  emptyHint?: ReactNode
  renderExpanded?: (row: T) => ReactNode
  footer?: ReactNode
  maxHeight?: string
  initialSort?: { key: string; dir: 'asc' | 'desc' }
  sort?: { key: string; dir: 'asc' | 'desc' } | null
  onSortChange?: (sort: { key: string; dir: 'asc' | 'desc' } | null) => void
  rowClassName?: (row: T) => string
}

const alignClass = { left: 'text-left', right: 'text-right', center: 'text-center' }

/**
 * Part 1.5 — DataTable: dense, sortable, sticky header. Expandable rows are
 * used for batch detail under a stock line. Supports both client-side and
 * server-side sorting (when onSortChange is supplied).
 */
export function DataTable<T>({
  columns,
  rows,
  rowKey,
  isLoading,
  error,
  onRetry,
  onRowClick,
  selectedKey,
  emptyTitle = 'Nothing here yet',
  emptyHint,
  renderExpanded,
  footer,
  maxHeight,
  initialSort,
  sort,
  onSortChange,
  rowClassName,
}: Props<T>) {
  const [internalSort, setInternalSort] = useState<{ key: string; dir: 'asc' | 'desc' } | null>(initialSort ?? null)
  const [expanded, setExpanded] = useState<Set<string>>(new Set())

  const effectiveSort = onSortChange !== undefined ? (sort ?? null) : internalSort

  const sorted = useMemo(() => {
    if (!rows) return []
    // When onSortChange is supplied, sorting is handled server-side across the whole dataset
    if (onSortChange) return rows
    if (!effectiveSort) return rows
    const column = columns.find((c) => (c.sortKey || c.key) === effectiveSort.key)
    if (!column?.sortValue) return rows
    const getter = column.sortValue
    return [...rows].sort((a, b) => {
      const x = getter(a)
      const y = getter(b)
      if (x === y) return 0
      if (x === null || x === undefined) return 1
      if (y === null || y === undefined) return -1
      const cmp = typeof x === 'number' && typeof y === 'number' ? x - y : String(x).localeCompare(String(y), undefined, { numeric: true })
      return effectiveSort.dir === 'asc' ? cmp : -cmp
    })
  }, [rows, effectiveSort, columns, onSortChange])

  function toggleSort(column: Column<T>) {
    const isSortable = Boolean(column.sortValue || column.sortable || column.sortKey)
    if (!isSortable) return

    const key = column.sortKey || column.key
    let nextSort: { key: string; dir: 'asc' | 'desc' } | null = null

    if (effectiveSort?.key !== key) {
      nextSort = { key, dir: 'asc' }
    } else if (effectiveSort.dir === 'asc') {
      nextSort = { key, dir: 'desc' }
    } else {
      nextSort = null
    }

    if (onSortChange) {
      onSortChange(nextSort)
    } else {
      setInternalSort(nextSort)
    }
  }

  function toggleExpanded(key: string) {
    setExpanded((prev) => {
      const next = new Set(prev)
      if (next.has(key)) next.delete(key)
      else next.add(key)
      return next
    })
  }

  if (error) return <ErrorState error={error} onRetry={onRetry} />
  if (isLoading && !rows) return <LoadingSkeleton rows={6} />
  if (!isLoading && rows && rows.length === 0) return <EmptyState title={emptyTitle} hint={emptyHint} />

  const colSpan = columns.length + (renderExpanded ? 1 : 0)

  return (
    <div className="overflow-auto rounded-[24px]" style={maxHeight ? { maxHeight } : undefined}>
      <table className="ui-table">
        <thead>
          <tr>
            {renderExpanded && <th style={{ width: 28 }} />}
            {columns.map((column) => {
              const sortKey = column.sortKey || column.key
              const isSortable = Boolean(column.sortValue || column.sortable || column.sortKey)
              const isCurrentSort = effectiveSort?.key === sortKey

              return (
                <th
                  key={column.key}
                  style={column.width ? { width: column.width } : undefined}
                  className={`${alignClass[column.align ?? 'left']} ${isSortable ? 'cursor-pointer select-none hover:text-[var(--text)]' : ''}`}
                  onClick={() => toggleSort(column)}
                >
                  <span className="inline-flex items-center gap-1">
                    {column.header}
                    {isSortable &&
                      (isCurrentSort ? (
                        effectiveSort?.dir === 'asc' ? (
                          <ChevronUp size={11} />
                        ) : (
                          <ChevronDown size={11} />
                        )
                      ) : (
                        <ChevronsUpDown size={11} className="opacity-40" />
                      ))}
                  </span>
                </th>
              )
            })}
          </tr>
        </thead>
        <tbody>
          {sorted.map((row) => {
            const key = rowKey(row)
            const isOpen = expanded.has(key)
            return (
              <RowGroup key={key}>
                <tr
                  className={`${onRowClick ? 'is-clickable' : ''} ${selectedKey === key ? 'is-selected' : ''} ${rowClassName?.(row) ?? ''}`}
                  onClick={() => onRowClick?.(row)}
                >
                  {renderExpanded && (
                    <td className="!px-1">
                      <button
                        type="button"
                        aria-label={isOpen ? 'Collapse' : 'Expand'}
                        className="p-1 rounded text-[var(--text-muted)] hover:text-[var(--text)]"
                        onClick={(e) => {
                          e.stopPropagation()
                          toggleExpanded(key)
                        }}
                      >
                        {isOpen ? <ChevronDown size={13} /> : <ChevronRight size={13} />}
                      </button>
                    </td>
                  )}
                  {columns.map((column) => (
                    <td key={column.key} className={`${alignClass[column.align ?? 'left']} ${column.className ?? ''}`}>
                      {column.render(row)}
                    </td>
                  ))}
                </tr>
                {renderExpanded && isOpen && (
                  <tr>
                    <td colSpan={colSpan} className="bg-[var(--surface-2)] !p-0">
                      {renderExpanded(row)}
                    </td>
                  </tr>
                )}
              </RowGroup>
            )
          })}
        </tbody>
        {footer && <tfoot>{footer}</tfoot>}
      </table>
    </div>
  )
}

function RowGroup({ children }: { children: ReactNode }) {
  return <>{children}</>
}
