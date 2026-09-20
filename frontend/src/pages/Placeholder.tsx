import { Link, useLocation } from 'react-router-dom'

/**
 * Catch-all for addresses that are not a screen. Every sidebar entry has a
 * real page, so reaching this means a mistyped or outdated link.
 */
export default function Placeholder() {
  const { pathname } = useLocation()

  return (
    <div className="p-7">
      <h1 className="text-xl font-extrabold text-slate-900">Page not found</h1>
      <p className="text-xs text-slate-500 mt-2 max-w-md">
        There is no screen at <span className="tabular font-semibold font-mono">{pathname}</span>. Use the menu on the left, or go back to the{' '}
        <Link to="/dashboard" className="underline text-blue-600 hover:text-blue-700">dashboard</Link>.
      </p>
    </div>
  )
}
