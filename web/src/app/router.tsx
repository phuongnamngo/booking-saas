import type { ReactNode } from 'react'
import { Navigate, NavLink, Outlet, Route, Routes } from 'react-router-dom'
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

  return (
    <div className="bell">
      <button type="button">Thông báo</button>
      <ul>
        {items
          .filter((item) => item.data?.message)
          .map((item) => (
          <li key={item.id}>
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
      <h1>Xác thực email</h1>
      <p>Mở liên kết trong email trước khi dùng quầy.</p>
      {message ? <p>{message}</p> : null}
      <button type="button" onClick={() => void resend()} disabled={pending}>
        Gửi lại email xác thực
      </button>
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

function AppShell() {
  const appointments = allows('appointments.manage') || allows('appointments.handle')

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
          <Outlet />
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
        <Route index element={<h1>Lịch hẹn</h1>} />
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
