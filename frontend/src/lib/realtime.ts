import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import type { ChannelAuthorizationCallback, ChannelAuthorizationData } from 'pusher-js/types/src/core/auth/options'
import { api } from './api'

/**
 * Part 17 — the websocket connection to Reverb.
 *
 * Authorisation goes through the app's own `/broadcasting/auth` endpoint on
 * the same origin, carrying the session cookie and CSRF token, so a channel
 * is only joined by someone the server already trusts. Nothing here decides
 * who may listen; routes/channels.php does.
 */

type EchoInstance = InstanceType<typeof Echo<'reverb'>>

let echo: EchoInstance | null = null

declare global {
  interface Window {
    Pusher: typeof Pusher
  }
}

export function connectRealtime(): EchoInstance | null {
  if (echo) return echo

  const key = import.meta.env.VITE_REVERB_APP_KEY
  if (!key) return null // Broadcasting is not configured here; the app works without it.

  window.Pusher = Pusher

  // Same host and port as the page: the web server proxies the socket, so
  // there is no second origin to allow and no mixed content over HTTPS.
  const https = window.location.protocol === 'https:'

  echo = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: window.location.hostname,
    wsPort: https ? 443 : Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: 443,
    forceTLS: https,
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: ChannelAuthorizationCallback) => {
        api
          .post<ChannelAuthorizationData>('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
          .then((response) => callback(null, response.data))
          .catch((error: Error) => callback(error, null))
      },
    }),
  })

  return echo
}

export function disconnectRealtime() {
  echo?.disconnect()
  echo = null
}

export function realtime(): EchoInstance | null {
  return echo
}
