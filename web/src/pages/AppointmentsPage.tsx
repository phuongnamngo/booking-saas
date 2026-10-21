import { useEffect, useState, type FormEvent } from 'react'
import { api, ensureCsrfCookie } from '../api/client'
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
          setError(readApiError(caught))
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
      setError(readApiError(caught))
    }
  }

  async function createAppointment(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
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
      event.currentTarget.reset()
      await reload()
    } catch (caught) {
      setError(readApiError(caught))
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
      await reload()
    } catch (caught) {
      setError(readApiError(caught))
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
      await reload()
    } catch (caught) {
      setError(readApiError(caught))
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
      }
    } catch (caught) {
      setError(readApiError(caught))
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
      await reload()
    } catch (caught) {
      setError(readApiError(caught))
    } finally {
      setPending(false)
    }
  }

  return (
    <section>
      <h1>Lịch hẹn</h1>
      {error ? <p role="alert">{error}</p> : null}
      {loading ? <p>Đang tải</p> : null}
      <label>
        Ngày
        <input
          type="date"
          value={date}
          onChange={(event) => setDate(event.target.value)}
        />
      </label>
      <button type="button" onClick={() => setView('day')}>
        Ngày
      </button>
      <button type="button" onClick={() => setView('week')}>
        Tuần
      </button>
      <label>
        Chi nhánh
        <select
          value={branchId}
          onChange={(event) => {
            const next = event.target.value
            setBranchId(next)
            void loadAppointments(next, date, view).catch((caught) => setError(readApiError(caught)))
          }}
        >
          {branches.map((branch) => (
            <option key={branch.id} value={branch.id}>
              {branch.name ?? branch.id}
            </option>
          ))}
        </select>
      </label>
      {!loading && appointments.length === 0 ? <p>Chưa có lịch hẹn</p> : null}
      <ul>
        {appointments.map((appointment) => (
          <li key={appointment.id}>
            <span>{appointment.status}</span> · {appointment.starts_at}
            <button type="button" disabled={pending} onClick={() => void cancel(appointment)}>
              Hủy
            </button>
            <button type="button" disabled={pending} onClick={() => void pay(appointment)}>
              Thanh toán
            </button>
            {checkoutUrls[appointment.id] ? (
              <a href={checkoutUrls[appointment.id]}>Thanh toán</a>
            ) : null}
            {(nextStatus[appointment.status] ?? []).map((status) => (
              <button
                key={status}
                type="button"
                disabled={pending}
                onClick={() => void changeStatus(appointment, status)}
              >
                {status}
              </button>
            ))}
          </li>
        ))}
      </ul>

      <form onSubmit={(event) => void createAppointment(event)}>
        <h2>Tạo lịch</h2>
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

      {appointments.length > 0 ? (
        <form onSubmit={(event) => void reschedule(event)}>
          <h2>Đổi giờ</h2>
          <label>
            Lịch
            <select name="appointment_id" defaultValue={appointments[0]?.id}>
              {appointments.map((appointment) => (
                <option key={appointment.id} value={appointment.id}>
                  {appointment.starts_at}
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
          <label>
            Giờ mới
            <input name="starts_at" type="datetime-local" required />
          </label>
          <button type="submit" disabled={pending}>
            Đổi giờ
          </button>
        </form>
      ) : null}
    </section>
  )
}
