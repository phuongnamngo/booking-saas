import { useEffect, useState, type FormEvent } from 'react'
import { api, ensureCsrfCookie } from '../api/client'
import { listFrom } from './listFrom'
import { readApiError } from './readApiError'

type Branch = {
  id: string
  name: string
  timezone: string
  address: string | null
  phone: string | null
  is_active: boolean
}

const weekdays = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy']

export function BranchesPage() {
  const [branches, setBranches] = useState<Branch[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const response = await api.get('/api/v1/branches')
      setBranches(listFrom<Branch>(response.data))
    } catch (caught) {
      setError(readApiError(caught))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    let cancelled = false

    void (async () => {
      try {
        const response = await api.get('/api/v1/branches')
        if (!cancelled) {
          setBranches(listFrom<Branch>(response.data))
        }
      } catch (caught) {
        if (!cancelled) {
          setError(readApiError(caught))
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

  async function createBranch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.post('/api/v1/branches', {
        name: String(form.get('name') ?? ''),
        timezone: String(form.get('timezone') ?? 'Asia/Ho_Chi_Minh'),
        address: String(form.get('address') ?? '') || null,
        phone: String(form.get('phone') ?? '') || null,
      })
      event.currentTarget.reset()
      await load()
    } catch (caught) {
      setError(readApiError(caught))
    } finally {
      setPending(false)
    }
  }

  async function saveHours(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const branchId = String(form.get('branch_id') ?? '')
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.put(`/api/v1/branches/${branchId}/opening-hours`, {
        intervals: [
          {
            weekday: Number(form.get('weekday')),
            opens_at: String(form.get('opens_at') ?? ''),
            closes_at: String(form.get('closes_at') ?? ''),
          },
        ],
      })
    } catch (caught) {
      setError(readApiError(caught))
    } finally {
      setPending(false)
    }
  }

  async function addHoliday(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const branchId = String(form.get('branch_id') ?? '')
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.post(`/api/v1/branches/${branchId}/holidays`, {
        date: String(form.get('date') ?? ''),
        name: String(form.get('holiday_name') ?? ''),
      })
      event.currentTarget.reset()
    } catch (caught) {
      setError(readApiError(caught))
    } finally {
      setPending(false)
    }
  }

  async function removeBranch(branch: Branch) {
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.delete(`/api/v1/branches/${branch.id}`)
      await load()
    } catch (caught) {
      setError(readApiError(caught))
    } finally {
      setPending(false)
    }
  }

  return (
    <section>
      <h1>Chi nhánh</h1>
      {error ? <p role="alert">{error}</p> : null}
      {loading ? <p>Đang tải</p> : null}
      {!loading && branches.length === 0 ? <p>Chưa có chi nhánh</p> : null}
      {branches.length > 0 ? (
        <ul>
          {branches.map((branch) => (
            <li key={branch.id}>
              {branch.name} · {branch.timezone}
              <button type="button" disabled={pending} onClick={() => void removeBranch(branch)}>
                Xóa
              </button>
            </li>
          ))}
        </ul>
      ) : null}

      <form onSubmit={(event) => void createBranch(event)}>
        <h2>Thêm chi nhánh</h2>
        <label>
          Tên
          <input name="name" required />
        </label>
        <label>
          Múi giờ
          <input name="timezone" defaultValue="Asia/Ho_Chi_Minh" required />
        </label>
        <label>
          Địa chỉ
          <input name="address" />
        </label>
        <label>
          Điện thoại
          <input name="phone" />
        </label>
        <button type="submit" disabled={pending}>
          {pending ? 'Đang lưu...' : 'Tạo chi nhánh'}
        </button>
      </form>

      {branches.length > 0 ? (
        <>
          <form onSubmit={(event) => void saveHours(event)}>
            <h2>Giờ mở cửa</h2>
            <label>
              Chi nhánh
              <select name="branch_id" defaultValue={branches[0]?.id}>
                {branches.map((branch) => (
                  <option key={branch.id} value={branch.id}>
                    {branch.name}
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
              Mở
              <input name="opens_at" type="time" defaultValue="09:00" required />
            </label>
            <label>
              Đóng
              <input name="closes_at" type="time" defaultValue="18:00" required />
            </label>
            <button type="submit" disabled={pending}>
              Lưu giờ
            </button>
          </form>

          <form onSubmit={(event) => void addHoliday(event)}>
            <h2>Ngày nghỉ</h2>
            <label>
              Chi nhánh
              <select name="branch_id" defaultValue={branches[0]?.id}>
                {branches.map((branch) => (
                  <option key={branch.id} value={branch.id}>
                    {branch.name}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Ngày
              <input name="date" type="date" required />
            </label>
            <label>
              Tên ngày nghỉ
              <input name="holiday_name" required />
            </label>
            <button type="submit" disabled={pending}>
              Thêm ngày nghỉ
            </button>
          </form>
        </>
      ) : null}
    </section>
  )
}
