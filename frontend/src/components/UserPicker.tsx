import { X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useUsers } from '../lib/hooks'
import type { UserRef } from '../lib/types'
import { useDebounced } from './ProductSearch'

/** Witness / approver picker over GET /api/users (id and name only). */
export function UserPicker({
  value,
  onChange,
  exclude = [],
  placeholder = 'Search users…',
  disabled,
}: {
  value: number | null
  onChange: (id: number | null, user: UserRef | null) => void
  exclude?: (number | null | undefined)[]
  placeholder?: string
  disabled?: boolean
}) {
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  const wrapRef = useRef<HTMLDivElement>(null)
  const dq = useDebounced(query.trim(), 200)
  const users = useUsers(dq)
  const all = useUsers('')
  const chosen = value !== null ? (all.data?.find((u) => u.id === value) ?? users.data?.find((u) => u.id === value) ?? null) : null

  useEffect(() => {
    function onDown(e: MouseEvent) {
      if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onDown)
    return () => document.removeEventListener('mousedown', onDown)
  }, [])

  if (value !== null) {
    return (
      <div className="ui-input flex items-center gap-2">
        <span className="flex-1 truncate">{chosen ? `${chosen.name}${chosen.username ? ` (${chosen.username})` : ''}` : `User #${value}`}</span>
        {!disabled && (
          <button type="button" aria-label="Clear user" onClick={() => onChange(null, null)} className="text-[var(--text-muted)] hover:text-[var(--text)]">
            <X size={13} />
          </button>
        )}
      </div>
    )
  }

  const options = (users.data ?? []).filter((u) => !exclude.includes(u.id))
  return (
    <div ref={wrapRef} className="relative">
      <input
        type="text"
        value={query}
        disabled={disabled}
        placeholder={placeholder}
        onChange={(e) => {
          setQuery(e.target.value)
          setOpen(true)
        }}
        onFocus={() => setOpen(true)}
        className="ui-input"
        autoComplete="off"
      />
      {open && (
        <div className="absolute z-30 left-0 right-0 top-9 ui-card shadow-xl max-h-60 overflow-y-auto">
          {options.length === 0 ? (
            <div className="px-3 py-2 text-[11.5px] text-[var(--text-muted)]">{users.isFetching ? 'Searching…' : 'No users match.'}</div>
          ) : (
            options.map((u) => (
              <button
                key={u.id}
                type="button"
                onClick={() => {
                  onChange(u.id, u)
                  setOpen(false)
                  setQuery('')
                }}
                className="w-full text-left px-3 py-1.5 border-b border-[var(--border)] last:border-b-0 hover:bg-[var(--surface-2)] text-[12.5px]"
              >
                {u.name} <span className="text-[var(--text-muted)]">{u.username ? `· ${u.username}` : ''} · #{u.id}</span>
              </button>
            ))
          )}
        </div>
      )}
    </div>
  )
}
