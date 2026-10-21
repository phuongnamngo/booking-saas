import { Navigate, NavLink, Outlet, Route, Routes } from 'react-router-dom'
import { useEffect, useState } from 'react'
import { api, ensureCsrfCookie } from '../api/client'
import { getSession } from '../auth/session'
import { AppointmentsPage } from '../pages/AppointmentsPage'
import { BranchesPage } from '../pages/BranchesPage'
import { InvitationPage } from '../pages/InvitationPage'
import { LoginPage } from '../pages/LoginPage'
import { PublicBookingPage } from '../pages/PublicBookingPage'
import { RegisterPage } from '../pages/RegisterPage'
import { ReportsPage } from '../pages/ReportsPage'
import { ServicesPage } from '../pages/ServicesPage'
import { StaffPage } from '../pages/StaffPage'
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

function RequireSession() {
  if (getSession().user === null) {
    return <Navigate to="/login" replace />
  }

  return <AppShell />
}

function AppShell() {
  return (
    <div className="desk">
      <aside className="rail">
        <p className="mark">Sổ lịch</p>
        <p className="mark-sub">Lễ tân</p>
        <nav>
          <NavLink to="/app/branches">Chi nhánh</NavLink>
          <NavLink to="/app/services">Dịch vụ</NavLink>
          <NavLink to="/app/staff">Nhân viên</NavLink>
          <NavLink to="/app/appointments">Lịch hẹn</NavLink>
          <NavLink to="/app/reports">Báo cáo</NavLink>
        </nav>
      </aside>
      <div className="desk-main">
        <header className="desk-bar">
          <NotificationBell />
        </header>
        <Outlet />
      </div>
    </div>
  )
}

export function AppRouter() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/invitations/:token" element={<InvitationPage />} />
      <Route path="/t/:slug" element={<PublicBookingPage />} />
      <Route path="/app" element={<RequireSession />}>
        <Route index element={<h1>Lịch hẹn</h1>} />
        <Route path="branches" element={<BranchesPage />} />
        <Route path="services" element={<ServicesPage />} />
        <Route path="staff" element={<StaffPage />} />
        <Route path="appointments" element={<AppointmentsPage />} />
        <Route path="reports" element={<ReportsPage />} />
      </Route>
    </Routes>
  )
}
