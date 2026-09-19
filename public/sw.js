/*
 * Part 16.7 — lets the till open during an internet outage.
 *
 * Only the web app itself is cached: the HTML shell (network first, so a
 * deploy reaches every browser on its next online load) and the hashed
 * files under /spa/ (cache first; a new build has new names). API calls are
 * never cached here; the POS keeps its own price list and outbox in
 * IndexedDB, and everything else simply waits for the connection.
 *
 * The cache is a convenience, never a dependency: if the browser's cache
 * storage fails (full disk, a damaged profile) every request still goes to
 * the network exactly as it would with no service worker at all.
 */
const SHELL_CACHE = 'stockpoint-shell-v1'
const ASSET_CACHE = 'stockpoint-assets-v1'
const SERVER_PATHS = /^\/(api|auth|sanctum|broadcasting|storage|up)(\/|$)/

self.addEventListener('install', (event) => {
  event.waitUntil(
    (async () => {
      try {
        const response = await fetch('/', { cache: 'no-store', credentials: 'same-origin' })
        if (response.ok) {
          await (await caches.open(SHELL_CACHE)).put('/', response.clone())
          const html = await response.text()
          const assets = [...html.matchAll(/(?:src|href)="(\/spa\/[^"]+)"/g)].map((m) => m[1])
          const cache = await caches.open(ASSET_CACHE)
          await Promise.all(assets.map((url) => cache.add(url).catch(() => undefined)))
        }
      } catch {
        // Offline, or no cache storage: the shell is cached on a later load.
      }
      await self.skipWaiting()
    })(),
  )
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      try {
        const keep = new Set([SHELL_CACHE, ASSET_CACHE])
        for (const name of await caches.keys()) {
          if (!keep.has(name)) await caches.delete(name)
        }
      } catch {
        // nothing to tidy
      }
      await self.clients.claim()
    })(),
  )
})

self.addEventListener('fetch', (event) => {
  const request = event.request
  if (request.method !== 'GET') return
  const url = new URL(request.url)
  if (url.origin !== self.location.origin || SERVER_PATHS.test(url.pathname)) return

  if (request.mode === 'navigate') {
    event.respondWith(shell(request))
    return
  }
  if (url.pathname.startsWith('/spa/')) {
    event.respondWith(asset(request))
  }
})

async function cached(cacheName, key) {
  try {
    return await (await caches.open(cacheName)).match(key)
  } catch {
    return undefined
  }
}

async function store(cacheName, key, response) {
  try {
    await (await caches.open(cacheName)).put(key, response)
  } catch {
    // not cached this time; the request itself still succeeded
  }
}

/** Every client-side route is the same HTML; keep the latest copy for outages. */
async function shell(request) {
  let response
  try {
    response = await fetch(request)
  } catch (error) {
    const copy = await cached(SHELL_CACHE, '/')
    if (copy) return copy
    throw error
  }
  if (response.ok && (response.headers.get('content-type') || '').includes('text/html')) {
    await store(SHELL_CACHE, '/', response.clone())
  }
  return response
}

async function asset(request) {
  const copy = await cached(ASSET_CACHE, request)
  if (copy) return copy
  const response = await fetch(request)
  if (response.ok) await store(ASSET_CACHE, request, response.clone())
  return response
}
