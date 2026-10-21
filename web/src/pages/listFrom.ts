export function listFrom<T>(data: unknown): T[] {
  if (Array.isArray(data)) {
    return data as T[]
  }

  if (data && typeof data === 'object' && 'data' in data && Array.isArray(data.data)) {
    return data.data as T[]
  }

  return []
}
