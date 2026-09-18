import { AnimatePresence, motion } from 'framer-motion'
import { CheckCircle2, RefreshCw, WifiOff } from 'lucide-react'
import { useNetworkStatus } from '../../hooks/useNetworkStatus'

export function OfflineBanner() {
  const { isOnline, wasOffline } = useNetworkStatus()

  // Show if offline, or if we just recovered from being offline
  const visible = !isOnline || wasOffline

  return (
    <AnimatePresence>
      {visible && (
        <motion.div
          initial={{ y: -40, opacity: 0 }}
          animate={{ y: 0, opacity: 1 }}
          exit={{ y: -40, opacity: 0 }}
          transition={{ duration: 0.25 }}
          className="fixed top-0 left-0 right-0 z-[60] flex items-center justify-center pointer-events-none"
        >
          <div
            className={`pointer-events-auto mt-2.5 px-4 py-2 rounded-2xl shadow-lg border backdrop-blur-md flex items-center gap-2.5 text-[12.5px] font-bold ${
              !isOnline
                ? 'bg-amber-500/95 text-slate-950 border-amber-400 shadow-amber-500/20'
                : 'bg-emerald-600/95 text-white border-emerald-500 shadow-emerald-600/20'
            }`}
          >
            {!isOnline ? (
              <>
                <WifiOff size={16} className="animate-pulse shrink-0" />
                <span>Working in Offline Mode — changes will queue for automatic eTIMS sync when reconnected</span>
              </>
            ) : (
              <>
                <CheckCircle2 size={16} className="shrink-0 text-emerald-200" />
                <span>Internet Reconnected — syncing live inventory ledgers</span>
                <RefreshCw size={13} className="animate-spin text-emerald-200 ml-1" />
              </>
            )}
          </div>
        </motion.div>
      )}
    </AnimatePresence>
  )
}
