import { useEffect, useState, type FormEvent } from 'react'
import { api, ensureCsrfCookie } from '../api/client'
import { useToast } from '../app/toast'
import { listFrom } from './listFrom'
import { readApiError } from './readApiError'

type Category = { id: string; name: string }

type ServiceItem = {
  id: string
  category_id: string | null
  name: string
  duration_minutes: number
  price_amount: number
  is_active: boolean
}

export function ServicesPage() {
  const [categories, setCategories] = useState<Category[]>([])
  const [services, setServices] = useState<ServiceItem[]>([])
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
      const [categoryResponse, serviceResponse] = await Promise.all([
        api.get('/api/v1/service-categories'),
        api.get('/api/v1/services'),
      ])
      setCategories(listFrom<Category>(categoryResponse.data))
      setServices(listFrom<ServiceItem>(serviceResponse.data))
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
        const [categoryResponse, serviceResponse] = await Promise.all([
          api.get('/api/v1/service-categories'),
          api.get('/api/v1/services'),
        ])
        if (!cancelled) {
          setCategories(listFrom<Category>(categoryResponse.data))
          setServices(listFrom<ServiceItem>(serviceResponse.data))
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

  async function createCategory(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const formElement = event.currentTarget
    const form = new FormData(formElement)
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.post('/api/v1/service-categories', { name: String(form.get('name') ?? '') })
      showToast('success', 'Đã tạo nhóm.')
      formElement.reset()
      await load()
    } catch (caught) {
      reportError(caught)
    } finally {
      setPending(false)
    }
  }

  async function createService(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const formElement = event.currentTarget
    const form = new FormData(formElement)
    const categoryId = String(form.get('category_id') ?? '')
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.post('/api/v1/services', {
        name: String(form.get('name') ?? ''),
        duration_minutes: Number(form.get('duration_minutes')),
        price_amount: Number(form.get('price_amount')),
        category_id: categoryId === '' ? null : categoryId,
        is_active: form.get('is_active') === 'on',
      })
      showToast('success', 'Đã tạo dịch vụ.')
      formElement.reset()
      await load()
    } catch (caught) {
      reportError(caught)
    } finally {
      setPending(false)
    }
  }

  async function saveService(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const serviceId = String(form.get('service_id') ?? '')
    const categoryId = String(form.get('category_id') ?? '')
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.patch(`/api/v1/services/${serviceId}`, {
        name: String(form.get('name') ?? ''),
        duration_minutes: Number(form.get('duration_minutes')),
        price_amount: Number(form.get('price_amount')),
        category_id: categoryId === '' ? null : categoryId,
        is_active: form.get('is_active') === 'on',
      })
      showToast('success', 'Đã lưu dịch vụ.')
      await load()
    } catch (caught) {
      reportError(caught)
    } finally {
      setPending(false)
    }
  }

  async function removeService(service: ServiceItem) {
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.delete(`/api/v1/services/${service.id}`)
      showToast('success', 'Đã xóa dịch vụ.')
      await load()
    } catch (caught) {
      reportError(caught)
    } finally {
      setPending(false)
    }
  }

  return (
    <section>
      <h1>Dịch vụ</h1>
      {error ? <p role="alert">{error}</p> : null}
      {loading ? <p>Đang tải</p> : null}
      {!loading && services.length === 0 ? <p>Chưa có dịch vụ</p> : null}
      {services.length > 0 ? (
        <ul>
          {services.map((service) => (
            <li key={service.id}>
              {service.name} · {service.duration_minutes} phút · {service.price_amount} VND
              {service.is_active ? '' : ' · ngừng nhận'}
              <button type="button" disabled={pending} onClick={() => void removeService(service)}>
                Xóa
              </button>
            </li>
          ))}
        </ul>
      ) : null}

      <form onSubmit={(event) => void createCategory(event)}>
        <h2>Nhóm dịch vụ</h2>
        <label>
          Tên nhóm
          <input name="name" required />
        </label>
        <button type="submit" disabled={pending}>
          Tạo nhóm
        </button>
      </form>

      <form onSubmit={(event) => void createService(event)}>
        <h2>Thêm dịch vụ</h2>
        <label>
          Tên
          <input name="name" required />
        </label>
        <label>
          Số phút
          <input name="duration_minutes" type="number" min={1} required />
        </label>
        <label>
          Giá (VND)
          <input name="price_amount" type="number" min={0} required />
        </label>
        <label>
          Nhóm
          <select name="category_id" defaultValue="">
            <option value="">Không chọn</option>
            {categories.map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
              </option>
            ))}
          </select>
        </label>
        <label>
          <input name="is_active" type="checkbox" defaultChecked />
          Đang nhận đặt
        </label>
        <button type="submit" disabled={pending}>
          {pending ? 'Đang lưu...' : 'Tạo dịch vụ'}
        </button>
      </form>

      {services.length > 0 ? (
        <form onSubmit={(event) => void saveService(event)}>
          <h2>Sửa dịch vụ</h2>
          <label>
            Dịch vụ
            <select name="service_id" defaultValue={services[0]?.id}>
              {services.map((service) => (
                <option key={service.id} value={service.id}>
                  {service.name}
                </option>
              ))}
            </select>
          </label>
          <label>
            Tên
            <input name="name" required defaultValue={services[0]?.name} />
          </label>
          <label>
            Số phút
            <input name="duration_minutes" type="number" min={1} required defaultValue={services[0]?.duration_minutes} />
          </label>
          <label>
            Giá (VND)
            <input name="price_amount" type="number" min={0} required defaultValue={services[0]?.price_amount} />
          </label>
          <label>
            Nhóm
            <select name="category_id" defaultValue={services[0]?.category_id ?? ''}>
              <option value="">Không chọn</option>
              {categories.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </select>
          </label>
          <label>
            <input name="is_active" type="checkbox" defaultChecked={services[0]?.is_active} />
            Đang nhận đặt
          </label>
          <button type="submit" disabled={pending}>
            Lưu dịch vụ
          </button>
        </form>
      ) : null}
    </section>
  )
}
