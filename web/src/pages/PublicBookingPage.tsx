import { useEffect, useState, type CSSProperties } from 'react'
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

function formatClock(iso: string): string {
  return new Intl.DateTimeFormat('vi-VN', {
    timeZone: 'Asia/Ho_Chi_Minh',
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
  const [direction, setDirection] = useState<'forward' | 'back'>('forward')

  function goTo(next: number) {
    setDirection(next >= step ? 'forward' : 'back')
    setStep(next)
  }

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
      goTo(1)
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
      goTo(2)
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

  const sheetClass = `sheet go-${direction}`
  const price = new Intl.NumberFormat('vi-VN')

  return (
    <main className="stage wizard">
      <p className="mark">Sổ lịch</p>
      <h1>Đặt lịch</h1>
      {bookingId ? (
        <section className="sheet is-done">
          <span className="check" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4">
              <path d="M5 12.5l4.5 4.5L19 7.5" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </span>
          <h2>Đã đặt lịch.</h2>
          <dl className="summary">
            <div style={{ '--i': 0 } as CSSProperties}>
              <dt>Mã lịch</dt>
              <dd>
                <strong className="code">{bookingId}</strong>
              </dd>
            </div>
            <div style={{ '--i': 1 } as CSSProperties}>
              <dt>Chi nhánh</dt>
              <dd>{branch?.name}</dd>
            </div>
            <div style={{ '--i': 2 } as CSSProperties}>
              <dt>Dịch vụ</dt>
              <dd>{chosenServices.map((item) => item.name).join(', ')}</dd>
            </div>
            <div style={{ '--i': 3 } as CSSProperties}>
              <dt>Giờ đã chọn</dt>
              <dd>
                <time dateTime={startsAt}>{formatWhen(startsAt)}</time>
              </dd>
            </div>
          </dl>
          {error ? <p role="alert">{error}</p> : null}
          <button className="primary" type="button" disabled={pending} onClick={() => void pay()}>
            Thanh toán
          </button>
          {checkoutUrl ? <a href={checkoutUrl}>Thanh toán</a> : null}
        </section>
      ) : (
        <>
          <ol className="steps">
            {steps.map((label, index) => (
              <li
                key={label}
                className={index < step ? 'is-done' : undefined}
                aria-current={index === step ? 'step' : undefined}
              >
                {label}
              </li>
            ))}
          </ol>
          {error ? <p role="alert">{error}</p> : null}

          {step === 0 ? (
            <section className={sheetClass} key="branch">
              <h2>Chọn chi nhánh</h2>
              {loading ? <p className="mute">Đang tải</p> : null}
              {!loading && error === null && branches.length === 0 ? <p className="mute">Chưa có chi nhánh</p> : null}
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
              <button className="primary" type="button" disabled={branchId === '' || loading} onClick={() => void loadServices()}>
                Tiếp
              </button>
            </section>
          ) : null}

          {step === 1 ? (
            <section className={sheetClass} key="services">
              <h2>Chọn dịch vụ</h2>
              {services.map((item) => (
                <label key={item.id}>
                  <input
                    type="checkbox"
                    checked={serviceIds.includes(item.id)}
                    onChange={() => toggleService(item.id)}
                  />
                  <span className="grow">{item.name}</span>
                  <span className="mute">
                    {item.duration_minutes} phút · {price.format(item.price_amount)} VND
                  </span>
                </label>
              ))}
              <div className="sheet-actions">
                <button type="button" onClick={() => goTo(0)}>
                  Quay lại
                </button>
                <button className="primary" type="button" disabled={serviceIds.length === 0 || loading} onClick={() => void loadStaff()}>
                  Tiếp
                </button>
              </div>
            </section>
          ) : null}

          {step === 2 ? (
            <section className={sheetClass} key="staff">
              <h2>Chọn nhân viên</h2>
              {!loading && staff.length === 0 ? <p className="mute">Chưa có nhân viên</p> : null}
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
              <div className="sheet-actions">
                <button type="button" onClick={() => goTo(1)}>
                  Quay lại
                </button>
                <button className="primary" type="button" disabled={staffId === ''} onClick={() => goTo(3)}>
                  Tiếp
                </button>
              </div>
            </section>
          ) : null}

          {step === 3 ? (
            <section className={sheetClass} key="time">
              <h2>Chọn ngày và giờ</h2>
              <label>
                Ngày
                <input type="date" value={date} onChange={(event) => void loadSlots(event.target.value)} />
              </label>
              {loading && date !== '' ? <p className="mute">Đang tìm khung giờ</p> : null}
              {!loading && date !== '' && slots.length === 0 ? <p className="mute">Không còn khung giờ</p> : null}
              {slots.length > 0 ? (
                <div className="chips" key={date}>
                  {slots.map((slot, index) => (
                    <label key={slot.starts_at} style={{ '--i': index } as CSSProperties}>
                      <input
                        type="radio"
                        name="slot"
                        checked={startsAt === slot.starts_at}
                        onChange={() => setStartsAt(slot.starts_at)}
                      />
                      {formatClock(slot.starts_at)}
                    </label>
                  ))}
                </div>
              ) : null}
              <div className="sheet-actions">
                <button type="button" onClick={() => goTo(2)}>
                  Quay lại
                </button>
                <button className="primary" type="button" disabled={startsAt === ''} onClick={() => goTo(4)}>
                  Tiếp
                </button>
              </div>
            </section>
          ) : null}

          {step === 4 ? (
            <section className={sheetClass} key="guest">
              <h2>Thông tin khách</h2>
              <label>
                Tên
                <input value={name} onChange={(event) => setName(event.target.value)} required />
              </label>
              <label>
                Số điện thoại
                <input type="tel" value={phone} onChange={(event) => setPhone(event.target.value)} required />
              </label>
              <label>
                Email
                <input type="email" value={email} onChange={(event) => setEmail(event.target.value)} />
              </label>
              <div className="sheet-actions">
                <button type="button" onClick={() => goTo(3)}>
                  Quay lại
                </button>
                <button
                  className="primary"
                  type="button"
                  disabled={name.trim() === '' || phone.trim() === ''}
                  onClick={() => goTo(5)}
                >
                  Tiếp
                </button>
              </div>
            </section>
          ) : null}

          {step === 5 ? (
            <section className={sheetClass} key="confirm">
              <h2>Xác nhận</h2>
              <dl className="summary">
                <div style={{ '--i': 0 } as CSSProperties}>
                  <dt>Chi nhánh</dt>
                  <dd>{branch?.name}</dd>
                </div>
                <div style={{ '--i': 1 } as CSSProperties}>
                  <dt>Dịch vụ</dt>
                  <dd>{chosenServices.map((item) => item.name).join(', ')}</dd>
                </div>
                <div style={{ '--i': 2 } as CSSProperties}>
                  <dt>Nhân viên</dt>
                  <dd>{chosenStaff?.name}</dd>
                </div>
                <div style={{ '--i': 3 } as CSSProperties}>
                  <dt>Giờ</dt>
                  <dd>
                    <time dateTime={startsAt}>{formatWhen(startsAt)}</time>
                  </dd>
                </div>
                <div style={{ '--i': 4 } as CSSProperties}>
                  <dt>Khách</dt>
                  <dd>
                    {name} · {phone}
                  </dd>
                </div>
              </dl>
              <div className="sheet-actions">
                <button type="button" onClick={() => goTo(4)}>
                  Quay lại
                </button>
                <button className="primary" type="button" disabled={pending} onClick={() => void confirm()}>
                  {pending ? 'Đang đặt...' : 'Đặt lịch'}
                </button>
              </div>
            </section>
          ) : null}
        </>
      )}
    </main>
  )
}
