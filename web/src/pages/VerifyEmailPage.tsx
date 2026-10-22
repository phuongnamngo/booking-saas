import { Link } from 'react-router-dom'

export function VerifyEmailPage() {
  return (
    <main className="stage">
      <p className="mark">Sổ lịch</p>
      <h1>Xác thực email</h1>
      <p>Email đã được xác thực.</p>
      <p>
        <Link to="/login">Đăng nhập</Link>
      </p>
    </main>
  )
}
