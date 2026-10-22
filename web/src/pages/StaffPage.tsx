import { useEffect, useState, type FormEvent } from 'react'
import { api, ensureCsrfCookie } from '../api/client'
import { useToast } from '../app/toast'
import { listFrom } from './listFrom'
import { readApiError } from './readApiError'

type StaffItem = {
  id: string
  name: string
  user_id: string
  branch_ids: string[]
  service_ids: string[]
}

type Option = { id: string; name: string }

const weekdays = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy']

export function StaffPage() {
  const [staff, setStaff] = useState<StaffItem[]>([])
  const [branches, setBranches] = useState<Option[]>([])
  const [services, setServices] = useState<Option[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)
  const showToast = useToast()

  function reportError(caught: unknown) {
    const message = readApiError(caught)
    setError(message)
    showToast('error', message)
  }

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const [staffResponse, branchResponse, serviceResponse] = await Promise.all([
        api.get('/api/v1/staff'),
        api.get('/api/v1/branches'),
        api.get('/api/v1/services'),
      ])
      setStaff(listFrom<StaffItem>(staffResponse.data))
      setBranches(listFrom<Option>(branchResponse.data))
      setServices(listFrom<Option>(serviceResponse.data))
    } catch (caught) {
      reportError(caught)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    let cancelled = false

    void (async () => {
      try {
        const [staffResponse, branchResponse, serviceResponse] = await Promise.all([
          api.get('/api/v1/staff'),
          api.get('/api/v1/branches'),
          api.get('/api/v1/services'),
        ])
        if (!cancelled) {
          setStaff(listFrom<StaffItem>(staffResponse.data))
          setBranches(listFrom<Option>(branchResponse.data))
          setServices(listFrom<Option>(serviceResponse.data))
        }
      } catch (caught) {
        if (!cancelled) {
          reportError(caught)
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    })()

    return () => {
      cancelled = true
    }
  }, [])

  async function saveProfile(event: FormEvent<HTMLFormElement>, staffId?: string) {
    event.preventDefault()
    const formElement = event.currentTarget
    const form = new FormData(formElement)
    const body = {
      user_id: String(form.get('user_id') ?? ''),
      branch_ids: form.getAll('branch_ids').map(String),
      service_ids: form.getAll('service_ids').map(String),
    }
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      if (staffId) {
        await api.patch(`/api/v1/staff/${staffId}`, body)
        showToast('success', 'Đã lưu hồ sơ.')
      } else {
        await api.post('/api/v1/staff', body)
        showToast('success', 'Đã tạo hồ sơ.')
        formElement.reset()
      }
      await load()
    } catch (caught) {
      reportError(caught)
    } finally {
      setPending(false)
    }
  }

  async function saveHours(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const staffId = String(form.get('staff_id') ?? '')
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.put(`/api/v1/staff/${staffId}/working-hours`, {
        intervals: [
          {
            weekday: Number(form.get('weekday')),
            starts_at: String(form.get('starts_at') ?? ''),
            ends_at: String(form.get('ends_at') ?? ''),
          },
        ],
      })
      showToast('success', 'Đã lưu giờ làm.')
    } catch (caught) {
      reportError(caught)
    } finally {
      setPending(false)
    }
  }

  async function addException(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const formElement = event.currentTarget
    const form = new FormData(formElement)
    const staffId = String(form.get('staff_id') ?? '')
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.post(`/api/v1/staff/${staffId}/exceptions`, {
        type: String(form.get('type') ?? 'off'),
        starts_at: String(form.get('starts_at') ?? ''),
        ends_at: String(form.get('ends_at') ?? ''),
      })
      showToast('success', 'Đã thêm ngoại lệ.')
      formElement.reset()
    } catch (caught) {
      reportError(caught)
    } finally {
      setPending(false)
    }
  }

  return (
    <section>
      <h1>Nhân viên</h1>
      {error ? <p role="alert">{error}</p> : null}
      {loading ? <p>Đang tải</p> : null}
      {!loading && staff.length === 0 ? <p>Chưa có nhân viên</p> : null}
      {staff.length > 0 ? (
        <ul>
          {staff.map((profile) => (
            <li key={profile.id}>
              {profile.name}
            </li>
          ))}
        </ul>
      ) : null}

      <form onSubmit={(event) => void saveProfile(event)}>
        <h2>Gán nhân viên</h2>
        <label>
          Mã người dùng
          <input name="user_id" required />
        </label>
        <fieldset>
          <legend>Chi nhánh</legend>
          {branches.map((branch) => (
            <label key={branch.id}>
              <input type="checkbox" name="branch_ids" value={branch.id} />
              {branch.name}
            </label>
          ))}
        </fieldset>
        <fieldset>
          <legend>Dịch vụ</legend>
          {services.map((service) => (
            <label key={service.id}>
              <input type="checkbox" name="service_ids" value={service.id} />
              {service.name}
            </label>
          ))}
        </fieldset>
        <button type="submit" disabled={pending}>
          {pending ? 'Đang lưu...' : 'Tạo hồ sơ'}
        </button>
      </form>

      {staff.length > 0 ? (
        <>
          <form onSubmit={(event) => void saveHours(event)}>
            <h2>Giờ làm</h2>
            <label>
              Nhân viên
              <select name="staff_id" defaultValue={staff[0]?.id}>
                {staff.map((profile) => (
                  <option key={profile.id} value={profile.id}>
                    {profile.name}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Thứ
              <select name="weekday" defaultValue="1">
                {weekdays.map((label, weekday) => (
                  <option key={label} value={weekday}>
                    {label}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Bắt đầu
              <input name="starts_at" type="time" defaultValue="09:00" required />
            </label>
            <label>
              Kết thúc
              <input name="ends_at" type="time" defaultValue="17:00" required />
            </label>
            <button type="submit" disabled={pending}>
              Lưu giờ làm
            </button>
          </form>

          <form onSubmit={(event) => void addException(event)}>
            <h2>Ngoại lệ</h2>
            <label>
              Nhân viên
              <select name="staff_id" defaultValue={staff[0]?.id}>
                {staff.map((profile) => (
                  <option key={profile.id} value={profile.id}>
                    {profile.name}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Loại
              <select name="type" defaultValue="off">
                <option value="off">Nghỉ</option>
                <option value="extra">Làm thêm</option>
              </select>
            </label>
            <label>
              Từ
              <input name="starts_at" type="datetime-local" required />
            </label>
            <label>
              Đến
              <input name="ends_at" type="datetime-local" required />
            </label>
            <button type="submit" disabled={pending}>
              Thêm ngoại lệ
            </button>
          </form>
        </>
      ) : null}
    </section>
  )
}
