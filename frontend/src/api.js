const TOKEN_KEY = 'andeslog-token'
const QUEUE_KEY = 'andeslog-outbox-v1'

export function apiRoot() {
  return import.meta.env.VITE_API_URL || '/api'
}

export function getToken() {
  return localStorage.getItem(TOKEN_KEY) || ''
}

export function setToken(token) {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

export function idempotencyKey() {
  if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID()
  return `k-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

export function queueSize() {
  return readQueue().length
}

function readQueue() {
  try {
    const parsed = JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]')
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

function writeQueue(items) {
  localStorage.setItem(QUEUE_KEY, JSON.stringify(items))
  window.dispatchEvent(new CustomEvent('andeslog-outbox'))
}

export function enqueue(job) {
  const queue = readQueue()
  if (!queue.some((item) => item.idempotency === job.idempotency)) {
    queue.push({ ...job, at: new Date().toISOString() })
    writeQueue(queue)
  }
}

export async function flushOutbox() {
  const pending = readQueue()
  if (!pending.length || !navigator.onLine) return queueSize()
  const remain = []
  for (const job of pending) {
    try {
      await api(job.path, {
        method: job.method,
        body: job.body,
        idempotency: job.idempotency,
        queue: false,
      })
    } catch (error) {
      if (error.queued || (error.status && error.status >= 500) || !error.status) remain.push(job)
    }
  }
  writeQueue(remain)
  return remain.length
}

export async function api(path, { method = 'GET', body, idempotency, queue = true } = {}) {
  const headers = { Accept: 'application/json' }
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`
  if (idempotency) headers['Idempotency-Key'] = idempotency
  const isForm = typeof FormData !== 'undefined' && body instanceof FormData
  if (body && !isForm) headers['Content-Type'] = 'application/json'

  let response
  try {
    response = await fetch(`${apiRoot()}${path}`, {
      method,
      headers,
      body: body ? (isForm ? body : JSON.stringify(body)) : undefined,
    })
  } catch (error) {
    if (queue && method !== 'GET' && idempotency && body && !isForm) {
      enqueue({ path, method, body, idempotency })
      const queued = new Error('Sin red. El movimiento quedó en la cola del terminal y se reintenta solo.')
      queued.queued = true
      throw queued
    }
    throw new Error(error?.message || 'No hay conexión con el API.')
  }

  const text = await response.text()
  let data = null
  if (text) {
    try {
      data = JSON.parse(text)
    } catch {
      data = { message: text }
    }
  }

  if (!response.ok) {
    if (queue && response.status >= 500 && method !== 'GET' && idempotency && body && !isForm) {
      enqueue({ path, method, body, idempotency })
    }
    const message = data?.message || `Error ${response.status}`
    const err = new Error(message)
    err.status = response.status
    err.body = data
    throw err
  }

  if (data && response.headers.get('Idempotent-Replay') === 'true') data.replayed = true
  return data
}

export async function downloadInventory() {
  const response = await fetch(`${apiRoot()}/inventory/export`, {
    headers: {
      Accept: 'text/csv',
      Authorization: `Bearer ${getToken()}`,
    },
  })
  if (!response.ok) throw new Error('No se pudo exportar el inventario.')
  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'inventario.csv'
  link.click()
  URL.revokeObjectURL(url)
}

export async function bindNativeResume(onResume) {
  const { Capacitor } = await import('@capacitor/core')
  if (!Capacitor.isNativePlatform()) return
  const { App } = await import('@capacitor/app')
  await App.addListener('appStateChange', ({ isActive }) => {
    if (isActive) onResume()
  })
}
