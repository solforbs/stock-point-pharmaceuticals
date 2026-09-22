import { useMutation, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, Clock, XCircle } from 'lucide-react'
import { useEffect, useRef } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError } from '../../components/ui/States'
import { apiPost } from '../../lib/api'
import type { AccessState } from '../../lib/types'

/**
 * Where Paystack sends the browser after payment. The redirect itself proves
 * nothing: the server confirms the charge with Paystack before the plan is
 * applied (Paystack's webhook would apply it anyway).
 */
export default function BillingCallbackPage() {
  const [params] = useSearchParams()
  const queryClient = useQueryClient()
  const started = useRef(false)
  const reference = params.get('reference') ?? params.get('trxref')

  const verify = useMutation({
    meta: { silent: true },
    mutationFn: (ref: string) => apiPost<{ status: 'PENDING' | 'SUCCESS' | 'FAILED'; access_state: AccessState }>('/api/billing/verify', { reference: ref }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['auth', 'user'] })
      queryClient.invalidateQueries({ queryKey: ['billing'] })
    },
  })

  useEffect(() => {
    if (!reference || started.current) return
    started.current = true
    verify.mutate(reference)
  }, [reference, verify])

  const status = verify.data?.status

  return (
    <Page>
      <PageHeader parent="Admin" title="Payment" />
      <div className="ui-card p-8 max-w-lg flex flex-col items-center text-center gap-3">
        {!reference && <p className="text-sm text-slate-600">No payment reference was returned.</p>}
        {verify.isPending && <><Clock className="w-10 h-10 text-slate-400" /><p className="text-sm text-slate-600">Confirming your payment with Paystack…</p></>}
        {verify.isError && <InlineError error={verify.error} />}
        {status === 'SUCCESS' && <><CheckCircle2 className="w-12 h-12 text-emerald-600" /><p className="text-sm text-slate-700">Payment received. Your plan is active.</p></>}
        {status === 'FAILED' && <><XCircle className="w-12 h-12 text-rose-600" /><p className="text-sm text-slate-700">The payment did not go through. No plan was changed; you can try again.</p></>}
        {status === 'PENDING' && <><Clock className="w-10 h-10 text-amber-500" /><p className="text-sm text-slate-700">Paystack has not confirmed the payment yet. It will apply automatically once it does.</p></>}
        <Link to="/admin/billing" className="text-blue-600 font-semibold hover:underline text-sm mt-2">Back to Plan & Billing</Link>
      </div>
    </Page>
  )
}
