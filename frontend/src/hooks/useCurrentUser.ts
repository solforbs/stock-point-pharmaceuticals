import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import type { CurrentUser } from '../lib/types'

export function useCurrentUser() {
  return useQuery({
    queryKey: ['auth', 'user'],
    queryFn: async () => {
      const { data } = await api.get<CurrentUser>('/api/user')
      return data
    },
    retry: false,
  })
}
