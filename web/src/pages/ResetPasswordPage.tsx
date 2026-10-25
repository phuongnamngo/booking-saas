import { useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, ensureCsrfCookie } from '../api/client'
import { readApiError } from './readApiError'

export function ResetPasswordPage() {
  const [params] = useSearchParams()
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState(false)
  const [pending, setPending] = useState(false)

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const password = String(form.get('password') ?? '')
    const passwordConfirmation = String(form.get('password_confirmation') ?? '')

    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.post('/api/v1/auth/reset-password', {
        email: params.get('email') ?? '',
        token: params.get('token') ?? '',
        password,
        password_confirmation: passwordConfirmation,
      })
      setDone(true)
    } catch (caught) {
      setError(readApiError(caught))
    } finally {
      setPending(false)
    }
  }

  return (
    <main className="stage">
      <p className="mark">Sổ lịch</p>
      <form onSubmit={onSubmit}>
        <h1>Đặt lại mật khẩu</h1>
        {error ? <p role="alert">{error}</p> : null}
        {done ? <p>Đã đặt lại mật khẩu.</p> : null}
        <label>
          Mật khẩu mới
          <input name="password" type="password" />
        </label>
        <label>
          Nhập lại mật khẩu
          <input name="password_confirmation" type="password" />
        </label>
        <button type="submit" disabled={pending}>
          {pending ? 'Đang lưu...' : 'Đặt lại mật khẩu'}
        </button>
        <p className="form-links">
          <Link to="/login">Quay lại đăng nhập</Link>
        </p>
      </form>
    </main>
  )
}
