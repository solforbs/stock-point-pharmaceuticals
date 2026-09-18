import { Link, useLocation } from 'react-router-dom'

/**
 * Catch-all for addresses that are not a screen. Every sidebar entry has a
 * real page, so reaching this means a mistyped or outdated link.
 */
export default function Placeholder() {
  const { pathname } = useLocation()

  return (
    <div className="p-7">
      <h1 className="text-[19px] font-extrabold text-[var(--text)]">Page not found</h1>
      <p className="text-[12px] text-[var(--text-muted)] mt-2 max-w-md">
        There is no screen at <span className="tabular font-semibold">{pathname}</span>. Use the menu on the left, or go back to the{' '}
        <Link to="/dashboard" className="underline">dashboard</Link>.
      </p>
    </div>
  )
}
