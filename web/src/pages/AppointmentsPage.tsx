import { useCallback, useEffect, useState, type CSSProperties, type FormEvent } from 'react'
import { api, ensureCsrfCookie } from '../api/client'
import { Sheet } from '../app/sheet'
import { isClosed, statusAction, statusLabel } from '../app/status'
import { useToast } from '../app/toast'
import { listFrom } from './listFrom'
import { readApiError } from './readApiError'

type Appointment = {
  id: string
  status: string
  starts_at: string
  ends_at: string
  staff_id?: string
}

type Option = { id: string; name?: string }

const nextStatus: Record<string, string[]> = {
  pending: ['confirmed', 'cancelled'],
  confirmed: ['in_progress', 'cancelled', 'no_show'],
  in_progress: ['completed', 'no_show'],
}

function today(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

function clock(iso: string): string {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) {
    return iso
  }
  return new Intl.DateTimeFormat('vi-VN', {
    timeZone: 'Asia/Ho_Chi_Minh',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

function dayLabel(iso: string): string {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) {
    return iso
  }
  return new Intl.DateTimeFormat('vi-VN', {
    timeZone: 'Asia/Ho_Chi_Minh',
    weekday: 'short',
    day: '2-digit',
    month: '2-digit',
  }).format(date)
}

function minutesBetween(startIso: string, endIso: string): number {
  return Math.max(0, Math.round((new Date(endIso).getTime() - new Date(startIso).getTime()) / 60000))
}

type SheetState = { kind: 'create' } | { kind: 'reschedule'; appointmentId: string }

function addDays(isoDate: string, days: number): string {
  const [year, month, day] = isoDate.split('-').map(Number)
  const date = new Date(year, month - 1, day)
  date.setDate(date.getDate() + days)
  const nextMonth = String(date.getMonth() + 1).padStart(2, '0')
  const nextDay = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${nextMonth}-${nextDay}`
}

export function AppointmentsPage() {
  const [branches, setBranches] = useState<Option[]>([])
  const [staff, setStaff] = useState<Option[]>([])
  const [services, setServices] = useState<Option[]>([])
  const [appointments, setAppointments] = useState<Appointment[]>([])
  const [branchId, setBranchId] = useState('')
  const [date, setDate] = useState(today)
  const [view, setView] = useState<'day' | 'week'>('day')
  const [checkoutUrls, setCheckoutUrls] = useState<Record<string, string>>({})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)
  const [sheet, setSheet] = useState<SheetState | null>(null)
  const closeSheet = useCallback(() => setSheet(null), [])
  const showToast = useToast()

  function reportError(caught: unknown) {
    const message = readApiError(caught)
    setError(message)
    showToast('error', message)
  }

  async function loadAppointments(nextBranchId: string, nextDate: string, nextView: 'day' | 'week') {
    if (nextBranchId === '') {
      setAppointments([])
      return
    }
    const params =
      nextView === 'week'
        ? { from: nextDate, to: addDays(nextDate, 6), branch_id: nextBranchId }
        : { date: nextDate, branch_id: nextBranchId }
    const response = await api.get('/api/v1/appointments', { params })
    setAppointments(listFrom<Appointment>(response.data))
  }

  useEffect(() => {
    let cancelled = false

    async function load() {
      setLoading(true)
      setError(null)
      try {
        const [branchResponse, staffResponse, serviceResponse] = await Promise.all([
          api.get('/api/v1/branches'),
          api.get('/api/v1/staff'),
          api.get('/api/v1/services'),
        ])
        if (cancelled) {
          return
        }
        const nextBranches = listFrom<Option>(branchResponse.data)
        setBranches(nextBranches)
        setStaff(listFrom<Option>(staffResponse.data))
        setServices(listFrom<Option>(serviceResponse.data))
        const nextBranchId = nextBranches[0]?.id ?? ''
        setBranchId(nextBranchId)
        await loadAppointments(nextBranchId, date, view)
      } catch (caught) {
        if (!cancelled) {
          reportError(caught)
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    void load()

    return () => {
      cancelled = true
    }
  }, [date, view])

  async function reload() {
    setError(null)
    try {
      await loadAppointments(branchId, date, view)
    } catch (caught) {
      reportError(caught)
    }
  }

  async function createAppointment(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const formElement = event.currentTarget
    const form = new FormData(formElement)
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.post('/api/v1/appointments', {
        branch_id: String(form.get('branch_id') ?? branchId),
        staff_id: String(form.get('staff_id') ?? ''),
        service_ids: form.getAll('service_ids').map(String),
        starts_at: String(form.get('starts_at') ?? ''),
        customer: {
          name: String(form.get('customer_name') ?? ''),
          phone: String(form.get('customer_phone') ?? ''),
          email: String(form.get('customer_email') ?? '') || null,
        },
      })
      showToast('success', 'Đã tạo lịch.')
      formElement.reset()
      setSheet(null)
      await reload()
    } catch (caught) {
      reportError(caught)
    } finally {
      setPending(false)
    }
  }

  async function reschedule(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const appointmentId = String(form.get('appointment_id') ?? '')
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.patch(`/api/v1/appointments/${appointmentId}`, {
        starts_at: String(form.get('starts_at') ?? ''),
        staff_id: String(form.get('staff_id') ?? '') || undefined,
      })
      showToast('success', 'Đã đổi giờ.')
      setSheet(null)
      await reload()
    } catch (caught) {
      reportError(caught)
    } finally {
      setPending(false)
    }
  }

  async function cancel(appointment: Appointment) {
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.post(`/api/v1/appointments/${appointment.id}/cancel`)
      showToast('success', 'Đã hủy lịch.')
      await reload()
    } catch (caught) {
      reportError(caught)
    } finally {
      setPending(false)
    }
  }

  async function pay(appointment: Appointment) {
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      const response = await api.post(`/api/v1/appointments/${appointment.id}/checkout`)
      const url = (response.data as { url?: string }).url
      if (url) {
        setCheckoutUrls((current) => ({ ...current, [appointment.id]: url }))
        showToast('success', 'Đã tạo liên kết thanh toán.')
      }
    } catch (caught) {
      reportError(caught)
    } finally {
      setPending(false)
    }
  }

  async function changeStatus(appointment: Appointment, status: string) {
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.post(`/api/v1/appointments/${appointment.id}/status`, { status })
      showToast('success', 'Đã đổi trạng thái.')
      await reload()
    } catch (caught) {
      reportError(caught)
    } finally {
      setPending(false)
    }
  }

  const staffName = (id?: string) => staff.find((profile) => profile.id === id)?.name
  const editingId = sheet?.kind === 'reschedule' ? sheet.appointmentId : null
  const editing = appointments.find((appointment) => appointment.id === editingId)

  return (
    <section>
      <div className="page-head">
        <div>
          <h1>Lịch hẹn</h1>
          <p className="mute">{appointments.length > 0 ? `${appointments.length} lịch trong ${view === 'day' ? 'ngày' : 'tuần'}` : 'Bảng đón khách tại quầy'}</p>
        </div>
        <div className="actions">
          <button className="primary" type="button" disabled={branches.length === 0} onClick={() => setSheet({ kind: 'create' })}>
            Tạo lịch
          </button>
        </div>
      </div>
      {error ? <p role="alert">{error}</p> : null}
      <div className="toolbar">
        <label>
          Chi nhánh
          <select
            value={branchId}
            onChange={(event) => {
              const next = event.target.value
              setBranchId(next)
              void loadAppointments(next, date, view).catch((caught) => reportError(caught))
            }}
          >
            {branches.map((branch) => (
              <option key={branch.id} value={branch.id}>
                {branch.name ?? branch.id}
              </option>
            ))}
          </select>
        </label>
        <label>
          Ngày
          <input type="date" value={date} onChange={(event) => setDate(event.target.value)} />
        </label>
        <span className="grow" />
        <div className="segment">
          <button type="button" aria-pressed={view === 'day'} onClick={() => setView('day')}>
            Ngày
          </button>
          <button type="button" aria-pressed={view === 'week'} onClick={() => setView('week')}>
            Tuần
          </button>
        </div>
      </div>
      {loading ? (
        <div className="skeleton" aria-busy="true">
          <span className="sr-only">Đang tải</span>
          <span />
          <span />
          <span />
        </div>
      ) : null}
      {!loading && appointments.length === 0 ? <p className="empty">Chưa có lịch hẹn</p> : null}
      {appointments.length > 0 ? (
        <ul className="appt-list">
          {appointments.map((appointment, index) => {
            const closed = isClosed(appointment.status)
            const name = staffName(appointment.staff_id)
            return (
              <li
                className={closed ? 'appt is-closed' : 'appt'}
                key={appointment.id}
                style={{ '--i': index } as CSSProperties}
              >
                <time className="appt-time" dateTime={appointment.starts_at}>
                  {clock(appointment.starts_at)}
                  <small>{minutesBetween(appointment.starts_at, appointment.ends_at)} phút</small>
                </time>
                <div className="appt-body">
                  <span className={`pill pill-${appointment.status}`}>{statusLabel(appointment.status)}</span>
                  <p className="row-meta">
                    {view === 'week' ? <span>{dayLabel(appointment.starts_at)}</span> : null}
                    <span>
                      {clock(appointment.starts_at)} – {clock(appointment.ends_at)}
                    </span>
                    {name ? <span>· {name}</span> : null}
                  </p>
                </div>
                <div className="appt-actions">
                  {closed ? null : (
                    <>
                      <button className="danger" type="button" disabled={pending} onClick={() => void cancel(appointment)}>
                        Hủy
                      </button>
                      <button
                        type="button"
                        disabled={pending}
                        onClick={() => setSheet({ kind: 'reschedule', appointmentId: appointment.id })}
                      >
                        Đổi giờ
                      </button>
                      <button type="button" disabled={pending} onClick={() => void pay(appointment)}>
                        Thanh toán
                      </button>
                    </>
                  )}
                  {checkoutUrls[appointment.id] ? <a href={checkoutUrls[appointment.id]}>Thanh toán</a> : null}
                  {(nextStatus[appointment.status] ?? [])
                    .filter((status) => status !== 'cancelled')
                    .map((status) => (
                      <button
                        key={status}
                        className={status === 'no_show' ? undefined : 'primary'}
                        type="button"
                        disabled={pending}
                        onClick={() => void changeStatus(appointment, status)}
                      >
                        {statusAction(status)}
                      </button>
                    ))}
                </div>
              </li>
            )
          })}
        </ul>
      ) : null}

      {sheet?.kind === 'create' ? (
        <Sheet title="Tạo lịch" onClose={closeSheet}>
          <form onSubmit={(event) => void createAppointment(event)}>
            <label>
              Chi nhánh
              <select name="branch_id" defaultValue={branchId}>
                {branches.map((branch) => (
                  <option key={branch.id} value={branch.id}>
                    {branch.name ?? branch.id}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Nhân viên
              <select name="staff_id" defaultValue={staff[0]?.id}>
                {staff.map((profile) => (
                  <option key={profile.id} value={profile.id}>
                    {profile.name ?? profile.id}
                  </option>
                ))}
              </select>
            </label>
            <fieldset>
              <legend>Dịch vụ</legend>
              {services.map((service) => (
                <label key={service.id}>
                  <input type="checkbox" name="service_ids" value={service.id} />
                  {service.name ?? service.id}
                </label>
              ))}
            </fieldset>
            <label>
              Giờ bắt đầu
              <input name="starts_at" type="datetime-local" required />
            </label>
            <label>
              Tên khách
              <input name="customer_name" required />
            </label>
            <label>
              Số điện thoại
              <input name="customer_phone" required />
            </label>
            <label>
              Email
              <input name="customer_email" type="email" />
            </label>
            <button type="submit" disabled={pending}>
              {pending ? 'Đang lưu...' : 'Tạo lịch'}
            </button>
          </form>
        </Sheet>
      ) : null}

      {sheet?.kind === 'reschedule' && editing ? (
        <Sheet title="Đổi giờ" onClose={closeSheet}>
          <form onSubmit={(event) => void reschedule(event)}>
            <input type="hidden" name="appointment_id" value={editing.id} />
            <p className="mute">
              Lịch hiện tại {clock(editing.starts_at)} · {dayLabel(editing.starts_at)}
            </p>
            <label>
              Nhân viên
              <select name="staff_id" defaultValue={editing.staff_id ?? staff[0]?.id}>
                {staff.map((profile) => (
                  <option key={profile.id} value={profile.id}>
                    {profile.name ?? profile.id}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Giờ mới
              <input name="starts_at" type="datetime-local" required />
            </label>
            <button type="submit" disabled={pending}>
              {pending ? 'Đang lưu...' : 'Đổi giờ'}
            </button>
          </form>
        </Sheet>
      ) : null}
    </section>
  )
}
