import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef } from 'react'
import { connectRealtime } from '../lib/realtime'
import { toast } from '../lib/toast'
import { useCurrentUser } from './useCurrentUser'

export type PatientFlowEvent = {
  kind: 'ENCOUNTER' | 'LAB_ORDER' | 'PRESCRIPTION'
  id: string
  number: string
  status: string
  encounter_id?: string
  facility_id?: string
  branch_id?: string | null
}

/**
 * Live patient flow over Reverb: the moment an encounter changes stage, a
 * lab order advances, or a prescription is written or dispensed, every
 * watching queue (reception, clinician, lab bench, pharmacy) refetches.
 * Without a websocket the pages still work — they just refresh on the
 * usual react-query cadence.
 *
 * A page that wants to react to a specific event beyond refetching (the
 * lab bench pops a new order open, say) passes `onEvent`; the latest
 * callback is kept in a ref so it never re-subscribes the channel.
 */
export function usePatientFlowRealtime(onEvent?: (event: PatientFlowEvent) => void) {
  const { data: user } = useCurrentUser()
  const queryClient = useQueryClient()
  const organisationId = user?.organisation?.id
  const onEventRef = useRef(onEvent)
  onEventRef.current = onEvent

  useEffect(() => {
    if (!organisationId) return
    const echo = connectRealtime()
    if (!echo) return

    const channelName = `healthcare.${organisationId}`
    echo.private(channelName).listen('.patient-flow.updated', (event: PatientFlowEvent) => {
      const noun = { ENCOUNTER: 'Encounter', LAB_ORDER: 'Lab order', PRESCRIPTION: 'Prescription' }[event.kind]
      toast.info(`${noun} ${event.number}`, `Now ${event.status.replaceAll('_', ' ').toLowerCase()}`)
      if (event.kind === 'ENCOUNTER') {
        queryClient.invalidateQueries({ queryKey: ['hospital', 'encounters'] })
        queryClient.invalidateQueries({ queryKey: ['hospital', 'encounter', event.id] })
        queryClient.invalidateQueries({ queryKey: ['hospital', 'patient'] })
      } else if (event.kind === 'LAB_ORDER') {
        queryClient.invalidateQueries({ queryKey: ['laboratory', 'orders'] })
        queryClient.invalidateQueries({ queryKey: ['laboratory', 'order', event.id] })
        if (event.encounter_id) queryClient.invalidateQueries({ queryKey: ['hospital', 'encounter', event.encounter_id] })
      } else if (event.kind === 'PRESCRIPTION') {
        queryClient.invalidateQueries({ queryKey: ['prescriptions'] })
        if (event.encounter_id) queryClient.invalidateQueries({ queryKey: ['hospital', 'encounter', event.encounter_id] })
      }
      onEventRef.current?.(event)
    })

    return () => {
      echo.leave(channelName)
    }
  }, [organisationId, queryClient])
}
