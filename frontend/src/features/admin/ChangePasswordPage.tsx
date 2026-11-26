import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Navigate, useNavigate } from 'react-router-dom'
import { Button } from '../../components/ui/primitives'
import { forgetCachedUser, useCurrentUser } from '../../hooks/useCurrentUser'
import { api } from '../../lib/api'
import { PasswordForm } from './MyAccountPage'

/**
 * Where a temporary password is replaced. It stands outside the application
 * frame because nothing else answers until the password has changed.
 */
export default function ChangePasswordPage() {
  const { data: user } = useCurrentUser()
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const signOut = useMutation({
    mutationFn: () => api.post('/auth/logout'),
    onSuccess: () => {
      forgetCachedUser()
      queryClient.clear()
      navigate('/login', { replace: true })
    },
  })

  if (user && !user.must_change_password) return <Navigate to="/dashboard" replace />

  return (
    <div className="min-h-svh bg-slate-50 px-4 py-10">
      <div className="max-w-2xl mx-auto space-y-4">
        <div>
          <h1 className="text-xl font-semibold text-slate-900">Choose a new password</h1>
          <p className="text-sm text-slate-600 mt-1">Your current password is temporary. Replace it to carry on.</p>
        </div>
        {/* Requests refused under the old password are refetched once it is replaced. */}
        <PasswordForm onChanged={() => queryClient.invalidateQueries()} />
        <Button variant="ghost" size="sm" onClick={() => signOut.mutate()} disabled={signOut.isPending}>
          Sign out
        </Button>
      </div>
    </div>
  )
}
