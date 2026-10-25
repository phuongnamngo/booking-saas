import type { ReactNode } from 'react'
import { Navigate, NavLink, Outlet, Route, Routes, useLocation } from 'react-router-dom'
import { useEffect, useState } from 'react'
import { api, ensureCsrfCookie } from '../api/client'
import { getSession } from '../auth/session'
import { AdminPage } from '../pages/AdminPage'
import { AppointmentsPage } from '../pages/AppointmentsPage'
import { BranchesPage } from '../pages/BranchesPage'
import { InvitationPage } from '../pages/InvitationPage'
import { ForgotPasswordPage } from '../pages/ForgotPasswordPage'
import { LoginPage } from '../pages/LoginPage'
import { ManageAppointmentPage } from '../pages/ManageAppointmentPage'
import { ResetPasswordPage } from '../pages/ResetPasswordPage'
import { VerifyEmailPage } from '../pages/VerifyEmailPage'
import { ProfilePage } from '../pages/ProfilePage'
import { PublicBookingPage } from '../pages/PublicBookingPage'
import { RegisterPage } from '../pages/RegisterPage'
import { ReportsPage } from '../pages/ReportsPage'
import { ServicesPage } from '../pages/ServicesPage'
import { StaffPage } from '../pages/StaffPage'
import { ToastProvider } from './toast'
import { listFrom } from '../pages/listFrom'

type NotificationItem = {
  id: string
  data: { message?: string }
  read_at: string | null
}

function NotificationBell() {
  const [items, setItems] = useState<NotificationItem[]>([])
  const [open, setOpen] = useState(false)

  useEffect(() => {
    let cancelled = false

    async function load() {
      try {
        const response = await api.get('/api/v1/notifications')
        if (!cancelled) {
          setItems(listFrom<NotificationItem>(response.data))
        }
      } catch {
        if (!cancelled) {
          setItems([])
        }
      }
    }

    void load()

    return () => {
      cancelled = true
    }
  }, [])

  async function markRead(id: string) {
    await ensureCsrfCookie()
    await api.post(`/api/v1/notifications/${id}/read`)
    setItems((current) =>
      current.map((item) => (item.id === id ? { ...item, read_at: new Date().toISOString() } : item)),
    )
  }

  const visible = items.filter((item) => item.data?.message)
  const unread = visible.some((item) => item.read_at === null)

  return (
    <div className="bell">
      <button
        type="button"
        className="bell-toggle"
        aria-label="Thông báo"
        aria-expanded={open}
        onClick={() => setOpen((current) => !current)}
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
          <path d="M6 9a6 6 0 1 1 12 0c0 5 2 6.5 2 6.5H4S6 14 6 9Z" strokeLinejoin="round" />
          <path d="M10 19a2 2 0 0 0 4 0" strokeLinecap="round" />
        </svg>
        {unread ? <span className="bell-dot" /> : null}
      </button>
      <ul hidden={!open}>
        {visible.length === 0 ? <li className="bell-empty">Chưa có thông báo</li> : null}
        {visible.map((item) => (
          <li key={item.id} className={item.read_at === null ? 'is-unread' : undefined}>
            <button type="button" onClick={() => void markRead(item.id)}>
              {item.data?.message}
            </button>
          </li>
        ))}
      </ul>
    </div>
  )
}

function VerificationWall() {
  const [message, setMessage] = useState<string | null>(null)
  const [pending, setPending] = useState(false)

  async function resend() {
    setPending(true)
    try {
      await ensureCsrfCookie()
      await api.post('/api/v1/auth/email/verification-notification')
      setMessage('Đã gửi lại email xác thực.')
    } catch {
      setMessage('Không gửi được email.')
    } finally {
      setPending(false)
    }
  }

  return (
    <main className="stage">
      <p className="mark">Sổ lịch</p>
      <section className="stage-card">
        <h1>Xác thực email</h1>
        <p className="mute">Mở liên kết trong email trước khi dùng quầy.</p>
        {message ? <p>{message}</p> : null}
        <button className="primary" type="button" onClick={() => void resend()} disabled={pending}>
          Gửi lại email xác thực
        </button>
      </section>
    </main>
  )
}

function RequireAdmin() {
  const user = getSession().user

  if (user === null || !user.isSuperAdmin) {
    return <Navigate to="/login" replace />
  }

  return <AdminPage />
}

function RequireSession() {
  const user = getSession().user

  if (user === null) {
    return <Navigate to="/login" replace />
  }

  if (user.isSuperAdmin) {
    return <Navigate to="/admin" replace />
  }

  if (user.emailVerifiedAt === null) {
    return <VerificationWall />
  }

  return <AppShell />
}

function allows(permission: string): boolean {
  return getSession().user?.permissions.includes(permission) ?? false
}

function RequirePermission({
  permission,
  anyOf,
  children,
}: {
  permission?: string
  anyOf?: string[]
  children: ReactNode
}) {
  const allowed = permission !== undefined ? allows(permission) : (anyOf ?? []).some((item) => allows(item))

  if (!allowed) {
    return <Navigate to="/app/appointments" replace />
  }

  return children
}

function DeskHome() {
  const appointments = allows('appointments.manage') || allows('appointments.handle')

  return <Navigate to={appointments ? '/app/appointments' : '/app/profile'} replace />
}

function AppShell() {
  const appointments = allows('appointments.manage') || allows('appointments.handle')
  const location = useLocation()

  return (
    <div className="desk">
      <aside className="rail">
        <p className="mark">Sổ lịch</p>
        <p className="mark-sub">Lễ tân</p>
        <nav>
          {allows('branches.manage') ? <NavLink to="/app/branches">Chi nhánh</NavLink> : null}
          {allows('services.manage') ? <NavLink to="/app/services">Dịch vụ</NavLink> : null}
          {allows('staff.manage') ? <NavLink to="/app/staff">Nhân viên</NavLink> : null}
          {appointments ? <NavLink to="/app/appointments">Lịch hẹn</NavLink> : null}
          {allows('appointments.manage') ? <NavLink to="/app/reports">Báo cáo</NavLink> : null}
          <NavLink to="/app/profile">Hồ sơ</NavLink>
        </nav>
      </aside>
      <ToastProvider>
        <div className="desk-main">
          <header className="desk-bar">
            <NotificationBell />
          </header>
          <div className="page" key={location.pathname}>
            <Outlet />
          </div>
        </div>
      </ToastProvider>
    </div>
  )
}

export function AppRouter() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
      <Route path="/verify-email" element={<VerifyEmailPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/invitations/:token" element={<InvitationPage />} />
      <Route path="/t/:slug" element={<PublicBookingPage />} />
      <Route path="/t/:slug/appointments/:id" element={<ManageAppointmentPage />} />
      <Route path="/admin" element={<RequireAdmin />} />
      <Route path="/app" element={<RequireSession />}>
        <Route index element={<DeskHome />} />
        <Route path="branches" element={<RequirePermission permission="branches.manage"><BranchesPage /></RequirePermission>} />
        <Route path="services" element={<RequirePermission permission="services.manage"><ServicesPage /></RequirePermission>} />
        <Route path="staff" element={<RequirePermission permission="staff.manage"><StaffPage /></RequirePermission>} />
        <Route
          path="appointments"
          element={
            <RequirePermission anyOf={['appointments.manage', 'appointments.handle']}>
              <AppointmentsPage />
            </RequirePermission>
          }
        />
        <Route path="reports" element={<RequirePermission permission="appointments.manage"><ReportsPage /></RequirePermission>} />
        <Route path="profile" element={<ProfilePage />} />
      </Route>
    </Routes>
  )
}
