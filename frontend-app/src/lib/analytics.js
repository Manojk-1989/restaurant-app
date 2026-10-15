const inMemoryIds = new Map()
let lastPageView = null

function createId() {
  return globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function storedId(storageName, key) {
  try {
    const storage = window[storageName]
    let id = storage.getItem(key)
    if (!id) {
      id = createId()
      storage.setItem(key, id)
    }
    return id
  } catch {
    if (!inMemoryIds.has(key)) inMemoryIds.set(key, createId())
    return inMemoryIds.get(key)
  }
}

export function getIdentity() {
  return {
    visitor_id: storedId('localStorage', 'good_table_visitor'),
    session_id: storedId('sessionStorage', 'good_table_session'),
  }
}

export function track(name, properties = {}) {
  const identity = getIdentity()
  const payload = {
    event_id: createId(),
    name,
    timestamp: new Date().toISOString(),
    ...identity,
    path: window.location.pathname,
    properties,
  }
  return fetch(`${import.meta.env.VITE_API_BASE_URL ?? ''}/api/events`, {
    method: 'POST',
    headers: { accept: 'application/json', 'content-type': 'application/json' },
    body: JSON.stringify(payload),
    keepalive: true,
  }).catch(() => undefined)
}

export function trackPageView(title) {
  const path = window.location.pathname
  if (lastPageView === path) return Promise.resolve()
  lastPageView = path
  return track('page_view', { title })
}