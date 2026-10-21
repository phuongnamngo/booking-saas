import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { api, ensureCsrfCookie } from '../api/client'
import { listFrom } from './listFrom'
import { readApiError } from './readApiError'

type Branch = { id: string; name: string; timezone: string }
type ServiceItem = { id: string; name: string; duration_minutes: number; price_amount: number }
type StaffItem = { id: string; name: string }
type Slot = { starts_at: string; ends_at: string }

const steps = ['Chi nhánh', 'Dịch vụ', 'Nhân viên', 'Ngày và giờ', 'Khách', 'Xác nhận']

function formatWhen(iso: string): string {
  return new Intl.DateTimeFormat('vi-VN', {
    timeZone: 'Asia/Ho_Chi_Minh',
    weekday: 'short',
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(iso))
}

export function PublicBookingPage() {
  const { slug = '' } = useParams()
  const [step, setStep] = useState(0)
  const [branches, setBranches] = useState<Branch[]>([])
  const [services, setServices] = useState<ServiceItem[]>([])
  const [staff, setStaff] = useState<StaffItem[]>([])
  const [slots, setSlots] = useState<Slot[]>([])
  const [branchId, setBranchId] = useState('')
  const [serviceIds, setServiceIds] = useState<string[]>([])
  const [staffId, setStaffId] = useState('')
  const [date, setDate] = useState('')
  const [startsAt, setStartsAt] = useState('')
  const [name, setName] = useState('')
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState('')
  const [bookingId, setBookingId] = useState<string | null>(null)
  const [checkoutUrl, setCheckoutUrl] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)

  useEffect(() => {
    let cancelled = false
    async function loadBranches() {
      setLoading(true)
      setError(null)
      try {
        const response = await api.get(`/api/v1/public/${slug}/branches`)
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
    }
    void loadBranches()
    return () => {
      cancelled = true
    }
  }, [slug])

  async function loadServices() {
    setLoading(true)
    setError(null)
    try {
      const response = await api.get(`/api/v1/public/${slug}/services`)
      setServices(listFrom<ServiceItem>(response.data))
      setStep(1)
    } catch (caught) {
      setError(readApiError(caught))
    } finally {
      setLoading(false)
    }
  }

  async function loadStaff() {
    setLoading(true)
    setError(null)
    try {
      const response = await api.get(`/api/v1/public/${slug}/staff`, {
        params: { branch_id: branchId, service_ids: serviceIds },
      })
      setStaff(listFrom<StaffItem>(response.data))
      setStep(2)
    } catch (caught) {
      setError(readApiError(caught))
    } finally {
      setLoading(false)
    }
  }

  async function loadSlots(nextDate: string) {
    setDate(nextDate)
    setStartsAt('')
    if (nextDate === '') {
      setSlots([])
      return
    }
    setLoading(true)
    setError(null)
    try {
      const response = await api.get(`/api/v1/public/${slug}/availability`, {
        params: {
          branch_id: branchId,
          staff_id: staffId,
          service_ids: serviceIds,
          date: nextDate,
        },
      })
      const payload = response.data as { slots?: Slot[] }
      setSlots(Array.isArray(payload.slots) ? payload.slots : [])
    } catch (caught) {
      setError(readApiError(caught))
    } finally {
      setLoading(false)
    }
  }

  function toggleService(id: string) {
    setServiceIds((current) =>
      current.includes(id) ? current.filter((item) => item !== id) : [...current, id],
    )
  }

  async function confirm() {
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      const response = await api.post(`/api/v1/public/${slug}/appointments`, {
        branch_id: branchId,
        staff_id: staffId,
        service_ids: serviceIds,
        starts_at: startsAt,
        customer: {
          name,
          phone,
          email: email === '' ? null : email,
        },
      })
      const payload = response.data as { id: string }
      setBookingId(payload.id)
    } catch (caught) {
      setError(readApiError(caught))
    } finally {
      setPending(false)
    }
  }

  async function pay() {
    if (bookingId === null) {
      return
    }
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      const response = await api.post(`/api/v1/public/${slug}/appointments/${bookingId}/checkout`)
      setCheckoutUrl((response.data as { url: string }).url)
    } catch (caught) {
      setError(readApiError(caught))
    } finally {
      setPending(false)
    }
  }

  const branch = branches.find((item) => item.id === branchId)
  const chosenServices = services.filter((item) => serviceIds.includes(item.id))
  const chosenStaff = staff.find((item) => item.id === staffId)

  return (
    <main className="stage wizard">
      <p className="mark">Sổ lịch</p>
      <h1>Đặt lịch</h1>
      {bookingId ? (
        <section className="sheet">
          <p>Đã đặt lịch.</p>
          <p>
            Mã lịch <strong>{bookingId}</strong>
          </p>
          <p>
            Giờ đã chọn <time dateTime={startsAt}>{formatWhen(startsAt)}</time>
          </p>
          <button className="primary" type="button" disabled={pending} onClick={() => void pay()}>
            Thanh toán
          </button>
          {checkoutUrl ? <a href={checkoutUrl}>Thanh toán</a> : null}
        </section>
      ) : (
        <>
          <ol className="steps">
            {steps.map((label, index) => (
              <li key={label} aria-current={index === step ? 'step' : undefined}>
                {label}
              </li>
            ))}
          </ol>
          {error ? <p role="alert">{error}</p> : null}
          {loading ? <p>Đang tải</p> : null}

          {step === 0 ? (
            <section className="sheet">
              <h2>Chọn chi nhánh</h2>
              {!loading && error === null && branches.length === 0 ? <p>Chưa có chi nhánh</p> : null}
              {branches.map((item) => (
                <label key={item.id}>
                  <input
                    type="radio"
                    name="branch"
                    checked={branchId === item.id}
                    onChange={() => setBranchId(item.id)}
                  />
                  {item.name}
                </label>
              ))}
              <button className="primary" type="button" disabled={branchId === ''} onClick={() => void loadServices()}>
                Tiếp
              </button>
            </section>
          ) : null}

          {step === 1 ? (
            <section className="sheet">
              <h2>Chọn dịch vụ</h2>
              {services.map((item) => (
                <label key={item.id}>
                  <input
                    type="checkbox"
                    checked={serviceIds.includes(item.id)}
                    onChange={() => toggleService(item.id)}
                  />
                  {item.name} · {item.duration_minutes} phút · {item.price_amount} VND
                </label>
              ))}
              <button type="button" onClick={() => setStep(0)}>
                Quay lại
              </button>
              <button className="primary" type="button" disabled={serviceIds.length === 0} onClick={() => void loadStaff()}>
                Tiếp
              </button>
            </section>
          ) : null}

          {step === 2 ? (
            <section className="sheet">
              <h2>Chọn nhân viên</h2>
              {!loading && staff.length === 0 ? <p>Chưa có nhân viên</p> : null}
              {staff.map((item) => (
                <label key={item.id}>
                  <input
                    type="radio"
                    name="staff"
                    checked={staffId === item.id}
                    onChange={() => setStaffId(item.id)}
                  />
                  {item.name}
                </label>
              ))}
              <button type="button" onClick={() => setStep(1)}>
                Quay lại
              </button>
              <button className="primary" type="button" disabled={staffId === ''} onClick={() => setStep(3)}>
                Tiếp
              </button>
            </section>
          ) : null}

          {step === 3 ? (
            <section className="sheet">
              <h2>Chọn ngày và giờ</h2>
              <label>
                Ngày
                <input type="date" value={date} onChange={(event) => void loadSlots(event.target.value)} />
              </label>
              {!loading && date !== '' && slots.length === 0 ? <p>Không còn khung giờ</p> : null}
              {slots.map((slot) => (
                <label key={slot.starts_at}>
                  <input
                    type="radio"
                    name="slot"
                    checked={startsAt === slot.starts_at}
                    onChange={() => setStartsAt(slot.starts_at)}
                  />
                  {formatWhen(slot.starts_at)}
                </label>
              ))}
              <button type="button" onClick={() => setStep(2)}>
                Quay lại
              </button>
              <button className="primary" type="button" disabled={startsAt === ''} onClick={() => setStep(4)}>
                Tiếp
              </button>
            </section>
          ) : null}

          {step === 4 ? (
            <section className="sheet">
              <h2>Thông tin khách</h2>
              <label>
                Tên
                <input value={name} onChange={(event) => setName(event.target.value)} required />
              </label>
              <label>
                Số điện thoại
                <input value={phone} onChange={(event) => setPhone(event.target.value)} required />
              </label>
              <label>
                Email
                <input type="email" value={email} onChange={(event) => setEmail(event.target.value)} />
              </label>
              <button type="button" onClick={() => setStep(3)}>
                Quay lại
              </button>
              <button
                className="primary"
                type="button"
                disabled={name.trim() === '' || phone.trim() === ''}
                onClick={() => setStep(5)}
              >
                Tiếp
              </button>
            </section>
          ) : null}

          {step === 5 ? (
            <section className="sheet">
              <h2>Xác nhận</h2>
              <p>{branch?.name}</p>
              <p>{chosenServices.map((item) => item.name).join(', ')}</p>
              <p>{chosenStaff?.name}</p>
              <p>
                <time dateTime={startsAt}>{formatWhen(startsAt)}</time>
              </p>
              <p>
                {name} · {phone}
              </p>
              <button type="button" onClick={() => setStep(4)}>
                Quay lại
              </button>
              <button className="primary" type="button" disabled={pending} onClick={() => void confirm()}>
                {pending ? 'Đang đặt...' : 'Đặt lịch'}
              </button>
            </section>
          ) : null}
        </>
      )}
    </main>
  )
}
