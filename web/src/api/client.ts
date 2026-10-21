import axios from 'axios'

export const api = axios.create({
  baseURL: '',
  withCredentials: true,
})

export async function ensureCsrfCookie(): Promise<void> {
  await api.get('/sanctum/csrf-cookie')
}
