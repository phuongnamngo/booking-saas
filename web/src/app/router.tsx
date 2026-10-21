import { Navigate, Outlet, Route, Routes, Link } from 'react-router-dom'
import { getSession } from '../auth/session'
import { AppointmentsPage } from '../pages/AppointmentsPage'
import { BranchesPage } from '../pages/BranchesPage'
import { InvitationPage } from '../pages/InvitationPage'
import { LoginPage } from '../pages/LoginPage'
import { PublicBookingPage } from '../pages/PublicBookingPage'
import { RegisterPage } from '../pages/RegisterPage'
import { ServicesPage } from '../pages/ServicesPage'
import { StaffPage } from '../pages/StaffPage'

function AppShell() {
  return (
    <div className="screen">
      <nav>
        <Link to="/app/branches">Chi nhánh</Link>
        <Link to="/app/services">Dịch vụ</Link>
        <Link to="/app/staff">Nhân viên</Link>
        <Link to="/app/appointments">Lịch hẹn</Link>
      </nav>
      <Outlet />
    </div>
  )
}

export function AppRouter() {
  const signedIn = getSession().user !== null

  return (
    <Routes>
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/invitations/:token" element={<InvitationPage />} />
      <Route path="/t/:slug" element={<PublicBookingPage />} />
      <Route path="/app" element={signedIn ? <AppShell /> : <Navigate to="/login" replace />}>
        <Route index element={<h1>Lịch hẹn</h1>} />
        <Route path="branches" element={<BranchesPage />} />
        <Route path="services" element={<ServicesPage />} />
        <Route path="staff" element={<StaffPage />} />
        <Route path="appointments" element={<AppointmentsPage />} />
      </Route>
    </Routes>
  )
}
