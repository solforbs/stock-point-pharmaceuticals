import { Navigate } from 'react-router-dom'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import HomePage from './HomePage'

/**
 * The root of the site. A visitor sees the front page immediately — the
 * profile call is never waited on, because a marketing page that blocks on
 * an API request is a marketing page nobody reads. Someone already signed in
 * is sent on to their dashboard as soon as that call comes back.
 */
export default function SiteHome() {
  const { data: user } = useCurrentUser()

  if (user) {
    return <Navigate to={user.organisation || !user.is_platform_admin ? '/dashboard' : '/platform'} replace />
  }

  return <HomePage />
}
