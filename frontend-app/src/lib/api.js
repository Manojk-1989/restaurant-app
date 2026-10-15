const apiBase = import.meta.env.VITE_API_BASE_URL ?? ''

export async function apiRequest(path, options = {}) {
  const response = await fetch(`${apiBase}${path}`, {
    ...options,
    headers: { accept: 'application/json', 'content-type': 'application/json', ...options.headers },
  })
  const result = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(result.message ?? result.error ?? `Request failed (${response.status})`)
  return result
}