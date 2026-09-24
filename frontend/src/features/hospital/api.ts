import { useQuery } from '@tanstack/react-query'
import { apiGet } from '../../lib/api'
import type { Encounter, Facility, HospitalLevel, LabTest, Paginated, Patient } from '../../lib/types'

export function useFacilities(enabled = true) {
  return useQuery({
    queryKey: ['hospital', 'facilities'],
    queryFn: () => apiGet<Facility[]>('/api/hospital/facilities'),
    enabled,
    staleTime: 5 * 60_000,
  })
}

export function useHospitalLevels(enabled = true) {
  return useQuery({
    queryKey: ['hospital', 'levels'],
    queryFn: () => apiGet<HospitalLevel[]>('/api/hospital/levels'),
    enabled,
    staleTime: 10 * 60_000,
  })
}

export function usePatientSearch(q: string, enabled = true) {
  return useQuery({
    queryKey: ['hospital', 'patients', q],
    queryFn: () => apiGet<Paginated<Patient>>('/api/hospital/patients', { q, per_page: 10 }),
    enabled: enabled && q.trim().length >= 2,
    placeholderData: (prev) => prev,
  })
}

export function useEncounters(params: Record<string, string | number | undefined>, enabled = true) {
  return useQuery({
    queryKey: ['hospital', 'encounters', params],
    queryFn: () => apiGet<Paginated<Encounter>>('/api/hospital/encounters', params),
    enabled,
    placeholderData: (prev) => prev,
  })
}

export function useEncounter(id: string | null) {
  return useQuery({
    queryKey: ['hospital', 'encounter', id],
    queryFn: () => apiGet<Encounter>(`/api/hospital/encounters/${id}`),
    enabled: !!id,
  })
}

/** The orderable catalogue, for the clinician's lab-order form. */
export function useOrderableLabTests(enabled = true) {
  return useQuery({
    queryKey: ['hospital', 'lab-tests'],
    queryFn: () => apiGet<LabTest[]>('/api/hospital/lab-tests'),
    enabled,
    staleTime: 5 * 60_000,
  })
}

export function patientName(p?: Patient | null): string {
  return p ? `${p.first_name} ${p.last_name}`.trim() : '—'
}

export function patientAge(p?: Patient | null): string {
  if (!p?.date_of_birth) return '—'
  const dob = new Date(p.date_of_birth)
  const years = Math.floor((Date.now() - dob.getTime()) / (365.25 * 24 * 3600 * 1000))
  return `${years} yrs`
}
