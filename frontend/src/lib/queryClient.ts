import { MutationCache, QueryClient } from '@tanstack/react-query'
import { toastApiError } from './toast'

declare module '@tanstack/react-query' {
  interface Register {
    mutationMeta: { silent?: boolean }
  }
}

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 30_000,
      refetchOnWindowFocus: false,
    },
  },
  // Every failed mutation surfaces error.code + message unless the screen
  // opts out (meta.silent) because it handles the code itself (the POS does).
  mutationCache: new MutationCache({
    onError: (error, _variables, _context, mutation) => {
      if (mutation.meta?.silent) return
      toastApiError(error)
    },
  }),
})
