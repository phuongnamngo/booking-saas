import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, ensureCsrfCookie } from '../api/client'
import { setSession } from '../auth/session'
import { readApiError } from './readApiError'

export function RegisterPage() {
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const email = String(form.get('email') ?? '').trim()
    if (email === '') {
      setError('Nhập email.')
      return
    }

    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      const response = await api.post<{ id: string }>('/api/v1/auth/register', {
        business_name: String(form.get('business_name') ?? ''),
        slug: String(form.get('slug') ?? ''),
        name: String(form.get('name') ?? ''),
        email,
        password: String(form.get('password') ?? ''),
      })
      setSession({
        id: response.data.id,
        name: String(form.get('name') ?? ''),
        email,
      })
      navigate('/app')
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
        <h1>Đăng ký</h1>
        {error ? <p role="alert">{error}</p> : null}
        <label>
          Tên cơ sở
          <input name="business_name" required />
        </label>
        <label>
          Đường dẫn
          <input name="slug" required />
        </label>
        <label>
          Tên của bạn
          <input name="name" required />
        </label>
        <label>
          Email
          <input name="email" type="email" />
        </label>
        <label>
          Mật khẩu
          <input name="password" type="password" required minLength={8} />
        </label>
        <button type="submit" disabled={pending}>
          {pending ? 'Đang tạo...' : 'Tạo tài khoản'}
        </button>
        <p>
          <Link to="/login">Đăng nhập</Link>
        </p>
      </form>
    </main>
  )
}
