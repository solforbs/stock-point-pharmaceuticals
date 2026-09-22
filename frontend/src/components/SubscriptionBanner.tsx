import { AlertTriangle, Clock, Lock } from 'lucide-react'
import { Link } from 'react-router-dom'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { daysUntil, formatDate } from '../lib/format'
import { usePermission } from '../lib/permissions'

/**
 * The institution's subscription, when it needs attention: trial days left,
 * read-only after the trial or subscription ends, or suspended. Only people
 * who can manage billing get the button; everyone else is told whom to ask.
 */
export function SubscriptionBanner() {
  const { data: user } = useCurrentUser()
  const canManage = usePermission('admin.settings')
  const sub = user?.subscription
  if (!sub || sub.access_state === 'ACTIVE') return null

  const action = canManage ? (
    <Link to="/admin/billing" className="ml-auto shrink-0 rounded-lg bg-white/90 px-3 py-1 text-xs font-bold text-slate-900 hover:bg-white">
      Choose a plan
    </Link>
  ) : (
    <span className="ml-auto shrink-0 text-xs opacity-90">Ask your administrator to choose a plan.</span>
  )

  if (sub.access_state === 'TRIAL') {
    const days = Math.max(0, daysUntil(sub.trial_ends_at) ?? 0)
    return (
      <div className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm">
        <Clock size={15} className="shrink-0" />
        <span>
          Free trial: <strong>{days === 0 ? 'ends today' : `${days} day${days === 1 ? '' : 's'} left`}</strong> (until {formatDate(sub.trial_ends_at)}).
        </span>
        {action}
      </div>
    )
  }

  if (sub.access_state === 'LAPSED') {
    return (
      <div className="flex items-center gap-2 px-4 py-2 bg-amber-500 text-slate-950 text-sm">
        <Lock size={15} className="shrink-0" />
        <span><strong>Read-only.</strong> Your trial or subscription has ended; you can view and export everything, but nothing new can be recorded.</span>
        {action}
      </div>
    )
  }

  return (
    <div className="flex items-center gap-2 px-4 py-2 bg-rose-600 text-white text-sm">
      <AlertTriangle size={15} className="shrink-0" />
      <span><strong>This institution has been suspended.</strong> Contact the platform team.</span>
      {canManage && (
        <Link to="/admin/billing" className="ml-auto shrink-0 rounded-lg bg-white/90 px-3 py-1 text-xs font-bold text-slate-900 hover:bg-white">
          Details
        </Link>
      )}
    </div>
  )
}
