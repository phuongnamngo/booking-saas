import axios from 'axios'

export function readApiError(caught: unknown): string {
  if (axios.isAxiosError(caught)) {
    const message = caught.response?.data?.message
    if (typeof message === 'string' && message !== '') {
      return message
    }
  }

  return 'Không gửi được. Thử lại.'
}
