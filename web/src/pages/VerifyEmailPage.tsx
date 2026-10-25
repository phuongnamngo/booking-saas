import { Link } from 'react-router-dom'

export function VerifyEmailPage() {
  return (
    <main className="stage">
      <p className="mark">Sổ lịch</p>
      <section className="stage-card">
        <span className="check" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4">
            <path d="M5 12.5l4.5 4.5L19 7.5" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </span>
        <h1>Xác thực email</h1>
        <p>Email đã được xác thực.</p>
        <Link to="/login">Đăng nhập</Link>
      </section>
    </main>
  )
}
